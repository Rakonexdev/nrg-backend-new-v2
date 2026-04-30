<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $fillable = [
        'contract_id', 'company_id', 'invoice_number', 'total_amount', 
        'amount_paid', 'due_date', 'status'
    ];

    protected $casts = [
        'due_date' => 'date',
    ];

    public function contract() {
        return $this->belongsTo(Contract::class);
    }

    public function company() {
        return $this->belongsTo(Company::class);
    }

    public function collections() {
        return $this->hasMany(Collection::class);
    }
}