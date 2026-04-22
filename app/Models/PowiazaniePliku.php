<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PowiazaniePliku extends Model
{
    protected $table = 'powiazania_plikow';

    protected $fillable = [
        'plik_id',
        'fileable_type',
        'fileable_id',
        'rola',
    ];

    public function plik(): BelongsTo
    {
        return $this->belongsTo(Plik::class);
    }

    public function fileable(): MorphTo
    {
        return $this->morphTo();
    }
}
