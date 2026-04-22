<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Maszyna extends Model
{
    use HasFactory;

    protected $table = 'maszyny';

    protected $fillable = [
        'nazwa',
        'typ',
        'status',
        'koszt_godziny',
        'parametry_techniczne',
    ];

    protected $casts = [
        'parametry_techniczne' => 'array',
    ];

    public function etapyProdukcji(): HasMany
    {
        return $this->hasMany(EtapProdukcji::class, 'maszyna_id');
    }
}
