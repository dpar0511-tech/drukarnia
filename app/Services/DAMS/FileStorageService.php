<?php

namespace App\Services\DAMS;

use App\Models\Plik;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileStorageService
{
    /**
     * Store an email attachment to MinIO/S3 and create a Plik record.
     */
    public function storeAttachment(array $attachmentData): Plik
    {
        $disk = config('filesystems.disks.minio') ? 'minio' : 's3';

        $filename = $attachmentData['name'];
        $content = $attachmentData['content'];
        $mime = $attachmentData['mime'];
        $size = $attachmentData['size'] ?? strlen($content);

        $path = 'attachments/'.date('Y/m/d').'/'.Str::uuid().'_'.$filename;

        Storage::disk($disk)->put($path, $content);

        return Plik::create([
            'nazwa_oryginalna' => $filename,
            'slug' => (string) Str::uuid(),
            'mime_type' => $mime,
            'rozmiar_bajtow' => $size,
            'sciezka_s3' => $path,
            'bucket' => config("filesystems.disks.{$disk}.bucket"),
            'uploadowany_przez_id' => Auth::check() ? Auth::id() : null,
            'checksum_sha256' => hash('sha256', $content),
        ]);
    }
}
