<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contract extends Model
{
    protected $fillable = [
        'staff_id', 'company_id', 'start_date', 'end_date', 
        'contract_value', 'payment_type'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'contract_value' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'pending_amount' => 'decimal:2',
    ];

    public function staff() {
        return $this->belongsTo(Staff::class);
    }

    public function company() {
        return $this->belongsTo(Company::class);
    }

    public function invoices() {
        return $this->hasMany(Invoice::class);
    }

    public function expenses() {
        return $this->hasMany(Expense::class);
    }

    public function payments() {
        return $this->hasMany(ContractPayment::class)->latest('payment_date')->latest();
    }

    public function syncPaymentTracking(): void
    {
        $contractValue = round((float) $this->contract_value, 2);
        $paidAmount = round((float) $this->payments()->sum('amount'), 2);
        $pendingAmount = round(max($contractValue - $paidAmount, 0), 2);

        $this->forceFill([
            'paid_amount' => $paidAmount,
            'pending_amount' => $pendingAmount,
            'payment_status' => $this->resolvePaymentStatus($contractValue, $paidAmount),
        ])->save();
    }

    private function resolvePaymentStatus(float $contractValue, float $paidAmount): string
    {
        if ($paidAmount <= 0) {
            return 'Pending';
        }

        if ($contractValue > 0 && $paidAmount >= $contractValue) {
            return 'Fully Paid';
        }

        return 'Partially Paid';
    }
}
