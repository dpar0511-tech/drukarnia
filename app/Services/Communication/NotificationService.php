<?php

namespace App\Services\Communication;

use App\Enums\SystemRole;
use App\Models\Powiadomienie;
use App\Models\User;
use App\Models\Wiadomosc;
use Illuminate\Support\Str;

class NotificationService
{
    /**
     * Send notification about new incoming message to relevant staff.
     */
    public function notifyNewMessage(Wiadomosc $wiadomosc): void
    {
        $klient = $wiadomosc->watek->klient;
        $title = 'Nowa wiadomość: '.($klient->imie_nazwa ?? $wiadomosc->nadawca_email);
        $excerpt = Str::limit($wiadomosc->tresc, 100);
        $link = "/inbox/{$wiadomosc->watek_id}";

        // Notify Admins and Managers
        $notifiedUsers = User::whereHas('roles', function ($query) {
            $query->whereIn('name', [SystemRole::Admin->value, SystemRole::Menedzer->value]);
        })->get();

        foreach ($notifiedUsers as $user) {
            Powiadomienie::create([
                'user_id' => $user->id,
                'typ' => 'new_message',
                'tytul' => $title,
                'tresc' => $excerpt,
                'link' => $link,
                'przeczytane' => false,
            ]);
        }

        // Note: Broadcast is already handled in the job via MessageReceived event,
        // which triggers the real-time update in the frontend.
    }
}
