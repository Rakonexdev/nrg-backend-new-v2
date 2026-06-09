<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OfficialFormat extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_name',
        'expiry_date',
        'file_path',
        'file_name',
        'uploaded_by',
    ];

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
