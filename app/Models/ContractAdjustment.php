<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContractAdjustment extends Model
{
    protected $fillable = [
        'contract_id', 'amount', 'reason', 'adjustment_date', 'created_by',
        'paid_amount', 'pending_amount', 'next_payment_date'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'pending_amount' => 'decimal:2',
        'adjustment_date' => 'date',
        'next_payment_date' => 'date',
    ];

    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }

    public function payments()
    {
        return $this->hasMany(ContractPayment::class, 'contract_adjustment_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
