<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;

class AuditLog extends Activity
{
    /**
     * Relationship to the user who caused the activity.
     * Alias for 'causer' to support existing code.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'causer_id');
    }

    /**
     * Get the IP address from properties.
     */
    public function getIpAddressAttribute(): ?string
    {
        return $this->getExtraProperty('ip_address');
    }

    /**
     * Get the User Agent from properties.
     */
    public function getUserAgentAttribute(): ?string
    {
        return $this->getExtraProperty('user_agent');
    }

    /**
     * Get the changes from properties.
     */
    public function getChangesAttribute(): Collection
    {
        return collect($this->getExtraProperty('attributes') ?? []);
    }
}
