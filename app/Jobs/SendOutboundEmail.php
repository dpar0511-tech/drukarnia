<?php

namespace App\Jobs;

use App\Events\MessageStatusChanged;
use App\Mail\OutboundCommunication;
use App\Models\Wiadomosc;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendOutboundEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public $backoff = [10, 60, 300];

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Wiadomosc $wiadomosc,
        public string $temat
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Mail::send(new OutboundCommunication($this->wiadomosc, $this->temat));

        $this->wiadomosc->update([
            'status_dostarczenia' => 'sent',
        ]);

        MessageStatusChanged::dispatch($this->wiadomosc);

        activity()
            ->performedOn($this->wiadomosc)
            ->causedBy(auth()->user())
            ->withProperty('temat', $this->temat)
            ->event('email_sent')
            ->log('Email sent');
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        $this->wiadomosc->update([
            'status_dostarczenia' => 'error',
        ]);

        MessageStatusChanged::dispatch($this->wiadomosc);

        Log::error("Failed to send email for Message ID: {$this->wiadomosc->id}. Error: {$exception->getMessage()}");

        activity()
            ->performedOn($this->wiadomosc)
            ->causedBy(auth()->user())
            ->withProperty('error', $exception->getMessage())
            ->event('email_failed')
            ->log('Email sending failed');
    }
}
