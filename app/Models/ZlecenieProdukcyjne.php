<?php

namespace App\Models;

use Database\Factories\ZlecenieProdukcyjneFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ZlecenieProdukcyjne extends Model
{
    /** @use HasFactory<ZlecenieProdukcyjneFactory> */
    use HasFactory;

    protected $table = 'zlecenia_produkcyjne';

    protected $fillable = [
        'zamowienie_id',
        'status',
        'priorytet',
        'data_planowana',
        'notatki',
    ];

    protected $casts = [
        'data_planowana' => 'date',
    ];

    public function zamowienie(): BelongsTo
    {
        return $this->belongsTo(Zamowienie::class);
    }

    public function etapy(): HasMany
    {
        return $this->hasMany(EtapProdukcji::class, 'zlecenie_id');
    }
}
