<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Logout;

class LogLogout
{
    /**
     * Handle the event.
     */
    public function handle(Logout $event): void
    {
        if ($event->user) {
            activity()
                ->performedOn($event->user)
                ->causedBy($event->user)
                ->withProperty('ip_address', request()->ip())
                ->withProperty('user_agent', request()->userAgent())
                ->event('logout')
                ->log('User logged out');
        }
    }
}
