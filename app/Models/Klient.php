<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

class Klient extends Model
{
    protected $table = 'klienci';

    protected $fillable = [
        'typ',
        'imie_nazwa',
        'nip',
        'regon',
        'email_glowny',
        'telefon_glowny',
        'adres_ulica',
        'adres_miasto',
        'adres_kod',
        'adres_kraj',
        'status',
        'poziom_lojalnosci_id',
    ];

    /** @use HasFactory<KlientFactory> */
    use HasFactory, Searchable, SoftDeletes;

    /**
     * Get the indexable data array for the model.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'imie_nazwa' => $this->imie_nazwa,
            'nip' => $this->nip,
            'email_glowny' => $this->email_glowny,
            'telefon_glowny' => $this->telefon_glowny,
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function zamowienia(): HasMany
    {
        return $this->hasMany(Zamowienie::class);
    }

    public function watkiKomunikacji(): HasMany
    {
        return $this->hasMany(WatekKomunikacji::class);
    }

    public function osobyKontaktowe(): HasMany
    {
        return $this->hasMany(OsobaKontaktowa::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'klient_tag');
    }

    public function poziomLojalnosci(): BelongsTo
    {
        return $this->belongsTo(PoziomLojalnosci::class, 'poziom_lojalnosci_id');
    }
}
