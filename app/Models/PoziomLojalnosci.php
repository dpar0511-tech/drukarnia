<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PoziomLojalnosci extends Model
{
    protected $table = 'poziomy_lojalnosci';

    protected $fillable = [
        'nazwa',
        'prog_obrotow',
        'rabat_procent',
    ];

    public function klienci(): HasMany
    {
        return $this->hasMany(Klient::class, 'poziom_lojalnosci_id');
    }
}
