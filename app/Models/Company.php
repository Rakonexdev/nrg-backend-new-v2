<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    protected $fillable = [
        'name', 'contact_person_name', 'contact_person_phone', 'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function contracts() {
        return $this->hasMany(Contract::class);
    }

    public function invoices() {
        return $this->hasMany(Invoice::class);
    }

    public function collections() {
        return $this->hasMany(Collection::class);
    }

    public function expenses() {
        return $this->hasMany(Expense::class);
    }
}