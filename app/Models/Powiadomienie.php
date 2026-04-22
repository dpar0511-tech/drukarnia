<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Powiadomienie extends Model
{
    protected $table = 'powiadomienia';

    protected $fillable = [
        'user_id',
        'typ',
        'tytul',
        'tresc',
        'link',
        'przeczytane',
    ];

    protected function casts(): array
    {
        return [
            'przeczytane' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->where('przeczytane', false);
    }
}
