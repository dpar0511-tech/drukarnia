<?php

namespace App\Observers;

use Spatie\Activitylog\Models\Activity;

class AuditLogObserver
{
    /**
     * Handle the Activity "creating" event.
     * Automatically injects IP address and User Agent into activity properties.
     */
    public function creating(Activity $activity): void
    {
        $request = request();

        $activity->properties = $activity->properties->merge([
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
