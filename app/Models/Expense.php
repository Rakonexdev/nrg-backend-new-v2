<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    protected $fillable = [
        'expense_date', 'category_id', 'staff_id', 'company_id', 
        'amount', 'description', 'recorded_by'
    ];

    protected $casts = [
        'expense_date' => 'date',
    ];

    public function category() {
        return $this->belongsTo(ExpenseCategory::class, 'category_id');
    }

    public function staff() {
        return $this->belongsTo(Staff::class);
    }

    public function company() {
        return $this->belongsTo(Company::class);
    }

    public function recorder() {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}