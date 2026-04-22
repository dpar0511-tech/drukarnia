<?php

namespace App\Models;

use App\Enums\MessageChannel;
use App\Enums\MessageDirection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wiadomosc extends Model
{
    use HasFactory;

    protected $table = 'wiadomosci';

    protected $fillable = [
        'watek_id',
        'kierunek',
        'kanal',
        'tresc',
        'nadawca_email',
        'odbiorca_email',
        'zewnetrzny_id',
        'status_dostarczenia',
        'przeczytana',
    ];

    protected function casts(): array
    {
        return [
            'kierunek' => MessageDirection::class,
            'kanal' => MessageChannel::class,
            'przeczytana' => 'boolean',
        ];
    }

    public function watek(): BelongsTo
    {
        return $this->belongsTo(WatekKomunikacji::class, 'watek_id');
    }

    public function zalaczniki(): HasMany
    {
        return $this->hasMany(ZalacznikWiadomosci::class, 'wiadomosc_id');
    }

    public function pliki()
    {
        return $this->belongsToMany(Plik::class, 'zalaczniki_wiadomosci', 'wiadomosc_id', 'plik_id');
    }
}
