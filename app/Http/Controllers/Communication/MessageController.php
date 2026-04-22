<?php

namespace App\Http\Controllers\Communication;

use App\Http\Controllers\Controller;
use App\Models\SzablonWiadomosci;
use App\Models\WatekKomunikacji;
use App\Services\Communication\MailSender;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Throwable;

class MessageController extends Controller
{
    public function __construct(
        protected MailSender $mailSender
    ) {}

    /**
     * Generate HTML preview of a message based on template and context.
     */
    public function preview(Request $request)
    {
        $request->validate([
            'content' => 'required|string',
            'watek_id' => 'required|exists:watki_komunikacji,id',
        ]);

        $watek = WatekKomunikacji::with(['klient', 'zamowienie'])->findOrFail($request->watek_id);

        try {
            $html = $this->mailSender->preview($request->content, [
                'klient' => $watek->klient,
                'zamowienie' => $watek->zamowienie,
            ]);

            return Response::json(['html' => $html]);
        } catch (Throwable $e) {
            return Response::json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Get available attachments for a thread (from order).
     */
    public function availableAttachments(WatekKomunikacji $watek)
    {
        $watek->load(['zamowienie.pliki']);

        $pliki = $watek->zamowienie?->pliki ?? collect();

        return Response::json($pliki);
    }

    /**
     * Send a composed message.
     */
    public function send(Request $request)
    {
        $validated = $request->validate([
            'watek_id' => 'required|exists:watki_komunikacji,id',
            'szablon_id' => 'nullable|exists:szablony_wiadomosci,id',
            'temat' => 'required|string',
            'tresc' => 'required|string',
            'recipient' => 'required|email',
            'attachments' => 'nullable|array',
            'attachments.*' => 'exists:pliki,id',
        ]);

        $watek = WatekKomunikacji::findOrFail($validated['watek_id']);
        $szablonId = $validated['szablon_id'] ?? null;
        $szablon = $szablonId ? SzablonWiadomosci::find($szablonId) : null;

        try {
            $this->mailSender->composeAndSend($watek, $szablon, [
                'tresc' => $validated['tresc'],
                'temat' => $validated['temat'],
                'recipient' => $validated['recipient'],
                'attachments' => $validated['attachments'] ?? [],
            ]);

            return back()->with('success', 'Wiadomość została zakolejkowana do wysyłki.');
        } catch (Throwable $e) {
            return back()->withErrors(['error' => 'Błąd podczas wysyłania: '.$e->getMessage()]);
        }
    }
}
