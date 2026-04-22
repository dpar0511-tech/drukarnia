<?php

namespace App\Models;

use Database\Factories\TemporaryUploadFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemporaryUpload extends Model
{
    /** @use HasFactory<TemporaryUploadFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'filename',
        'filepath',
        'tus_id',
        'size',
        'mime_type',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'size' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
