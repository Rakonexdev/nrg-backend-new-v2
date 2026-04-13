<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Settlement extends Model
{
    protected $fillable = [
        'collector_id', 'settlement_date', 'total_collected', 'total_settled', 
        'status', 'confirmed_by', 'notes'
    ];

    protected $casts = [
        'settlement_date' => 'date',
    ];

    public function collector() {
        return $this->belongsTo(User::class, 'collector_id');
    }

    public function confirmer() {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function collections() {
        return $this->belongsToMany(Collection::class);
    }
}