<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EtapProdukcji extends Model
{
    use HasFactory;

    protected $table = 'etapy_produkcji';

    protected $fillable = [
        'zlecenie_id',
        'typ',
        'kolejnosc',
        'status',
        'maszyna_id',
        'operator_id',
        'czas_start',
        'czas_stop',
        'czas_normatywny_min',
        'notatki_operatora',
    ];

    protected $casts = [
        'czas_start' => 'datetime',
        'czas_stop' => 'datetime',
        'kolejnosc' => 'integer',
        'czas_normatywny_min' => 'integer',
    ];

    public function zlecenie(): BelongsTo
    {
        return $this->belongsTo(ZlecenieProdukcyjne::class, 'zlecenie_id');
    }

    public function maszyna(): BelongsTo
    {
        return $this->belongsTo(Maszyna::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }
}
