<?php

namespace App\Services\Communication;

use App\Enums\MessageChannel;
use App\Enums\MessageDirection;
use App\Jobs\SendOutboundEmail;
use App\Models\Plik;
use App\Models\SzablonWiadomosci;
use App\Models\WatekKomunikacji;
use App\Models\Wiadomosc;
use Illuminate\Support\Facades\DB;
use Throwable;

class MailSender
{
    public function __construct(
        protected TemplateParser $parser
    ) {}

    /**
     * Compose and queue an outbound email.
     *
     * @param  array  $data  {tresc: string, temat: string, recipient: string, attachments: array}
     *
     * @throws Throwable
     */
    public function composeAndSend(WatekKomunikacji $watek, ?SzablonWiadomosci $szablon = null, array $data = []): Wiadomosc
    {
        // Load context
        $watek->loadMissing(['klient', 'zamowienie']);

        return DB::transaction(function () use ($watek, $szablon, $data) {
            $context = [
                'klient' => $watek->klient,
                'zamowienie' => $watek->zamowienie,
                'watek' => $watek,
            ];

            // Render subject and content
            $rawTresc = $data['tresc'] ?? ($szablon ? $szablon->tresc_html : '');
            $rawTemat = $data['temat'] ?? ($szablon ? $szablon->temat : '');

            $tresc = $this->parser->render($rawTresc, $context);
            $temat = $this->parser->render($rawTemat, $context);

            $recipient = $data['recipient'] ?? $watek->klient?->email;

            // 1. Create the message record
            $wiadomosc = Wiadomosc::create([
                'watek_id' => $watek->id,
                'kierunek' => MessageDirection::Wychodzacy,
                'kanal' => MessageChannel::Email,
                'tresc' => $tresc,
                'nadawca_email' => config('mail.from.address'),
                'odbiorca_email' => $recipient,
                'status_dostarczenia' => 'pending',
                'przeczytana' => true,
            ]);

            // 2. Handle attachments if any
            if (! empty($data['attachments'])) {
                foreach ($data['attachments'] as $plikId) {
                    $wiadomosc->zalaczniki()->create([
                        'plik_id' => $plikId,
                        'nazwa_oryginalna' => Plik::find($plikId)?->nazwa_oryginalna ?? 'attachment',
                    ]);
                }
            }

            // 3. Dispatch the Job
            SendOutboundEmail::dispatch($wiadomosc, $temat);

            return $wiadomosc;
        });
    }

    /**
     * Preview a template without sending.
     */
    public function preview(string $templateContent, array $context): string
    {
        return $this->parser->render($templateContent, $context);
    }
}
