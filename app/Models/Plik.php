<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Plik extends Model
{
    use SoftDeletes;

    protected $table = 'pliki';

    protected $fillable = [
        'nazwa_oryginalna',
        'slug',
        'mime_type',
        'rozmiar_bajtow',
        'sciezka_s3',
        'bucket',
        'thumbnail_sm_url',
        'thumbnail_md_url',
        'thumbnail_lg_url',
        'uploadowany_przez_id',
        'checksum_sha256',
    ];

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploadowany_przez_id');
    }

    public function wersje(): HasMany
    {
        return $this->hasMany(WersjaPliku::class, 'plik_id');
    }

    public function aktywnaWersja()
    {
        return $this->hasOne(WersjaPliku::class, 'plik_id')->where('aktywna', true);
    }
}
