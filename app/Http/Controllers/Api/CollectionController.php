<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Collection;
use App\Models\Company;
use App\Services\CollectionService;
use Illuminate\Http\Request;

class CollectionController extends Controller
{
    protected $collectionService;
    public function __construct(CollectionService $collectionService) {
        $this->collectionService = $collectionService;
    }

    public function index()
    {
        return Collection::with('invoice', 'company', 'collector')->paginate(15);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'invoice_id' => 'required|exists:invoices,id',
            'company_id' => 'required|exists:companies,id',
            'collected_amount' => 'required|numeric',
            'payment_channel' => 'required|in:cash,mobile_pay,bank_transfer',
            'next_due_date' => 'nullable|date',
            'collection_date' => 'required|date',
            'notes' => 'nullable|string'
        ]);
        
        $data['collector_id'] = $request->user()->id;
        
        $collection = $this->collectionService->createCollection($data);
        return response()->json($collection, 201);
    }

    public function unsettled(Request $request)
    {
        return Collection::where('collector_id', $request->user()->id)
            ->where('is_settled', false)
            ->get();
    }

    /**
     * Returns all companies with outstanding contracts (Partially Paid / Payment Not Initialized),
     * grouped by company with staff and contract details for the collector dashboard.
     */
    public function pendingCollections()
    {
        $companies = Company::with([
            'staff' => function ($query) {
                $query->select('id', 'company_id', 'name', 'mobile', 'profession');
            },
            'staff.contracts' => function ($query) {
                $query->whereIn('payment_status', ['Partially Paid', 'Payment Not Initialized'])
                      ->where('pending_amount', '>', 0)
                      ->select('id', 'staff_id', 'total_income', 'paid_amount', 'pending_amount', 'payment_status', 'payment_type', 'start_date', 'end_date');
            }
        ])
        ->whereHas('staff.contracts', function ($query) {
            $query->whereIn('payment_status', ['Partially Paid', 'Payment Not Initialized'])
                  ->where('pending_amount', '>', 0);
        })
        ->select('id', 'name', 'contact_person', 'phone_number')
        ->get();

        $result = $companies->map(function ($company) {
            $contracts = collect();
            foreach ($company->staff as $staff) {
                foreach ($staff->contracts as $contract) {
                    $contracts->push([
                        'contract_id'    => $contract->id,
                        'staff_id'       => $staff->id,
                        'staff_name'     => $staff->name,
                        'staff_mobile'   => $staff->mobile,
                        'profession'     => $staff->profession,
                        'total_income'   => (float) $contract->total_income,
                        'paid_amount'    => (float) $contract->paid_amount,
                        'pending_amount' => (float) $contract->pending_amount,
                        'payment_status' => $contract->payment_status,
                        'payment_type'   => $contract->payment_type,
                        'start_date'     => optional($contract->start_date)->format('Y-m-d'),
                        'end_date'       => optional($contract->end_date)->format('Y-m-d'),
                    ]);
                }
            }

            return [
                'company_id'     => $company->id,
                'company_name'   => $company->name,
                'contact_person' => $company->contact_person,
                'phone_number'   => $company->phone_number,
                'total_pending'  => $contracts->sum('pending_amount'),
                'contracts'      => $contracts->values(),
            ];
        })->filter(fn($c) => $c['contracts']->count() > 0)->values();

        return response()->json($result);
    }

    public function show($id)
    {
        $numericId = is_object($id) ? ($id->id ?? null) : $id;
        $collection = Collection::with('invoice', 'company', 'collector')->find($numericId);
        if (!$collection) {
            $payment = \App\Models\ContractPayment::find($numericId);
            return response()->json($payment ?? ['message' => 'Record not found'], $payment ? 200 : 404);
        }
        return response()->json($collection);
    }

    public function update(Request $request, $id)
    {
        return $this->updateStatus($request, $id);
    }

    public function updateStatus(Request $request, $id)
    {
        try {
            $numericId = is_object($id) ? ($id->id ?? null) : $id;
            $status = $request->input('status') ?? $request->input('payment_status');

            if (!$status) {
                return response()->json(['message' => 'The status field is required.'], 422);
            }

            if (!in_array($status, ['collected', 'not_collected'])) {
                return response()->json(['message' => 'The status must be collected or not_collected.'], 422);
            }

            // Ensure status column exists in contract_payments table
            if (!\Illuminate\Support\Facades\Schema::hasColumn('contract_payments', 'status')) {
                try {
                    \Illuminate\Support\Facades\DB::statement("ALTER TABLE contract_payments ADD status VARCHAR(255) DEFAULT 'not_collected'");
                } catch (\Throwable $e) {}
            }

            // Update status directly in contract_payments table
            $affected = \Illuminate\Support\Facades\DB::table('contract_payments')
                ->where('id', $numericId)
                ->update(['status' => $status]);

            if (!$affected) {
                $exists = \Illuminate\Support\Facades\DB::table('contract_payments')->where('id', $numericId)->exists();
                if (!$exists) {
                    return response()->json(['message' => 'Payment record not found'], 404);
                }
            }

            return response()->json([
                'message' => 'Status updated successfully',
                'status' => $status,
                'id' => $numericId
            ]);
        } catch (\Throwable $th) {
            \Illuminate\Support\Facades\Log::error("Failed to update collection status: " . $th->getMessage());
            return response()->json([
                'message' => 'Failed to update status: ' . $th->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        $numericId = is_object($id) ? ($id->id ?? null) : $id;
        $deleted = \Illuminate\Support\Facades\DB::table('contract_payments')->where('id', $numericId)->delete();
        if ($deleted) {
            return response()->json(['message' => 'Payment deleted successfully']);
        }

        if (\Illuminate\Support\Facades\Schema::hasTable('collections')) {
            $deletedCollection = \Illuminate\Support\Facades\DB::table('collections')->where('id', $numericId)->delete();
            if ($deletedCollection) {
                return response()->json(['message' => 'Collection deleted successfully']);
            }
        }

        return response()->json(['message' => 'Record not found'], 404);
    }
}