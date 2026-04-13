<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffDocument extends Model
{
    protected $fillable = [
        'staff_id', 'document_type', 'file_path', 'file_name', 
        'uploaded_at', 'uploaded_by'
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
    ];

    public function staff() {
        return $this->belongsTo(Staff::class);
    }

    public function uploader() {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}