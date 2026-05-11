<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Staff extends Model
{
    protected $table = 'staff';

    protected $fillable = [
        'company_id',
        'branch_id',
        'name', 'nationality', 'profession', 'mobile', 'alternative_mobile', 
        'date_of_birth', 'passport_number', 'passport_expiry', 
        'qid_number', 'qid_expiry', 'status', 'joining_date', 'notes'
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'passport_expiry' => 'date',
        'qid_expiry' => 'date',
        'joining_date' => 'date',
    ];

    public function documents(): HasMany
    {
        return $this->hasMany(StaffDocument::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(CompanyBranch::class, 'branch_id');
    }
}