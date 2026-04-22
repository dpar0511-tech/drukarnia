<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KosztWlasny extends Model
{
    use HasFactory;

    protected $table = 'koszty_wlasne';

    protected $fillable = [
        'zamowienie_id',
        'koszt_materialow',
        'koszt_robocizny',
        'koszty_inne',
        'marza_realna',
    ];

    public function zamowienie(): BelongsTo
    {
        return $this->belongsTo(Zamowienie::class);
    }
}
