<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VisaPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'visa_application_id',
        'amount',
        'payment_date',
        'notes',
        'method',
        'user_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
    ];

    public function visaApplication()
    {
        return $this->belongsTo(VisaApplication::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
