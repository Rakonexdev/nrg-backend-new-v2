<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    use HasFactory;

    protected $fillable = [
        'plate_number',
        'vehicle_name',
        'description',
        'chassis_no',
        'reg_expiry_date',
        'company_id',
        'driver_qid',
        'driver_name',
        'driver_phone',
        'driver_alt_phone',
        'handover_datetime',
        'return_datetime',
        'is_active',
    ];

    protected $casts = [
        'reg_expiry_date' => 'date',
        'handover_datetime' => 'datetime',
        'return_datetime' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
