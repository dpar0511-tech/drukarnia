<?php

namespace App\Jobs;

use App\Enums\MessageChannel;
use App\Enums\MessageDirection;
use App\Events\MessageReceived;
use App\Models\Wiadomosc;
use App\Models\ZalacznikWiadomosci;
use App\Services\Communication\ClientMatcherService;
use App\Services\Communication\EmailParserService;
use App\Services\Communication\HtmlPurifier;
use App\Services\Communication\ImapMailImporter;
use App\Services\Communication\NotificationService;
use App\Services\Communication\ThreadManagerService;
use App\Services\DAMS\FileStorageService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessIncomingEmail implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public $tries = 5;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public $backoff = [60, 300, 600];

    /**
     * Create a new job instance.
     */
    public function __construct(public array $emailData)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(
        FileStorageService $storageService,
        ImapMailImporter $imapService,
        HtmlPurifier $purifier,
        EmailParserService $parserService,
        ClientMatcherService $matcherService,
        ThreadManagerService $threadService,
        NotificationService $notificationService
    ): void {
        // 1. Deduplication
        if (Wiadomosc::where('zewnetrzny_id', $this->emailData['zewnetrzny_id'])->exists()) {
            $imapService->markAsSeen($this->emailData['uid'], $this->emailData['folder']);

            return;
        }

        try {
            DB::transaction(function () use ($storageService, $imapService, $parserService, $matcherService, $threadService, $notificationService) {
                // 2. Extract context
                $orderNumber = $parserService->extractOrderNumber($this->emailData['subject']);
                $normalizedSubject = $parserService->extractSubject($this->emailData['subject']);

                // 3. Find or create Client
                $klient = $matcherService->findOrCreateClient($this->emailData['from'], $this->emailData['subject']);

                // 4. Find or create Thread
                $watek = $threadService->findOrCreateThread(
                    $klient,
                    $normalizedSubject,
                    $orderNumber,
                    $this->emailData['in_reply_to'] ?? null
                );

                // 5. Save Message
                $rawContent = $this->emailData['body_html'] ?? $this->emailData['body_text'] ?? '';
                $purifiedContent = $parserService->parseContent($rawContent);

                $wiadomosc = Wiadomosc::create([
                    'watek_id' => $watek->id,
                    'kierunek' => MessageDirection::Przychodzacy,
                    'kanal' => MessageChannel::Email,
                    'tresc' => $purifiedContent,
                    'nadawca_email' => $this->emailData['from'],
                    'odbiorca_email' => $this->emailData['to'],
                    'zewnetrzny_id' => $this->emailData['zewnetrzny_id'],
                    'przeczytana' => false,
                ]);

                // 6. Handle Attachments
                if (! empty($this->emailData['attachments'])) {
                    foreach ($this->emailData['attachments'] as $attachmentData) {
                        $plik = $storageService->storeAttachment($attachmentData);

                        ZalacznikWiadomosci::create([
                            'wiadomosc_id' => $wiadomosc->id,
                            'plik_id' => $plik->id,
                            'nazwa_oryginalna' => $plik->nazwa_oryginalna,
                        ]);
                    }
                }

                // 7. Persist Notifications
                $notificationService->notifyNewMessage($wiadomosc);

                // 8. Broadcast Event
                MessageReceived::dispatch($wiadomosc);

                // 9. Mark as seen on IMAP server after successful DB transaction
                $imapService->markAsSeen($this->emailData['uid'], $this->emailData['folder']);
            });
        } catch (\Exception $e) {
            Log::error('Failed to process incoming email: '.$e->getMessage(), [
                'zewnetrzny_id' => $this->emailData['zewnetrzny_id'],
                'subject' => $this->emailData['subject'],
            ]);
            throw $e;
        }
    }
}
