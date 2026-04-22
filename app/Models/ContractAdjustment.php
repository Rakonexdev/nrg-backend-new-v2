<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContractAdjustment extends Model
{
    protected $fillable = [
        'contract_id', 'amount', 'reason', 'adjustment_date', 'created_by'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'adjustment_date' => 'date',
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
