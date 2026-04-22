<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IndywidualnyCennik extends Model
{
    use HasFactory;

    protected $table = 'indywidualne_cenniki';

    protected $fillable = [
        'klient_id',
        'produkt_kod',
        'cena_specjalna',
    ];

    public function klient(): BelongsTo
    {
        return $this->belongsTo(Klient::class);
    }
}
