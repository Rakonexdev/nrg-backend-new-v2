<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contract extends Model
{
    protected $fillable = [
        'staff_id',
        'start_date',
        'end_date',
        'total_income',
        'payment_type',
        'qid_renewal_fee',
        'qid_next_renewal_date',
        'passport_renewal_fee',
        'profession_change_fee',
        'sponsorship_change_fee',
        'health_card_fee',
        'others_fee',
        'others_reason',
        'contract_date'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'contract_date' => 'date',
        'qid_next_renewal_date' => 'date',
        'total_income' => 'decimal:2',

        'paid_amount' => 'decimal:2',
        'pending_amount' => 'decimal:2',
        'qid_renewal_fee' => 'decimal:2',
        'passport_renewal_fee' => 'decimal:2',
        'profession_change_fee' => 'decimal:2',
        'sponsorship_change_fee' => 'decimal:2',
        'health_card_fee' => 'decimal:2',
        'others_fee' => 'decimal:2',
    ];

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }

    public function payments()
    {
        return $this->hasMany(ContractPayment::class)->latest('payment_date')->latest();
    }

    public function adjustments()
    {
        return $this->hasMany(ContractAdjustment::class)->latest('adjustment_date')->latest();
    }

    public function getNetIncomeAttribute()
    {
        return round((float) $this->total_income + (float) $this->adjustments()->sum('amount'), 2);
    }

    public function syncPaymentTracking(): void
    {
        $this->refresh();

        $initialTotalIncome = round((float) $this->total_income, 2);

        $paidAmount = round((float) $this->payments()->sum('amount'), 2);

        // Pending is strictly the contract value minus regular payments
        // Additional amounts (adjustments) are tracked separately and do not affect this balance
        $pendingAmount = round(max($initialTotalIncome - $paidAmount, 0), 2);

        $this->forceFill([
            'paid_amount' => $paidAmount,
            'pending_amount' => $pendingAmount,
            'payment_status' => $this->resolvePaymentStatus($initialTotalIncome, $paidAmount, $pendingAmount),
        ])->save();
    }

    private function resolvePaymentStatus(float $contractValue, float $paidAmount, float $pendingAmount): string
    {
        if ($paidAmount <= 0) {
            return 'Payment Not Initialized';
        }

        if ($contractValue > 0 && $paidAmount >= $contractValue) {
            return 'Fully Paid';
        }

        return 'Partially Paid';
    }
}
