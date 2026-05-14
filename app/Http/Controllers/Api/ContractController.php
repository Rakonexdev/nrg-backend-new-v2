<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Http\Resources\ContractResource;
use Illuminate\Http\Request;

use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ContractController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view_contracts', only: ['index', 'show', 'summary']),
            new Middleware('permission:contract_create', only: ['store']),
            new Middleware('permission:contract_edit', only: ['update', 'addAdjustment']),
            new Middleware('permission:contract_delete', only: ['destroy']),
        ];
    }
    public function summary()
    {
        $contractStats = Contract::selectRaw('
            COUNT(*) as total_contracts,
            SUM(total_income) as total_income_sum,
            SUM(paid_amount) as total_paid,
            SUM(pending_amount) as total_pending,
            SUM(qid_renewal_fee + passport_renewal_fee + profession_change_fee + sponsorship_change_fee + health_card_fee + others_fee) as total_fees
        ')->first();

        $adjustmentTotal = (float) \App\Models\ContractAdjustment::sum('amount');
        $recoverableTotal = (float) \App\Models\Expense::where('is_recoverable', true)->sum('amount');
        $netAdjustments = $adjustmentTotal - $recoverableTotal;

        $expenseStats = \App\Models\Expense::selectRaw('
            SUM(CASE WHEN contract_id IS NOT NULL AND is_recoverable = 0 THEN amount ELSE 0 END) as contract_linked_expenses,
            SUM(CASE WHEN contract_id IS NULL AND is_recoverable = 0 THEN amount ELSE 0 END) as general_overheads
        ')->first();

        $totalValue = (float) $contractStats->total_income_sum + $netAdjustments;
        $totalContractProfit = (float) $contractStats->total_paid + $netAdjustments - ((float) $contractStats->total_fees + (float) $expenseStats->contract_linked_expenses);
        $totalOverheads = (float) $expenseStats->general_overheads;
        $netCompanyProfit = $totalContractProfit - $totalOverheads;

        $adjustmentPendingTotal = (float) \App\Models\ContractAdjustment::sum('pending_amount');

        return response()->json([
            'total_contracts' => (int) $contractStats->total_contracts,
            'total_value' => round($totalValue, 2),
            'total_paid' => round((float) $contractStats->total_paid, 2),
            'total_pending' => round((float) $contractStats->total_pending + $adjustmentPendingTotal, 2),
            'total_personal_due_pending' => round($adjustmentPendingTotal, 2),
            'total_contract_profit' => round($totalContractProfit, 2),
            'total_overheads' => round($totalOverheads, 2),
            'net_company_profit' => round($netCompanyProfit, 2)
        ]);
    }

    public function index(Request $request)
    {
        $query = Contract::with(['staff.company', 'staff.branch', 'adjustments'])
            ->withSum([
                'expenses as expense_total' => function ($q) {
                    $q->where('is_recoverable', false);
                }
            ], 'amount');

        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($subQuery) use ($search) {
                $subQuery->whereHas('staff', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('qid_number', 'like', "%{$search}%")
                        ->orWhereHas('company', function ($cq) use ($search) {
                            $cq->where('name', 'like', "%{$search}%");
                        });
                });
            });
        }

        if ($request->filled('pending_only')) {
            $query->where(function ($q) {
                // Main contract balance pending
                $q->where('pending_amount', '>', 0)
                    // OR has adjustments with pending amounts
                    ->orWhereHas('adjustments', function ($aq) {
                        $aq->where('pending_amount', '>', 0);
                    });
            });
        }

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->get('payment_status'));
        }

        if ($request->filled('staff_id')) {
            $query->where('staff_id', $request->get('staff_id'));
        }

        if ($request->filled('from_date')) {
            $query->whereDate('contract_date', '>=', $request->get('from_date'));
        }

        if ($request->filled('to_date')) {
            $query->whereDate('contract_date', '<=', $request->get('to_date'));
        }

        $sortColumn = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        $allowedSortColumns = ['contract_date', 'start_date', 'end_date', 'total_income', 'paid_amount', 'pending_amount', 'payment_status', 'payment_type', 'created_at'];

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
            'contract_date' => 'nullable|date',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'total_income' => 'required|numeric|min:0.01',

            'payment_type' => 'required|in:Cash,Online',
            'qid_renewal_fee' => 'nullable|numeric|min:0',
            'qid_next_renewal_date' => 'nullable|date',
            'passport_renewal_fee' => 'nullable|numeric|min:0',
            'profession_change_fee' => 'nullable|numeric|min:0',
            'sponsorship_change_fee' => 'nullable|numeric|min:0',
            'health_card_fee' => 'nullable|numeric|min:0',
            'others_fee' => 'nullable|numeric|min:0',
            'others_reason' => 'nullable|string',
        ]);
        $data['total_income'] = (float) ($data['total_income'] ?? 0);


        $entity = Contract::create($data);
        $entity->syncPaymentTracking();
        return new ContractResource($entity->load(['staff', 'payments', 'expenses'])
            ->loadSum([
                'expenses as expense_total' => function ($q) {
                    $q->where('is_recoverable', false);
                }
            ], 'amount'));
    }

    public function show($id)
    {
        return new ContractResource(
            Contract::with(['staff.company', 'staff.branch', 'payments.settlement', 'expenses', 'adjustments'])
                ->withSum([
                    'expenses as expense_total' => function ($q) {
                        $q->where('is_recoverable', false);
                    }
                ], 'amount')
                ->findOrFail($id)
        );
    }

    public function update(Request $request, $id)
    {
        $entity = Contract::findOrFail($id);
        $data = $request->validate([
            'staff_id' => 'sometimes|exists:staff,id',
            'contract_date' => 'nullable|date',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'total_income' => 'required|numeric|min:0.01',

            'payment_type' => 'sometimes|in:Cash,Online',
            'qid_renewal_fee' => 'nullable|numeric|min:0',
            'qid_next_renewal_date' => 'nullable|date',
            'passport_renewal_fee' => 'nullable|numeric|min:0',
            'profession_change_fee' => 'nullable|numeric|min:0',
            'sponsorship_change_fee' => 'nullable|numeric|min:0',
            'health_card_fee' => 'nullable|numeric|min:0',
            'others_fee' => 'nullable|numeric|min:0',
            'others_reason' => 'nullable|string',
        ]);
        $data['total_income'] = (float) ($data['total_income'] ?? $entity->total_income ?? 0);


        $newTotalIncome = array_key_exists('total_income', $data)
            ? round((float) $data['total_income'], 2)
            : round((float) $entity->total_income, 2);

        if ($newTotalIncome < (float) $entity->paid_amount) {
            return response()->json([
                'message' => 'Total income cannot be less than the amount already recorded in payment history.'
            ], 422);
        }

        $entity->update($data);
        $entity->syncPaymentTracking();
        return new ContractResource($entity->load(['staff', 'payments', 'expenses'])
            ->loadSum([
                'expenses as expense_total' => function ($q) {
                    $q->where('is_recoverable', false);
                }
            ], 'amount'));
    }

    public function addAdjustment(Request $request, $id)
    {
        $contract = Contract::findOrFail($id);

        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'paid_amount' => 'nullable|numeric|min:0',
            'reason' => 'required|string|max:255',
            'adjustment_date' => 'required|date',
            'next_payment_date' => 'nullable|date',
            'payment_method' => 'nullable|string'
        ]);

        $totalAmount = (float) $data['amount'];
        $paidAmount = (float) ($data['paid_amount'] ?? 0);
        $pendingAmount = round($totalAmount - $paidAmount, 2);

        $adjustment = $contract->adjustments()->create([
            'amount' => $totalAmount,
            'paid_amount' => $paidAmount,
            'pending_amount' => $pendingAmount,
            'reason' => $data['reason'],
            'adjustment_date' => $data['adjustment_date'],
            'next_payment_date' => $data['next_payment_date'] ?? null,
            'created_by' => auth()->id(),
        ]);

        if ($paidAmount > 0) {
            $contract->payments()->create([
                'amount' => $paidAmount,
                'payment_date' => $data['adjustment_date'],
                'payment_method' => $data['payment_method'] ?? 'Cash',
                'subcategory' => $data['reason'],
                'contract_adjustment_id' => $adjustment->id,
                'created_by' => auth()->id(),
            ]);
        }

        $contract->syncPaymentTracking();

        return new ContractResource($contract->load(['staff', 'payments', 'expenses', 'adjustments'])
            ->loadSum([
                'expenses as expense_total' => function ($q) {
                    $q->where('is_recoverable', false);
                }
            ], 'amount'));
    }

    public function destroy($id)
    {
        $entity = Contract::findOrFail($id);
        $entity->delete();
        return response()->json(['message' => 'Deleted']);
    }
}
