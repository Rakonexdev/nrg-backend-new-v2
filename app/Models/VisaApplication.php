<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VisaApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'serial_no',
        'vp_expiry_date',
        'vp_number',
        'position',
        'nationality',
        'company_id',
        'full_name',
        'passport_number',
        'visa_number',
        'description',
        'appointment_date',
        'contract_person',
        'medical_report',
        'attestation_details',
        'payment_date',
        'total_amount',
        'total_pay',
        'due_amount',
        'is_active',
    ];

    protected $casts = [
        'vp_expiry_date' => 'date',
        'appointment_date' => 'datetime',
        'payment_date' => 'datetime',
        'total_amount' => 'decimal:2',
        'total_pay' => 'decimal:2',
        'due_amount' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
