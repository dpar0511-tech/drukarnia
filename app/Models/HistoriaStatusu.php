<?php

namespace App\Models;

use Database\Factories\HistoriaStatusuFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistoriaStatusu extends Model
{
    /** @use HasFactory<HistoriaStatusuFactory> */
    use HasFactory;

    protected $table = 'historia_statusow';

    protected $fillable = [
        'zamowienie_id',
        'status_from',
        'status_to',
        'user_id',
        'komentarz',
    ];

    public function zamowienie(): BelongsTo
    {
        return $this->belongsTo(Zamowienie::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
