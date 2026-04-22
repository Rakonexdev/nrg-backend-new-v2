<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Collection extends Model
{
    protected $fillable = [
        'invoice_id', 'company_id', 'collector_id', 'collected_amount', 
        'payment_channel', 'next_due_date', 'collection_date', 'notes', 
        'is_settled', 'settled_at'
    ];

    protected $casts = [
        'next_due_date' => 'date',
        'collection_date' => 'date',
        'is_settled' => 'boolean',
        'settled_at' => 'datetime',
    ];

    public function invoice() {
        return $this->belongsTo(Invoice::class);
    }

    public function company() {
        // Obsolete: Company model merged into Staff
        return null;
    }

    public function collector() {
        return $this->belongsTo(User::class, 'collector_id');
    }

    public function settlements() {
        return $this->belongsToMany(Settlement::class);
    }
}