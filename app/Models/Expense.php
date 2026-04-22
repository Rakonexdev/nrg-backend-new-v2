<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    protected $fillable = [
        'expense_date', 'category_id', 'subcategory_id', 'staff_id', 'contract_id',
        'amount', 'payment_method', 'vendor_name', 'description', 'recorded_by'
    ];

    protected $casts = [
        'expense_date' => 'date',
    ];

    public function category() {
        return $this->belongsTo(ExpenseCategory::class, 'category_id');
    }

    public function subcategory() {
        return $this->belongsTo(ExpenseCategory::class, 'subcategory_id');
    }

    public function staff() {
        return $this->belongsTo(Staff::class);
    }



    public function contract() {
        return $this->belongsTo(Contract::class);
    }

    public function recorder() {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
