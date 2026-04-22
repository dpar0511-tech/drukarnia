<?php

namespace Tests\Unit;

use App\Models\Klient;
use App\Models\WatekKomunikacji;
use App\Models\Wiadomosc;
use App\Models\Zamowienie;
use App\Services\Communication\ThreadManagerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThreadManagerServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ThreadManagerService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ThreadManagerService;
    }

    public function test_matches_thread_by_in_reply_to_header()
    {
        $klient = Klient::factory()->create();
        $thread = WatekKomunikacji::factory()->create(['klient_id' => $klient->id]);
        Wiadomosc::factory()->create([
            'watek_id' => $thread->id,
            'zewnetrzny_id' => '<original-msg-123@example.com>',
            'kierunek' => 'przychodzacy',
            'kanal' => 'email',
            'tresc' => 'Test',
        ]);

        $result = $this->service->findOrCreateThread(
            $klient,
            'Re: Subject',
            null,
            '<original-msg-123@example.com>'
        );

        $this->assertEquals($thread->id, $result->id);
    }

    public function test_finds_existing_thread_for_order()
    {
        $klient = Klient::factory()->create();
        $order = Zamowienie::factory()->create(['klient_id' => $klient->id]);
        $thread = WatekKomunikacji::factory()->create([
            'zamowienie_id' => $order->id,
            'klient_id' => $klient->id,
            'temat' => 'Order discussion',
        ]);

        $result = $this->service->findOrCreateThread($klient, 'New Subject', $order->numer);

        $this->assertEquals($thread->id, $result->id);
    }

    public function test_finds_existing_thread_by_subject_for_client()
    {
        $klient = Klient::factory()->create();
        $thread = WatekKomunikacji::factory()->create([
            'klient_id' => $klient->id,
            'temat' => 'General Inquiry',
        ]);

        $result = $this->service->findOrCreateThread($klient, 'General Inquiry');

        $this->assertEquals($thread->id, $result->id);
    }

    public function test_creates_new_thread_if_none_exists()
    {
        $klient = Klient::factory()->create();

        $result = $this->service->findOrCreateThread($klient, 'Hello');

        $this->assertEquals('Hello', $result->temat);
        $this->assertEquals($klient->id, $result->klient_id);
        $this->assertDatabaseHas('watki_komunikacji', [
            'klient_id' => $klient->id,
            'temat' => 'Hello',
        ]);
    }
}
