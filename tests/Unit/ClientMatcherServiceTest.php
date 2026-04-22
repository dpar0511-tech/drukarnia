<?php

namespace Tests\Unit;

use App\Models\Klient;
use App\Models\OsobaKontaktowa;
use App\Models\Zamowienie;
use App\Services\Communication\ClientMatcherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientMatcherServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ClientMatcherService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ClientMatcherService;
    }

    public function test_matches_client_by_direct_email()
    {
        $klient = Klient::factory()->create(['email_glowny' => 'test@example.com']);

        $result = $this->service->findOrCreateClient('test@example.com');

        $this->assertEquals($klient->id, $result->id);
    }

    public function test_matches_client_by_contact_person_email()
    {
        $klient = Klient::factory()->create();
        OsobaKontaktowa::factory()->create([
            'klient_id' => $klient->id,
            'email' => 'contact@example.com',
        ]);

        $result = $this->service->findOrCreateClient('contact@example.com');

        $this->assertEquals($klient->id, $result->id);
    }

    public function test_matches_client_by_order_number_in_subject()
    {
        $klient = Klient::factory()->create();
        $order = Zamowienie::factory()->create([
            'klient_id' => $klient->id,
            'numer' => 'DRK-2026-12345',
        ]);

        $result = $this->service->findOrCreateClient('unknown@example.com', 'Update on DRK-2026-12345');

        $this->assertEquals($klient->id, $result->id);
    }

    public function test_creates_draft_client_when_no_match_found()
    {
        $email = 'new@example.com';

        $result = $this->service->findOrCreateClient($email);

        $this->assertEquals('draft', $result->status);
        $this->assertEquals($email, $result->email_glowny);
        $this->assertDatabaseHas('klienci', [
            'email_glowny' => $email,
            'status' => 'draft',
        ]);
    }
}
