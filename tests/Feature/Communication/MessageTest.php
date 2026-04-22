<?php

namespace Tests\Feature\Communication;

use App\Enums\SystemRole;
use App\Jobs\SendOutboundEmail;
use App\Mail\OutboundCommunication;
use App\Models\Klient;
use App\Models\Role;
use App\Models\User;
use App\Models\WatekKomunikacji;
use App\Models\Wiadomosc;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MessageTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected $klient;

    protected $watek;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);

        // Create required roles
        Role::create(['name' => SystemRole::Admin->value, 'guard_name' => 'web']);

        $this->user = User::factory()->admin()->create();
        $this->actingAs($this->user);

        $this->klient = Klient::create([
            'typ' => 'B2B',
            'imie_nazwa' => 'Test Client',
            'email_glowny' => 'client@example.com',
            'status' => 'aktywny',
        ]);

        $this->watek = WatekKomunikacji::create([
            'klient_id' => $this->klient->id,
            'temat' => 'Test Topic',
            'status' => 'otwarty',
        ]);
    }

    public function test_can_preview_template()
    {
        $response = $this->postJson(route('messages.preview'), [
            'content' => 'Hello {{ $klient->imie_nazwa }}',
            'watek_id' => $this->watek->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('html', 'Hello Test Client');
    }

    public function test_can_send_message_and_queues_job()
    {
        Queue::fake();

        $response = $this->post(route('messages.send'), [
            'watek_id' => $this->watek->id,
            'temat' => 'Email Topic',
            'tresc' => 'Test content',
            'recipient' => 'test@example.com',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('wiadomosci', [
            'watek_id' => $this->watek->id,
            'kierunek' => 'wychodzacy',
            'status_dostarczenia' => 'pending',
        ]);

        Queue::assertPushed(SendOutboundEmail::class);
    }

    public function test_message_is_rendered_with_context_when_sent()
    {
        $response = $this->post(route('messages.send'), [
            'watek_id' => $this->watek->id,
            'temat' => 'Email for {{ $klient->imie_nazwa }}',
            'tresc' => 'Hello {{ $klient->imie_nazwa }}, your order is {{ $watek->temat }}',
            'recipient' => 'test@example.com',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('wiadomosci', [
            'watek_id' => $this->watek->id,
            'tresc' => 'Hello Test Client, your order is Test Topic',
        ]);
    }

    public function test_job_sends_email_and_updates_status()
    {
        Mail::fake();

        $wiadomosc = Wiadomosc::create([
            'watek_id' => $this->watek->id,
            'kierunek' => 'wychodzacy',
            'kanal' => 'email',
            'status_dostarczenia' => 'pending',
            'tresc' => 'Final content',
            'odbiorca_email' => 'test@example.com',
        ]);

        $job = new SendOutboundEmail($wiadomosc, 'Email Topic');
        $job->handle();

        Mail::assertSent(OutboundCommunication::class, function ($mail) {
            return $mail->hasTo('test@example.com');
        });

        $this->assertEquals('sent', $wiadomosc->fresh()->status_dostarczenia);
    }
}
