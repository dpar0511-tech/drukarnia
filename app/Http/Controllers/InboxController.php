<?php

namespace App\Http\Controllers;

use App\Models\WatekKomunikacji;
use Inertia\Inertia;

class InboxController extends Controller
{
    public function index()
    {
        $threads = WatekKomunikacji::with(['klient', 'wiadomosci' => function ($q) {
            $q->latest()->limit(1);
        }])
            ->withCount(['wiadomosci as unread_count' => function ($q) {
                $q->where('przeczytana', false);
            }])
            ->orderBy('updated_at', 'desc')
            ->paginate(20);

        return Inertia::render('Inbox/Index', [
            'threads' => $threads,
        ]);
    }

    public function show(WatekKomunikacji $watek)
    {
        $watek->load(['klient', 'wiadomosci.zalaczniki.plik']);

        // Mark all as read
        $watek->wiadomosci()->where('przeczytana', false)->update(['przeczytana' => true]);

        return Inertia::render('Inbox/Show', [
            'thread' => $watek,
        ]);
    }
}
