<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanyBranch extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'location',
        'contact_person',
        'contact_number',
        'is_active'
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function staff()
    {
        return $this->hasMany(Staff::class, 'branch_id');
    }
}
