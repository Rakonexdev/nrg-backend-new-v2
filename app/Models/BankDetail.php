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
        'qid',
        'mobile_number',
        'bank_name',
        'account_number',
        'balance',
        'card_type',
        'card_number',
        'card_expiry_date',
        'updated_date',
        'document'
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
