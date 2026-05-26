<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContractPayment extends Model
{
    protected $fillable = [
        'contract_id', 'amount', 'status', 'payment_date', 'payment_method', 'notes', 'created_by',
        'is_settled', 'settled_at', 'settlement_id', 'subcategory', 'next_payment_date',
        'contract_adjustment_id'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'datetime',
        'next_payment_date' => 'date',
        'is_settled' => 'boolean',
        'settled_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($payment) {
            if ($payment->payment_date) {
                if (is_string($payment->payment_date)) {
                    if (strlen($payment->payment_date) === 10) {
                        $payment->payment_date = \Illuminate\Support\Carbon::parse($payment->payment_date . ' ' . now()->format('H:i:s'));
                    }
                } elseif ($payment->payment_date instanceof \Carbon\Carbon || $payment->payment_date instanceof \DateTimeInterface) {
                    $paymentDate = \Illuminate\Support\Carbon::instance($payment->payment_date);
                    if ($paymentDate->hour === 0 && $paymentDate->minute === 0 && $paymentDate->second === 0) {
                        $paymentDate->setTime(now()->hour, now()->minute, now()->second);
                    }
                    $payment->payment_date = $paymentDate;
                }
            }
        });

        static::updating(function ($payment) {
            if ($payment->isDirty('payment_date') && $payment->payment_date) {
                if (is_string($payment->payment_date)) {
                    if (strlen($payment->payment_date) === 10) {
                        $payment->payment_date = \Illuminate\Support\Carbon::parse($payment->payment_date . ' ' . now()->format('H:i:s'));
                    }
                } elseif ($payment->payment_date instanceof \Carbon\Carbon || $payment->payment_date instanceof \DateTimeInterface) {
                    $paymentDate = \Illuminate\Support\Carbon::instance($payment->payment_date);
                    if ($paymentDate->hour === 0 && $paymentDate->minute === 0 && $paymentDate->second === 0) {
                        $paymentDate->setTime(now()->hour, now()->minute, now()->second);
                    }
                    $payment->payment_date = $paymentDate;
                }
            }
        });
    }

    public function settlement()
    {
        return $this->belongsTo(Settlement::class);
    }

    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }

    public function adjustment()
    {
        return $this->belongsTo(ContractAdjustment::class, 'contract_adjustment_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
