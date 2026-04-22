<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KalkulacjaCen extends Model
{
    use HasFactory;

    protected $table = 'kalkulacje_cen';

    protected $fillable = [
        'zamowienie_id',
        'pozycja_id',
        'cena_bazowa',
        'modyfikatory',
        'rabat_kwota',
        'cena_netto',
        'stawka_vat',
        'vat_kwota',
        'cena_brutto',
        'zaakceptowana_przez_id',
        'zaakceptowana_at',
    ];

    protected $casts = [
        'modyfikatory' => 'array',
        'zaakceptowana_at' => 'datetime',
    ];

    public function zamowienie(): BelongsTo
    {
        return $this->belongsTo(Zamowienie::class);
    }

    public function pozycja(): BelongsTo
    {
        return $this->belongsTo(PozycjaZamowienia::class, 'pozycja_id');
    }

    public function zaakceptowanaPrzez(): BelongsTo
    {
        return $this->belongsTo(User::class, 'zaakceptowana_przez_id');
    }
}
