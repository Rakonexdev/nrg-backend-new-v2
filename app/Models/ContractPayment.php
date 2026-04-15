<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContractPayment extends Model
{
    protected $fillable = [
        'contract_id', 'amount', 'payment_date', 'payment_method', 'notes', 'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
    ];

    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
