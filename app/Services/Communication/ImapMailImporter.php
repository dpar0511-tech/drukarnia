<?php

namespace App\Services\Communication;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Webklex\IMAP\Facades\Client;
use Webklex\PHPIMAP\Message;

class ImapMailImporter
{
    /**
     * Fetch all unseen messages from all folders (or specified account).
     */
    public function fetchUnseenMessages(string $account = 'default'): Collection
    {
        try {
            $client = Client::account($account);
            $client->connect();

            $allMessages = collect();
            // Fetch only from INBOX by default for performance and to avoid Trash/Sent/Drafts
            $folder = $client->getFolder('INBOX');

            if ($folder) {
                $messages = $folder->query()->unseen()->get();

                foreach ($messages as $message) {
                    $allMessages->push($this->parseMessage($message));
                }
            }

            return $allMessages;
        } catch (\Exception $e) {
            Log::error('IMAP Import Error: '.$e->getMessage());

            return collect();
        }
    }

    /**
     * Parse IMAP message into a structured array.
     */
    protected function parseMessage(Message $message): array
    {
        $attachments = [];
        foreach ($message->getAttachments() as $attachment) {
            $attachments[] = [
                'name' => $attachment->getName(),
                'content' => $attachment->getContent(),
                'mime' => $attachment->getMimeType(),
                'size' => $attachment->getSize(),
            ];
        }

        return [
            'zewnetrzny_id' => $message->getMessageId(),
            'message_id' => $message->getMessageId(),
            'in_reply_to' => $message->getInReplyTo(),
            'references' => $message->getReferences(),
            'subject' => $message->getSubject(),
            'from' => $message->getFrom()[0]->mail ?? null,
            'to' => $message->getTo()[0]->mail ?? null,
            'date' => $message->getDate(),
            'body_html' => $message->getHTMLBody(),
            'body_text' => $message->getTextBody(),
            'attachments' => $attachments,
            'uid' => $message->getUid(),
            'folder' => $message->getFolder()->path,
        ];
    }

    /**
     * Mark a message as seen on the server.
     */
    public function markAsSeen(string $uid, string $folderPath, string $account = 'default'): bool
    {
        try {
            $client = Client::account($account);
            $client->connect();
            $folder = $client->getFolder($folderPath);

            // In webklex/laravel-imap v3, we can get message by UID directly from folder
            $message = $folder->query()->getMessageByUid($uid);

            if ($message) {
                return $message->setFlag('Seen');
            }

            return false;
        } catch (\Exception $e) {
            Log::error("IMAP MarkAsSeen Error (UID: $uid): ".$e->getMessage());

            return false;
        }
    }
}
