<?php

namespace App\Http\Controllers;

use App\Enums\SystemRole;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    /**
     * Handle the intelligent redirection based on user role.
     */
    public function __invoke(Request $request)
    {
        $user = $request->user();

        if ($user->hasRole(SystemRole::Klient->value)) {
            return redirect()->intended('/portal');
        }

        if ($user->hasRole(SystemRole::Operator->value)) {
            return redirect()->intended('/kanban');
        }

        // Admins, Managers and others go to the main dashboard
        return Inertia::render('Dashboard', [
            'version' => app()->version(),
            'stats' => Inertia::defer(fn () => [
                'active_orders' => 12,
                'unread_emails' => 4,
                'production_progress' => 34,
            ]),
        ]);
    }
}
