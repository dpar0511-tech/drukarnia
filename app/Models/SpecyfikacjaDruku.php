<?php

namespace App\Models;

use Database\Factories\SpecyfikacjaDrukuFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpecyfikacjaDruku extends Model
{
    /** @use HasFactory<SpecyfikacjaDrukuFactory> */
    use HasFactory;

    protected $table = 'specyfikacje_druku';

    protected $fillable = [
        'pozycja_id',
        'parametry',
    ];

    protected $casts = [
        'parametry' => 'array',
    ];

    public function pozycja(): BelongsTo
    {
        return $this->belongsTo(PozycjaZamowienia::class, 'pozycja_id');
    }
}
