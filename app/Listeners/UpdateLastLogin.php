<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;

class UpdateLastLogin
{
    /**
     * Handle the event.
     */
    public function handle(Login $event): void
    {
        $event->user->update([
            'last_login_at' => now(),
        ]);

        activity()
            ->performedOn($event->user)
            ->causedBy($event->user)
            ->withProperty('ip_address', request()->ip())
            ->withProperty('user_agent', request()->userAgent())
            ->event('login')
            ->log('User logged in');
    }
}
