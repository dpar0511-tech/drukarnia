<?php

namespace App\Models;

use Database\Factories\RegulaCenowaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RegulaCenowa extends Model
{
    /** @use HasFactory<RegulaCenowaFactory> */
    use HasFactory;

    protected $table = 'reguly_cenowe';

    protected $fillable = [
        'nazwa',
        'typ',
        'warunki',
        'wartosc',
        'aktywna',
    ];

    protected $casts = [
        'warunki' => 'array',
        'aktywna' => 'boolean',
    ];
}
