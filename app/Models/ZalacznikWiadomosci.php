<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZalacznikWiadomosci extends Model
{
    protected $table = 'zalaczniki_wiadomosci';

    protected $fillable = [
        'wiadomosc_id',
        'plik_id',
        'nazwa_oryginalna',
    ];

    public function wiadomosc(): BelongsTo
    {
        return $this->belongsTo(Wiadomosc::class);
    }

    public function plik(): BelongsTo
    {
        return $this->belongsTo(Plik::class);
    }
}
