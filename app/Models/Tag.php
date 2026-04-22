<?php

namespace App\Models;

use Database\Factories\TagFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    /** @use HasFactory<TagFactory> */
    use HasFactory;

    protected $table = 'tagi';

    protected $fillable = [
        'nazwa',
        'slug',
        'kolor',
    ];

    public function klienci(): BelongsToMany
    {
        return $this->belongsToMany(Klient::class, 'klient_tag');
    }
}
