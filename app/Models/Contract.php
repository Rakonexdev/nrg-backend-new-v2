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
}