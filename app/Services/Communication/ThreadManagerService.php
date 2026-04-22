<?php

namespace App\Services\Communication;

use App\Models\Klient;
use App\Models\WatekKomunikacji;
use App\Models\Wiadomosc;
use App\Models\Zamowienie;

class ThreadManagerService
{
    /**
     * Find an existing communication thread or create a new one.
     */
    public function findOrCreateThread(
        Klient $klient,
        string $subject,
        ?string $orderNumber = null,
        ?string $inReplyTo = null
    ): WatekKomunikacji {
        // 1. Try to find by In-Reply-To header
        if ($inReplyTo) {
            $originalMessage = Wiadomosc::where('zewnetrzny_id', $inReplyTo)->first();
            if ($originalMessage && $originalMessage->watek) {
                return $originalMessage->watek;
            }
        }

        // 2. Try to find by order number if provided
        if ($orderNumber) {
            $order = Zamowienie::where('numer', $orderNumber)->first();
            if ($order) {
                $thread = WatekKomunikacji::where('zamowienie_id', $order->id)->first();
                if ($thread) {
                    return $thread;
                }

                // If order exists but no thread, create one for this order
                return WatekKomunikacji::create([
                    'zamowienie_id' => $order->id,
                    'klient_id' => $klient->id,
                    'temat' => 'Zamówienie: '.$order->numer.' - '.$subject,
                    'status' => 'nowy',
                ]);
            }
        }

        // 3. Try to find existing open thread for this client with same subject
        $thread = WatekKomunikacji::where('klient_id', $klient->id)
            ->where('temat', $subject)
            ->where('status', '!=', 'zamkniety')
            ->latest()
            ->first();

        if ($thread) {
            return $thread;
        }

        // 4. Create new thread
        return WatekKomunikacji::create([
            'klient_id' => $klient->id,
            'temat' => $subject,
            'status' => 'nowy',
        ]);
    }
}
