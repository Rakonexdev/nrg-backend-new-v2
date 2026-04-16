<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Http\Resources\ContractResource;
use Illuminate\Http\Request;

class ContractController extends Controller
{
    public function index(Request $request)
    {
        $query = Contract::with(['staff', 'company'])->withSum('expenses as expense_total', 'amount');
        
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($subQuery) use ($search) {
                $subQuery->whereHas('staff', function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%");
                })->orWhereHas('company', function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%");
                });
            });
        }

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->get('payment_status'));
        }

        $sortColumn = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        $allowedSortColumns = ['start_date', 'end_date', 'contract_value', 'paid_amount', 'pending_amount', 'payment_status', 'payment_type', 'created_at'];

        if (in_array($sortColumn, $allowedSortColumns, true)) {
            $query->orderBy($sortColumn, $sortDirection);
        } else {
            $query->latest();
        }

        $perPage = $request->get('per_page', 15);

        return ContractResource::collection($query->paginate($perPage));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'staff_id' => 'required|exists:staff,id',
            'company_id' => 'required|exists:companies,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'contract_value' => 'required|numeric|min:0',
            'payment_type' => 'required|in:Cash,Online',
        ]);

        $entity = Contract::create($data);
        $entity->syncPaymentTracking();
        return new ContractResource($entity->load(['staff', 'company', 'payments'])->loadSum('expenses as expense_total', 'amount'));
    }

    public function show($id)
    {
        return new ContractResource(
            Contract::with(['staff', 'company', 'payments'])
                ->withSum('expenses as expense_total', 'amount')
                ->findOrFail($id)
        );
    }

    public function update(Request $request, $id)
    {
        $entity = Contract::findOrFail($id);
        $data = $request->validate([
            'staff_id' => 'sometimes|exists:staff,id',
            'company_id' => 'sometimes|exists:companies,id',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after_or_equal:start_date',
            'contract_value' => 'sometimes|numeric|min:0',
            'payment_type' => 'sometimes|in:Cash,Online',
        ]);

        $newContractValue = array_key_exists('contract_value', $data)
            ? round((float) $data['contract_value'], 2)
            : round((float) $entity->contract_value, 2);

        if ($newContractValue < (float) $entity->paid_amount) {
            return response()->json([
                'message' => 'Contract value cannot be less than the amount already recorded in payment history.'
            ], 422);
        }

        $entity->update($data);
        $entity->syncPaymentTracking();
        return new ContractResource($entity->load(['staff', 'company', 'payments'])->loadSum('expenses as expense_total', 'amount'));
    }

    public function destroy($id)
    {
        $entity = Contract::findOrFail($id);
        $entity->delete();
        return response()->json(['message' => 'Deleted']);
    }
}
