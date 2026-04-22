<?php

namespace App\Mail;

use App\Models\Wiadomosc;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class OutboundCommunication extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Wiadomosc $wiadomosc,
        public string $temat
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->temat,
            to: [$this->wiadomosc->odbiorca_email],
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.outbound',
            with: [
                'tresc' => $this->wiadomosc->tresc,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $attachments = [];

        foreach ($this->wiadomosc->pliki as $plik) {
            // Use the correct disk (minio for DAMS or default)
            $disk = config('filesystems.default') === 'minio' ? 'minio' : 's3';

            if (Storage::disk($disk)->exists($plik->sciezka_s3)) {
                $attachments[] = Attachment::fromStorage($plik->sciezka_s3)
                    ->as($plik->nazwa_oryginalna)
                    ->withMime($plik->mime_type);
            }
        }

        return $attachments;
    }
}
