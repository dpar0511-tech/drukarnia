<?php

namespace App\Models;

use Database\Factories\RabatDefinicjaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RabatDefinicja extends Model
{
    /** @use HasFactory<RabatDefinicjaFactory> */
    use HasFactory;

    protected $table = 'rabaty_definicje';

    protected $fillable = [
        'nazwa',
        'kod_rabatowy',
        'typ',
        'wartosc',
        'minimalna_kwota_zamowienia',
        'wygasa_at',
        'aktywny',
    ];

    protected $casts = [
        'wygasa_at' => 'datetime',
        'aktywny' => 'boolean',
    ];
}
