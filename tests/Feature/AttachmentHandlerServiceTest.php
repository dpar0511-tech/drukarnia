<?php

namespace Tests\Feature;

use App\Models\Plik;
use App\Models\Wiadomosc;
use App\Services\DAMS\AttachmentHandlerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttachmentHandlerServiceTest extends TestCase
{
    use RefreshDatabase;

    protected AttachmentHandlerService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AttachmentHandlerService;

        // Mock disks that might be used
        Storage::fake('minio');
        Storage::fake('s3');
        Storage::fake('local');
    }

    public function test_handles_attachment_and_saves_to_storage()
    {
        $wiadomosc = Wiadomosc::factory()->create();
        $data = [
            'name' => 'test.pdf',
            'content' => 'fake content',
            'mime' => 'application/pdf',
        ];

        $result = $this->service->handle($wiadomosc, $data);

        $this->assertInstanceOf(Plik::class, $result);
        $this->assertEquals('test.pdf', $result->nazwa_oryginalna);

        // Determine which disk was actually used based on service logic
        $disk = config('filesystems.disks.minio') ? 'minio' : (config('filesystems.disks.s3') ? 's3' : 'local');

        Storage::disk($disk)->assertExists($result->sciezka_s3);

        $this->assertDatabaseHas('zalaczniki_wiadomosci', [
            'wiadomosc_id' => $wiadomosc->id,
            'plik_id' => $result->id,
        ]);

        $this->assertDatabaseHas('pliki', [
            'id' => $result->id,
            'nazwa_oryginalna' => 'test.pdf',
        ]);
    }
}
