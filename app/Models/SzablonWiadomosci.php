<?php

namespace App\Models;

use App\Enums\MessageChannel;
use Illuminate\Database\Eloquent\Model;

class SzablonWiadomosci extends Model
{
    protected $table = 'szablony_wiadomosci';

    protected $fillable = [
        'nazwa',
        'kanal',
        'temat',
        'tresc_template',
        'zmienne',
    ];

    protected function casts(): array
    {
        return [
            'kanal' => MessageChannel::class,
            'zmienne' => 'array',
        ];
    }
}
