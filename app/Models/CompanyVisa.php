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
        'nationality',
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
        $query = \App\Models\VisaApplication::where('company_id', $this->company_id)
            ->where('position', $this->profession)
            ->where('vp_number', $this->vp_number);

        if ($this->nationality) {
            $query->where('nationality', $this->nationality);
        }

        return $query->count();
    }
}
