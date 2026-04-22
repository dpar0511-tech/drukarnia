<?php

namespace App\Services\DAMS;

use App\Models\Plik;
use App\Models\Wiadomosc;
use App\Models\ZalacznikWiadomosci;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AttachmentHandlerService
{
    /**
     * Handle incoming email attachment.
     * Saves the file to storage and creates database records.
     */
    public function handle(Wiadomosc $wiadomosc, array $attachmentData): Plik
    {
        $filename = $attachmentData['name'];
        $content = $attachmentData['content'];
        $mime = $attachmentData['mime'] ?? 'application/octet-stream';
        $size = strlen($content);

        // Determine disk - prefer minio, fall back to s3 or local
        $disk = config('filesystems.disks.minio') ? 'minio' : (config('filesystems.disks.s3') ? 's3' : 'local');

        $path = 'communication/'.$wiadomosc->id.'/'.Str::random(10).'_'.$filename;

        Storage::disk($disk)->put($path, $content);

        $plik = Plik::create([
            'nazwa_oryginalna' => $filename,
            'slug' => Str::slug(pathinfo($filename, PATHINFO_FILENAME)).'-'.Str::random(5),
            'mime_type' => $mime,
            'rozmiar_bajtow' => $size,
            'sciezka_s3' => $path,
            'bucket' => config("filesystems.disks.{$disk}.bucket"),
            'checksum_sha256' => hash('sha256', $content),
        ]);

        ZalacznikWiadomosci::create([
            'wiadomosc_id' => $wiadomosc->id,
            'plik_id' => $plik->id,
            'nazwa_oryginalna' => $filename,
        ]);

        return $plik;
    }
}
