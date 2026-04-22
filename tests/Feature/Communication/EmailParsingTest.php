<?php

namespace Tests\Feature\Communication;

use App\Enums\MessageChannel;
use App\Enums\MessageDirection;
use App\Enums\SystemRole;
use App\Jobs\ProcessIncomingEmail;
use App\Models\Role;
use App\Models\User;
use App\Models\Wiadomosc;
use App\Services\Communication\ClientMatcherService;
use App\Services\Communication\EmailParserService;
use App\Services\Communication\HtmlPurifier;
use App\Services\Communication\ImapMailImporter;
use App\Services\Communication\NotificationService;
use App\Services\Communication\ThreadManagerService;
use App\Services\DAMS\FileStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EmailParsingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create Admin and Manager roles and users for notifications
        $adminRole = Role::create(['name' => SystemRole::Admin->value, 'guard_name' => 'web']);
        $managerRole = Role::create(['name' => SystemRole::Menedzer->value, 'guard_name' => 'web']);

        User::factory()->create(['email' => 'admin@example.com'])->assignRole($adminRole);
        User::factory()->create(['email' => 'manager@example.com'])->assignRole($managerRole);
    }

    public function test_it_processes_incoming_email_correctly()
    {
        Storage::fake('minio');
        Storage::fake('s3');
        Event::fake();

        $emailData = [
            'from' => 'test@client.com',
            'to' => 'office@drukarnia.pl',
            'subject' => 'New Order Inquiry',
            'body_html' => '<div>Hello, I want to order some flyers.</div><div class="gmail_quote">On Mon, Apr 20, 2026 at 10:00 AM office@drukarnia.pl wrote: ...</div>',
            'zewnetrzny_id' => '<msg-12345@client.com>',
            'uid' => '123',
            'folder' => 'INBOX',
            'attachments' => [
                [
                    'name' => 'logo.png',
                    'content' => 'fake-image-content',
                    'mime' => 'image/png',
                    'size' => 100,
                ],
            ],
        ];

        // Mocking IMAP Importer is tricky here because it's passed via handle()
        // But we can just run the job and it should use the real services (which we want to test)
        // We just need to make sure the IMAP markAsSeen doesn't fail the test

        $job = new ProcessIncomingEmail($emailData);
        $job->handle(
            app(FileStorageService::class),
            $this->mock(ImapMailImporter::class, function ($mock) {
                $mock->shouldReceive('markAsSeen')->once();
            }),
            app(HtmlPurifier::class),
            app(EmailParserService::class),
            app(ClientMatcherService::class),
            app(ThreadManagerService::class),
            app(NotificationService::class)
        );

        // 1. Check Client creation (ClientMatcherService)
        $this->assertDatabaseHas('klienci', [
            'email_glowny' => 'test@client.com',
            'status' => 'draft',
        ]);

        // 2. Check Thread creation (ThreadManagerService)
        $this->assertDatabaseHas('watki_komunikacji', [
            'temat' => 'New Order Inquiry',
        ]);

        // 3. Check Message creation and Content Cleaning (EmailParserService + HtmlPurifier)
        $wiadomosc = Wiadomosc::where('zewnetrzny_id', '<msg-12345@client.com>')->first();
        $this->assertNotNull($wiadomosc);
        $this->assertEquals(MessageDirection::Przychodzacy, $wiadomosc->kierunek);
        $this->assertEquals(MessageChannel::Email, $wiadomosc->kanal);
        // Quoted history should be removed, but newlines preserved (purifier might wrap in tags, but we check text)
        $this->assertStringContainsString('Hello, I want to order some flyers.', $wiadomosc->tresc);
        $this->assertStringNotContainsString('On Mon, Apr 20, 2026', $wiadomosc->tresc);

        // 4. Check Attachments (FileStorageService)
        $this->assertDatabaseHas('pliki', [
            'nazwa_oryginalna' => 'logo.png',
            'mime_type' => 'image/png',
        ]);
        $this->assertDatabaseHas('zalaczniki_wiadomosci', [
            'wiadomosc_id' => $wiadomosc->id,
            'nazwa_oryginalna' => 'logo.png',
        ]);

        // 5. Check Notifications persistence
        $this->assertDatabaseCount('powiadomienia', 2); // Admin + Manager
        $this->assertDatabaseHas('powiadomienia', [
            'typ' => 'new_message',
            'link' => "/inbox/{$wiadomosc->watek_id}",
        ]);
    }
}
