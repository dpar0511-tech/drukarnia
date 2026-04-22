<?php

namespace App\Http\Controllers\Communication;

use App\Http\Controllers\Controller;
use App\Models\WatekKomunikacji;
use Illuminate\Http\Request;
use Inertia\Inertia;

class InboxController extends Controller
{
    public function index()
    {
        return Inertia::render('Communication/Inbox', [
            'threads' => WatekKomunikacji::with(['klient', 'zamowienie'])
                ->withCount(['wiadomosci as unread_count' => function ($query) {
                    $query->where('przeczytana', false)->where('kierunek', 'przychodzacy');
                }])
                ->latest()
                ->get(),
        ]);
    }

    public function show(WatekKomunikacji $watek)
    {
        // Mark messages as read using Query Builder for efficiency
        $watek->wiadomosci()->where('przeczytana', false)->where('kierunek', 'przychodzacy')->update(['przeczytana' => true]);

        return Inertia::render('Communication/Inbox', [
            'threads' => WatekKomunikacji::with(['klient', 'zamowienie'])
                ->withCount(['wiadomosci as unread_count' => function ($query) {
                    $query->where('przeczytana', false)->where('kierunek', 'przychodzacy');
                }])
                ->latest()
                ->get(),
            'activeThreadData' => $watek->load(['wiadomosci' => fn ($q) => $q->oldest(), 'klient', 'zamowienie']),
        ]);
    }

    public function reply(Request $request, WatekKomunikacji $watek)
    {
        $validated = $request->validate([
            'tresc' => 'required|string',
            'kanal' => 'required|string',
        ]);

        $message = $watek->wiadomosci()->create([
            'kierunek' => 'wychodzacy',
            'kanal' => $validated['kanal'],
            'tresc' => $validated['tresc'],
            'przeczytana' => true,
        ]);

        return back()->with('success', 'Wiadomość została wysłana.');
    }
}
