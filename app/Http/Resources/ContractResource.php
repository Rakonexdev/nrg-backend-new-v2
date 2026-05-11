<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContractResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $adjustmentsSum = (float) $this->adjustments()->sum('amount');
        $recoverableExpenseTotal = (float) $this->expenses()->where('is_recoverable', true)->sum('amount');
        $nonRecoverableExpenseTotal = (float) ($this->expense_total ?? 0); // This comes from withSum in controller

        $operationalFees = (float) (
            $this->qid_renewal_fee +
            $this->passport_renewal_fee +
            $this->profession_change_fee +
            $this->sponsorship_change_fee +
            $this->health_card_fee +
            $this->others_fee
        );

        $netAdjustments = $adjustmentsSum - $recoverableExpenseTotal;

        return [
            'id' => $this->id,
            'staff_id' => $this->staff_id,
            'staff' => new StaffResource($this->whenLoaded('staff')),
            'contract_date' => $this->contract_date ? $this->contract_date->format('Y-m-d') : null,
            'start_date' => $this->start_date ? $this->start_date->format('Y-m-d') : null,
            'end_date' => $this->end_date ? $this->end_date->format('Y-m-d') : null,
            'total_income' => (float) $this->total_income,
            'adjustment_total' => $netAdjustments,
            'recoverable_expense_total' => $recoverableExpenseTotal,
            'net_payable' => (float) $this->total_income,
            'paid_amount' => (float) $this->paid_amount,
            'pending_amount' => (float) $this->pending_amount,
            'qid_renewal_fee' => (float) $this->qid_renewal_fee,
            'qid_next_renewal_date' => $this->qid_next_renewal_date ? $this->qid_next_renewal_date->format('Y-m-d') : null,
            'passport_renewal_fee' => (float) $this->passport_renewal_fee,
            'profession_change_fee' => (float) $this->profession_change_fee,
            'sponsorship_change_fee' => (float) $this->sponsorship_change_fee,
            'health_card_fee' => (float) $this->health_card_fee,
            'others_fee' => (float) $this->others_fee,
            'others_reason' => $this->others_reason,
            'expense_total' => (float) ($nonRecoverableExpenseTotal + $operationalFees),
            'profit_amount' => (float) ($this->paid_amount + $netAdjustments) - (float) ($nonRecoverableExpenseTotal + $operationalFees),
            'gross_income' => (float) ($this->paid_amount + $netAdjustments),
            'employee_expenses_total' => (float) ($nonRecoverableExpenseTotal + $operationalFees),
            'payment_status' => $this->payment_status,
            'payment_type' => $this->payment_type,
            'payments' => ContractPaymentResource::collection($this->whenLoaded('payments')),
            'daily_expenses' => ExpenseResource::collection($this->whenLoaded('expenses')),
            'recoverable_expenses' => ExpenseResource::collection($this->expenses()->where('is_recoverable', true)->get()),
            'adjustments' => $this->whenLoaded('adjustments', function () {
                return $this->adjustments->map(function ($adj) {
                    return [
                        'id' => $adj->id,
                        'amount' => (float) $adj->amount,
                        'reason' => $adj->reason,
                        'adjustment_date' => $adj->adjustment_date ? $adj->adjustment_date->format('Y-m-d') : null,
                        'created_at' => $adj->created_at,
                    ];
                });
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
