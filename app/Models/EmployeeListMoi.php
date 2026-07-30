<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeListMoi extends Model
{
    use HasFactory;

    protected $table = 'employee_list_mois';

    protected $fillable = [
        'document_name',
        'computer_card_number',
        'company_name',
        'company_id',
        'upload_date',
        'salary_month',
        'file_path',
        'file_name',
        'file_type',
        'file_size',
        'uploaded_by',
    ];

    protected $casts = [
        'upload_date' => 'date:Y-m-d',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
