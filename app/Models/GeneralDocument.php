<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class GeneralDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_name',
        'category',
        'expiry_date',
        'file_path',
        'file_name',
        'uploaded_by'
    ];

    protected $casts = [
        'expiry_date' => 'date',
    ];

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
