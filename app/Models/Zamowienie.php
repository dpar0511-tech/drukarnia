<?php

namespace App\Models;

use App\States\Order\OrderState;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\ModelStates\HasStates;

class Zamowienie extends Model
{
    use HasFactory, HasStates;

    protected $table = 'zamowienia';

    protected $fillable = [
        'numer',
        'klient_id',
        'menedzer_id',
        'status',
        'priorytet',
        'channel',
        'termin_realizacji',
        'parent_order_id',
        'source_email',
        'uwagi_wewnetrzne',
        'uwagi_klienta',
    ];

    protected function casts(): array
    {
        return [
            'termin_realizacji' => 'date',
            'status' => OrderState::class,
        ];
    }

    public function klient(): BelongsTo
    {
        return $this->belongsTo(Klient::class);
    }

    public function menedzer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'menedzer_id');
    }

    public function parentOrder(): BelongsTo
    {
        return $this->belongsTo(Zamowienie::class, 'parent_order_id');
    }

    public function childOrders(): HasMany
    {
        return $this->hasMany(Zamowienie::class, 'parent_order_id');
    }

    public function watkiKomunikacji(): HasMany
    {
        return $this->hasMany(WatekKomunikacji::class);
    }

    public function pozycje(): HasMany
    {
        return $this->hasMany(PozycjaZamowienia::class);
    }

    public function historiaStatusow(): HasMany
    {
        return $this->hasMany(HistoriaStatusu::class);
    }

    public function kalkulacjeCen(): HasMany
    {
        return $this->hasMany(KalkulacjaCen::class);
    }

    public function kosztyWlasne(): HasMany
    {
        return $this->hasMany(KosztWlasny::class);
    }

    public function zlecenieProdukcyjne()
    {
        return $this->hasOne(ZlecenieProdukcyjne::class);
    }

    public function pliki()
    {
        return $this->morphMany(PowiazaniePliku::class, 'fileable');
    }
}
