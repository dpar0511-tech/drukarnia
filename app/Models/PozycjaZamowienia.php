<?php

namespace App\Models;

use Database\Factories\PozycjaZamowieniaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PozycjaZamowienia extends Model
{
    /** @use HasFactory<PozycjaZamowieniaFactory> */
    use HasFactory;

    protected $table = 'pozycje_zamowienia';

    protected $fillable = [
        'zamowienie_id',
        'nazwa',
        'naklad',
        'format',
        'material',
        'kolorystyka',
        'cena_netto',
    ];

    public function zamowienie(): BelongsTo
    {
        return $this->belongsTo(Zamowienie::class);
    }

    public function specyfikacja(): HasOne
    {
        return $this->hasOne(SpecyfikacjaDruku::class, 'pozycja_id');
    }

    public function kalkulacje(): HasMany
    {
        return $this->hasMany(KalkulacjaCen::class, 'pozycja_id');
    }
}
