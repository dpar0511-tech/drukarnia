<?php

namespace App\Models;

use Database\Factories\StatusDefinitionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StatusDefinition extends Model
{
    /** @use HasFactory<StatusDefinitionFactory> */
    use HasFactory;

    protected $fillable = [
        'kod',
        'nazwa_pl',
        'nazwa_en',
        'kolor',
        'ikona',
        'modul',
        'kolejnosc',
        'aktywny',
    ];

    protected $casts = [
        'aktywny' => 'boolean',
        'kolejnosc' => 'integer',
    ];
}
