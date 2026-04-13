<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contract extends Model
{
    protected $fillable = [
        'staff_id', 'company_id', 'joining_date', 'contract_start', 
        'contract_end', 'monthly_salary', 'status'
    ];

    protected $casts = [
        'joining_date' => 'date',
        'contract_start' => 'date',
        'contract_end' => 'date',
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