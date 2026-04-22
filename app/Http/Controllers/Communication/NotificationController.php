<?php

namespace App\Http\Controllers\Communication;

use App\Http\Controllers\Controller;
use App\Models\Powiadomienie;
use Illuminate\Http\Request;
use Inertia\Inertia;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Communication/Notifications', [
            'notifications' => $request->user()->powiadomienia()->latest()->get(),
        ]);
    }

    public function markAsRead(Powiadomienie $notification)
    {
        $notification->update(['przeczytane' => true]);

        return back();
    }

    public function markAllAsRead(Request $request)
    {
        // Use Query Builder for efficient bulk update
        $request->user()->powiadomienia()->unread()->update(['przeczytane' => true]);

        return back()->with('success', 'Wszystkie powiadomienia zostały oznaczone jako przeczytane.');
    }
}
