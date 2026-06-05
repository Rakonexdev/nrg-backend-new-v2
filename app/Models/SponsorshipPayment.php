<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SponsorshipPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'sponsorship_change_id',
        'amount',
        'payment_date',
        'next_payment_date',
        'notes',
        'method',
        'user_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
        'next_payment_date' => 'date',
    ];

    public function sponsorshipChange()
    {
        return $this->belongsTo(SponsorshipChange::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
