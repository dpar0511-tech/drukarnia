<?php

namespace App\Models;

use App\Enums\CommunicationThreadStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WatekKomunikacji extends Model
{
    use HasFactory;

    protected $table = 'watki_komunikacji';

    protected $fillable = [
        'zamowienie_id',
        'klient_id',
        'temat',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => CommunicationThreadStatus::class,
        ];
    }

    public function zamowienie(): BelongsTo
    {
        return $this->belongsTo(Zamowienie::class);
    }

    public function klient(): BelongsTo
    {
        return $this->belongsTo(Klient::class);
    }

    public function wiadomosci(): HasMany
    {
        return $this->hasMany(Wiadomosc::class, 'watek_id');
    }
}
