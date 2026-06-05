<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SponsorshipChange extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'qid_expiry_date' => 'date',
        'submitted_date' => 'date',
        'approval_date' => 'date',
        'approval_expiry' => 'date',
        'total_contract_amount' => 'decimal:2',
        'pay_amount' => 'decimal:2',
        'due_amount' => 'decimal:2',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class, 'new_company_id');
    }

    public function payments()
    {
        return $this->hasMany(SponsorshipPayment::class);
    }
}
