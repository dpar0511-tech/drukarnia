<?php

namespace App\Models;

use Database\Factories\OperatorProdukcjiFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OperatorProdukcji extends Model
{
    /** @use HasFactory<OperatorProdukcjiFactory> */
    use HasFactory;

    protected $table = 'operatorzy_produkcji';

    protected $fillable = [
        'user_id',
        'specjalizacja',
        'dostepny',
    ];

    protected $casts = [
        'specjalizacja' => 'array',
        'dostepny' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function etapyProdukcji(): HasMany
    {
        return $this->hasMany(EtapProdukcji::class, 'operator_id', 'user_id');
    }
}
