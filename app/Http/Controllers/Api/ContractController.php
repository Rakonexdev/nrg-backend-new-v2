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
            new Middleware('permission:contract_edit', only: ['update', 'addAdjustment', 'updateAdjustment', 'updateNextDueDate']),
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

        $adjustmentPaidTotal = (float) \App\Models\ContractAdjustment::sum('paid_amount');

        return response()->json([
            'total_contracts' => (int) $contractStats->total_contracts,
            'total_value' => round($totalValue, 2),
            'total_paid' => round((float) $contractStats->total_paid + $adjustmentPaidTotal, 2),
            'total_pending' => round((float) $contractStats->total_pending + $adjustmentPendingTotal, 2),
            'total_personal_due_pending' => round($adjustmentPendingTotal, 2),
            'total_contract_profit' => round($totalContractProfit, 2),
            'total_overheads' => round($totalOverheads, 2),
            'net_company_profit' => round($netCompanyProfit, 2)
        ]);
    }

    public function index(Request $request)
    {
        if ($request->boolean('minimal')) {
            $query = Contract::with(['staff:id,name,qid_number,mobile,qid_expiry,passport_expiry,profession,company_id', 'staff.company:id,name'])
                ->withSum('adjustments as adjustments_paid_sum', 'paid_amount')
                ->withSum(['expenses as recoverable_expense_total' => function ($q) {
                    $q->where('is_recoverable', true);
                }], 'amount');

            if ($request->filled('staff_id')) {
                $query->where('staff_id', $request->get('staff_id'));
            }

            $perPage = $request->get('per_page', 1000);
            $contracts = $query->latest()->paginate($perPage);

            return response()->json([
                'data' => collect($contracts->items())->map(function ($c) {
                    return [
                        'id' => $c->id,
                        'adjustment_paid_total' => (float)($c->adjustments_paid_sum ?? 0) - (float)($c->recoverable_expense_total ?? 0),
                        'staff' => $c->staff ? [
                            'id' => $c->staff->id,
                            'name' => $c->staff->name,
                            'qid_number' => $c->staff->qid_number,
                            'mobile' => $c->staff->mobile,
                            'qid_expiry' => $c->staff->qid_expiry,
                            'passport_expiry' => $c->staff->passport_expiry,
                            'profession' => $c->staff->profession,
                            'company' => $c->staff->company ? ['name' => $c->staff->company->name] : null
                        ] : null
                    ];
                }),
                'meta' => [
                    'current_page' => $contracts->currentPage(),
                    'last_page' => $contracts->lastPage(),
                    'per_page' => $contracts->perPage(),
                    'total' => $contracts->total(),
                ]
            ]);
        }

        $query = Contract::with(['staff.company', 'staff.branch', 'adjustments.creator.roles', 'latestCompanyPayment', 'latestPersonalPayment'])
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
                        ->orWhere('mobile', 'like', "%{$search}%")
                        ->orWhere('alternative_mobile', 'like', "%{$search}%")
                        ->orWhereHas('company', function ($cq) use ($search) {
                            $cq->where('name', 'like', "%{$search}%")
                               ->orWhere('computer_card', 'like', "%{$search}%");
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

        if ($request->filled('company_id')) {
            $query->whereHas('staff', function ($q) use ($request) {
                $q->where('company_id', $request->get('company_id'));
            });
        }

        if ($request->filled('staff_id')) {
            $query->where('staff_id', $request->get('staff_id'));
        }

        $fromDate = $request->get('from_date');
        $toDate = $request->get('to_date');

        if ($fromDate || $toDate) {
            $query->where(function ($dateQuery) use ($fromDate, $toDate) {
                $applyDateRange = function ($builder, string $column) use ($fromDate, $toDate) {
                    if ($fromDate) {
                        $builder->whereDate($column, '>=', $fromDate);
                    }

                    if ($toDate) {
                        $builder->whereDate($column, '<=', $toDate);
                    }
                };

                $dateQuery->where(function ($contractDateQuery) use ($applyDateRange) {
                    $applyDateRange($contractDateQuery, 'contract_date');
                })->orWhereHas('payments', function ($paymentQuery) use ($applyDateRange) {
                    $paymentQuery->whereNull('contract_adjustment_id')
                        ->whereNotNull('next_payment_date')
                        ->where(function ($paymentDateQuery) use ($applyDateRange) {
                            $applyDateRange($paymentDateQuery, 'next_payment_date');
                        });
                })->orWhereHas('adjustments', function ($adjustmentQuery) use ($applyDateRange) {
                    $adjustmentQuery->where('pending_amount', '>', 0)
                        ->whereNotNull('next_payment_date')
                        ->where(function ($adjustmentDateQuery) use ($applyDateRange) {
                            $applyDateRange($adjustmentDateQuery, 'next_payment_date');
                        });
                });
            });
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
            'notes' => 'nullable|string',
        ]);
        $data['total_income'] = (float) ($data['total_income'] ?? 0);


        $entity = Contract::create($data);
        $entity->syncPaymentTracking();
        return new ContractResource($entity->load(['staff', 'payments.creator.roles', 'expenses'])
            ->loadSum([
                'expenses as expense_total' => function ($q) {
                    $q->where('is_recoverable', false);
                }
            ], 'amount'));
    }

    public function show($id)
    {
        return new ContractResource(
            Contract::with(['staff.company', 'staff.branch', 'payments.settlement', 'payments.creator.roles', 'expenses', 'adjustments.creator.roles'])
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
        try {
            $contractId = $id instanceof Contract ? $id->id : $id;
            $entity = Contract::findOrFail($contractId);

            $input = $request->all();
            $dateFields = ['contract_date', 'start_date', 'end_date', 'qid_next_renewal_date'];
            foreach ($dateFields as $field) {
                if (array_key_exists($field, $input) && $input[$field] === '') {
                    $input[$field] = null;
                }
            }
            $feeFields = ['qid_renewal_fee', 'passport_renewal_fee', 'profession_change_fee', 'sponsorship_change_fee', 'health_card_fee', 'others_fee'];
            foreach ($feeFields as $field) {
                if (array_key_exists($field, $input) && ($input[$field] === '' || $input[$field] === null)) {
                    $input[$field] = null;
                }
            }
            if (array_key_exists('others_reason', $input) && $input['others_reason'] === '') {
                $input['others_reason'] = null;
            }
            if (array_key_exists('notes', $input) && $input['notes'] === '') {
                $input['notes'] = null;
            }
            $request->replace($input);

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
                'notes' => 'nullable|string',
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

            $existingColumns = \Illuminate\Support\Facades\Schema::getColumnListing($entity->getTable());
            $safeData = array_intersect_key($data, array_flip($existingColumns));

            $entity->update($safeData);
            $entity->syncPaymentTracking();
            return new ContractResource($entity->load(['staff', 'payments.creator.roles', 'expenses'])
                ->loadSum([
                    'expenses as expense_total' => function ($q) {
                        $q->where('is_recoverable', false);
                    }
                ], 'amount'));
        } catch (\Illuminate\Validation\ValidationException $ve) {
            throw $ve;
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Error updating contract: ' . $e->getMessage()
            ], 500);
        }
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

        return new ContractResource($contract->load(['staff', 'payments.creator.roles', 'expenses', 'adjustments.creator.roles'])
            ->loadSum([
                'expenses as expense_total' => function ($q) {
                    $q->where('is_recoverable', false);
                }
            ], 'amount'));
    }

    public function updateAdjustment(Request $request, $id, $adjustmentId)
    {
        $contract = Contract::findOrFail($id);
        $adjustment = $contract->adjustments()->findOrFail($adjustmentId);

        $data = $request->validate([
            'next_payment_date' => 'nullable|date',
            'amount'            => 'nullable|numeric|min:0.01',
            'paid_amount'       => 'nullable|numeric|min:0',
            'reason'            => 'nullable|string|max:255',
            'adjustment_date'   => 'nullable|date',
        ]);

        if (array_key_exists('next_payment_date', $data)) {
            $adjustment->next_payment_date = $data['next_payment_date'];
        }
        if (isset($data['amount'])) {
            $adjustment->amount = (float) $data['amount'];
        }
        if (isset($data['reason'])) {
            $adjustment->reason = $data['reason'];
        }
        if (isset($data['adjustment_date'])) {
            $adjustment->adjustment_date = $data['adjustment_date'];
        }
        $adjustment->save();

        $totalAmount = (float) $adjustment->amount;

        // Determine new paid total
        if (array_key_exists('paid_amount', $data) && $data['paid_amount'] !== null) {
            $newPaid = round((float) $data['paid_amount'], 2);

            if ($newPaid > $totalAmount) {
                return response()->json([
                    'message' => 'Paid amount cannot exceed the total adjustment amount.'
                ], 422);
            }

            // Sync ContractPayment records so the Payments History table reflects the change.
            $existingPaidSum = round((float) $adjustment->payments()->sum('amount'), 2);
            $delta = round($newPaid - $existingPaidSum, 2);

            if ($delta > 0) {
                // Extra amount paid — add a new payment record for the delta
                $paymentDate = isset($data['adjustment_date'])
                    ? $data['adjustment_date']
                    : ($adjustment->adjustment_date
                        ? $adjustment->adjustment_date->format('Y-m-d')
                        : now()->format('Y-m-d'));

                $contract->payments()->create([
                    'amount'                 => $delta,
                    'payment_date'           => $paymentDate,
                    'payment_method'         => 'Cash',
                    'subcategory'            => $adjustment->reason,
                    'contract_adjustment_id' => $adjustment->id,
                    'created_by'             => auth()->id(),
                ]);
            } elseif ($delta < 0) {
                // Amount reduced — remove payment records newest-first until delta is satisfied
                $toRemove = abs($delta);
                $paymentsDesc = $adjustment->payments()->orderByDesc('payment_date')->orderByDesc('id')->get();
                foreach ($paymentsDesc as $pmt) {
                    if ($toRemove <= 0) break;
                    $pmtAmount = (float) $pmt->amount;
                    if ($pmtAmount <= $toRemove) {
                        $toRemove -= $pmtAmount;
                        $pmt->delete();
                    } else {
                        $pmt->update(['amount' => round($pmtAmount - $toRemove, 2)]);
                        $toRemove = 0;
                    }
                }
            }

            // Re-calculate paid from actual payment records after sync
            $paid = round((float) $adjustment->payments()->sum('amount'), 2);
        } else {
            // No paid_amount supplied — derive from actual payment records
            $paid = round((float) $adjustment->payments()->sum('amount'), 2);
        }

        $adjustment->update([
            'paid_amount'   => $paid,
            'pending_amount' => round($totalAmount - $paid, 2),
        ]);

        $contract->syncPaymentTracking();

        return new ContractResource($contract->load(['staff', 'payments.creator.roles', 'expenses', 'adjustments.creator.roles'])
            ->loadSum([
                'expenses as expense_total' => function ($q) {
                    $q->where('is_recoverable', false);
                }
            ], 'amount'));
    }

    public function updateNextDueDate(Request $request, $id)
    {
        try {
            $contract = Contract::findOrFail($id);

            $data = $request->validate([
                'type' => 'required|in:collection,personal',
                'next_payment_date' => 'nullable|date',
            ]);

            $nextDate = $data['next_payment_date'] ?? null;

            if ($data['type'] === 'collection') {
                // Find the latest regular (non-adjustment) payment
                $latestPayment = $contract->payments()
                    ->whereNull('contract_adjustment_id')
                    ->latest('payment_date')
                    ->latest('id')
                    ->first();

                if ($latestPayment) {
                    $latestPayment->update([
                        'next_payment_date' => $nextDate
                    ]);
                } else {
                    // Create a placeholder payment with 0 amount to hold the next_payment_date
                    $contract->payments()->create([
                        'amount' => 0,
                        'payment_date' => now()->format('Y-m-d'),
                        'payment_method' => 'Cash',
                        'subcategory' => 'Monthly Installment',
                        'notes' => 'Next payment date scheduled',
                        'next_payment_date' => $nextDate,
                        'status' => 'not_collected',
                        'created_by' => auth()->id(),
                    ]);
                }
            } else {
                // Find the latest pending adjustment
                $latestAdjustment = $contract->adjustments()
                    ->where('pending_amount', '>', 0)
                    ->latest('adjustment_date')
                    ->latest('id')
                    ->first();

                if (!$latestAdjustment) {
                    // If there are no pending adjustments, find the latest adjustment
                    $latestAdjustment = $contract->adjustments()
                        ->latest('adjustment_date')
                        ->latest('id')
                        ->first();
                }

                if ($latestAdjustment) {
                    $latestAdjustment->update([
                        'next_payment_date' => $nextDate
                    ]);
                } else {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'next_payment_date' => ['Cannot update Personal Due date because no Personal Due entry exists for this contract.'],
                    ]);
                }
            }

            $contract->syncPaymentTracking();

            return new ContractResource($contract->load(['staff', 'payments.creator.roles', 'expenses', 'adjustments.creator.roles'])
                ->loadSum([
                    'expenses as expense_total' => function ($q) {
                        $q->where('is_recoverable', false);
                    }
                ], 'amount'));
        } catch (\Illuminate\Validation\ValidationException $ve) {
            throw $ve;
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Contract not found'
            ], 404);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Failed to update next due date: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        $entity = Contract::findOrFail($id);
        $entity->payments()->delete();
        $entity->adjustments()->delete();
        $entity->invoices()->delete();
        \App\Models\Expense::where('contract_id', $id)->update(['contract_id' => null]);
        $entity->delete();
        return response()->json(['message' => 'Deleted']);
    }
}
