<?php

namespace App\Services\Communication;

use App\Models\Klient;
use App\Models\OsobaKontaktowa;
use App\Models\Zamowienie;

class ClientMatcherService
{
    /**
     * Match a client based on email address and subject line.
     * If no client is found, a draft client record is created.
     */
    public function findOrCreateClient(string $email, ?string $subject = null): Klient
    {
        // 1. Try to find by direct email in klienci
        $klient = Klient::where('email_glowny', $email)->first();
        if ($klient) {
            return $klient;
        }

        // 2. Try to find by email in osoby_kontaktowe
        $osoba = OsobaKontaktowa::where('email', $email)->first();
        if ($osoba && $osoba->klient) {
            return $osoba->klient;
        }

        // 3. Try to find by order number in subject
        if ($subject) {
            preg_match('/DRK-\d{4}-\d{5}/', $subject, $matches);
            if (! empty($matches)) {
                $orderNumber = $matches[0];
                $order = Zamowienie::where('numer', $orderNumber)->first();
                if ($order && $order->klient) {
                    return $order->klient;
                }
            }
        }

        // 4. Create draft client if still not found
        return Klient::create([
            'typ' => 'B2C',
            'imie_nazwa' => 'Nowy Klient ('.$email.')',
            'email_glowny' => $email,
            'status' => 'draft',
        ]);
    }
}
