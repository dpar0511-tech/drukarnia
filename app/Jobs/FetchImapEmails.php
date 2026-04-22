<?php

namespace App\Jobs;

use App\Services\Communication\ImapMailImporter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class FetchImapEmails implements ShouldQueue
{
    use Queueable;

    /**
     * Execute the job.
     */
    public function handle(ImapMailImporter $importer): void
    {
        try {
            $messages = $importer->fetchUnseenMessages();

            foreach ($messages as $messageData) {
                ProcessIncomingEmail::dispatch($messageData);
                // Mark as seen is moved to ProcessIncomingEmail for better resilience
            }
        } catch (\Exception $e) {
            Log::error('IMAP Fetch Job Error: '.$e->getMessage());
        }
    }
}
