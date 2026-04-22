<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Scout\Searchable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\CausesActivity;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    protected $fillable = ['imie', 'nazwisko', 'email', 'password', 'aktywny', 'last_login_at', 'klient_id'];

    protected $hidden = ['password', 'remember_token'];

    /** @use HasFactory<UserFactory> */
    use CausesActivity, HasFactory, HasRoles, LogsActivity, Notifiable, Searchable, SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['imie', 'nazwisko', 'email', 'aktywny', 'klient_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Get the indexable data array for the model.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'imie' => $this->imie,
            'nazwisko' => $this->nazwisko,
            'email' => $this->email,
            'aktywny' => $this->aktywny,
        ];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'aktywny' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function klient(): BelongsTo
    {
        return $this->belongsTo(Klient::class);
    }

    public function powiadomienia(): HasMany
    {
        return $this->hasMany(Powiadomienie::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'causer_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('aktywny', true);
    }

    public function getRoleAttribute()
    {
        return $this->roles->first();
    }
}
