<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BankDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'bank_details_for',
        'company_id',
        'person_name',
        'mobile_number',
        'bank_name',
        'account_number',
        'balance',
        'card_type',
        'updated_date'
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
