<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WersjaPliku extends Model
{
    protected $table = 'wersje_plikow';

    protected $fillable = [
        'plik_id',
        'numer_wersji',
        'sciezka_s3',
        'komentarz',
        'uploadowany_przez_id',
        'aktywna',
    ];

    protected function casts(): array
    {
        return [
            'aktywna' => 'boolean',
        ];
    }

    public function plik(): BelongsTo
    {
        return $this->belongsTo(Plik::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploadowany_przez_id');
    }
}
