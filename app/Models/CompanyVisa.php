<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanyVisa extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'profession',
        'available_slots',
        'vp_number',
        'vp_expiry_date',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    protected $appends = ['used_slots'];

    public function getUsedSlotsAttribute()
    {
        return \App\Models\VisaApplication::where('company_id', $this->company_id)
            ->where('position', $this->profession)
            ->count();
    }
}
