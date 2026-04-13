<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Staff extends Model
{
    protected $fillable = [
        'name', 'nationality', 'profession', 'mobile', 
        'date_of_birth', 'passport_number', 'passport_expiry', 
        'qid_number', 'qid_expiry', 'status', 'joining_date'
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'passport_expiry' => 'date',
        'qid_expiry' => 'date',
        'joining_date' => 'date',
    ];

    public function documents() {
        return $this->hasMany(StaffDocument::class);
    }

    public function contracts() {
        return $this->hasMany(Contract::class);
    }

    public function expenses() {
        return $this->hasMany(Expense::class);
    }
}