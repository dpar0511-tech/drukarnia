<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OsobaKontaktowa extends Model
{
    use HasFactory;

    protected $table = 'osoby_kontaktowe';

    protected $fillable = [
        'klient_id',
        'imie',
        'email',
        'telefon',
    ];

    public function klient(): BelongsTo
    {
        return $this->belongsTo(Klient::class);
    }
}
