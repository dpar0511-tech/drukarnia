# 08 — DAMS: LOKALNE SKLEPOWANIE PLIKÓW

> **Decyzja architektoniczna (nadrzędna):** Wszystkie pliki — systemowe i klientów — **wyłącznie na lokalnym dysku serwera ERP**.
> **Zakaz:** MinIO, S3 (AWS/DO/Wasabi), Google Cloud Storage, Azure Blob, Backblaze B2 *jako głównego storage* (dopuszczalne tylko off-site backup).
> **Zastępuje:** sekcje o MinIO/S3 w `MASTER_PLAN.md` i `steady-hopping-aurora.md`.

---

## 1. Uzasadnienie decyzji

- Jedna drukarnia = jedna instancja ERP na dedykowanym VPS; dane nie muszą być wspołdzielone między regionami.
- Pliki klientów zawierają **wrażliwą własność intelektualną** (projekty, marki, tajemnice handlowe) — wymóg właściciela: „nic nie opuszcza naszego serwera".
- Eliminacja kosztów zmiennych (per GB transferu, per request S3).
- Brak zależności od uptime zewnętrznego dostawcy.
- Kopia zapasowa off-site (Backblaze B2 / inny dysk) jest opcjonalna i **tylko jako encrypted backup** — nigdy nie jako warstwa dostępowa.

---

## 2. Konfiguracja `config/filesystems.php`

```php
return [
    'default' => env('FILESYSTEM_DISK', 'local_private'),

    'disks' => [

        // Główny dysk prywatny — niewidoczny z publicznego URL
        'local_private' => [
            'driver' => 'local',
            'root'   => storage_path('app/private'),
            'throw'  => true,
            'visibility' => 'private',
            'permissions' => [
                'file' => ['public' => 0644, 'private' => 0600],
                'dir'  => ['public' => 0755, 'private' => 0750],
            ],
        ],

        // Publiczne aktywa (logo, avatary) — symlink do public/storage
        'local_public' => [
            'driver' => 'local',
            'root'   => storage_path('app/public'),
            'url'    => env('APP_URL').'/storage',
            'visibility' => 'public',
        ],

        // Tymczasowe chunki tus.io
        'tus_temp' => [
            'driver' => 'local',
            'root'   => storage_path('app/temp/tus'),
            'throw'  => true,
        ],

        // Backup (lokalny drugi dysk, np. /mnt/backup)
        'backup_local' => [
            'driver' => 'local',
            'root'   => env('BACKUP_PATH', '/mnt/backup/drukarnia-erp'),
            'throw'  => true,
        ],
    ],
];
```

Po deployu: `php artisan storage:link`.

---

## 3. Struktura katalogów na dysku

```
storage/
└── app/
    ├── private/                                ← 99% danych tu
    │   ├── orders/
    │   │   └── {yyyy}/{mm}/{order_id}/
    │   │       ├── source/      ← oryginały klienta (PDF, AI, EPS, TIFF, ZIP)
    │   │       ├── proofs/      ← proof do akceptacji
    │   │       ├── production/  ← finalne produkcyjne
    │   │       └── thumbs/      ← sm/md/lg PNG
    │   │
    │   ├── designs/
    │   │   └── {project_id}/
    │   │       ├── scenes/      ← Konva JSON autosave (co 5 s)
    │   │       └── exports/     ← wyeksportowane PDF CMYK
    │   │
    │   ├── invoices/{yyyy}/{mm}/   ← PDF faktur z Subiekt
    │   ├── complaints/{rkl_id}/evidence/
    │   ├── shipment_labels/{yyyy}/{mm}/
    │   └── messages/{yyyy}/{mm}/   ← załączniki z IMAP
    │
    ├── public/                                 ← symlink z public/storage
    │   ├── logos/
    │   ├── avatars/
    │   └── product_thumbs_public/              ← miniatury produktów w sklepie
    │
    └── temp/
        └── tus/                                ← niedokończone uploady
```

**Reguła nazewnictwa plików:** `{uuid}-{oryginalna_nazwa_slug}.{ext}`. UUID eliminuje kolizje i ułatwia mapowanie do `pliki.uuid`.

---

## 4. Resumable upload: `tus-php`

Biblioteka: `ankitpokhrel/tus-php` — serwer tus.io protocol w PHP. Pozwala wznowić uwolnione uploady (utrata sieci → klient wznawia od ostatniego chunka).

### Architektura

```
Vue (tus-js-client) ──► /tus/uploads (tus-php server) ──► storage/app/temp/tus/
                                                              │
                                                              │ finalize hook
                                                              ▼
                                                        FileUploader::finalize()
                                                              │
                                                              ▼
                                         storage/app/private/orders/{id}/source/
                                                              │
                                                              ▼
                                                    new Plik + WersjaPliku
                                                              │
                                                              ▼
                                                    FileUploaded event
                                                              │
                                         ┌──────────────────────────────────────────┐
                                         ▼                                         ▼
                                   GenerateThumbnailsJob                      CheckOrderFiles
                                   (sm/md/lg PDF→PNG)                         (update status)
```

### Route i middleware

```php
// routes/tus.php
use TusPhp\Tus\Server;

Route::any('/tus/uploads/{any?}', function () {
    /** @var Server $server */
    $server = app(Server::class);
    return $server->serve();
})->middleware(['auth', 'throttle:uploads'])->where('any', '.*');
```

`app\Providers\AppServiceProvider::register()`:
```php
$this->app->singleton(Server::class, function () {
    $server = new Server();
    $server->setUploadDir(storage_path('app/temp/tus'));
    $server->setApiPath('/tus/uploads');
    $server->setMaxUploadSize(config('uploads.max_size', 5 * 1024 * 1024 * 1024));  // 5 GB
    return $server;
});
```

### Finalize hook

tus-php wywołuje event `tus-server.upload.complete`. Listener przenosi plik z `temp/tus/` do docelowego katalogu:

```php
// app/Listeners/FinalizeTusUpload.php
public function handle(TusUploadComplete $event): void
{
    $tempPath = $event->fileMeta['file_path'];
    $originalName = $event->fileMeta['name'];
    $orderId = $event->fileMeta['metadata']['order_id'] ?? null;

    $uploader = app(FileUploader::class);
    $plik = $uploader->finalize($tempPath, $originalName, $orderId);

    FileUploaded::dispatch($plik);
}
```

### `FileUploader::finalize`

```php
public function finalize(string $tempPath, string $originalName, ?int $orderId = null): Plik
{
    // 1. SHA-256
    $sha256 = hash_file('sha256', $tempPath);

    // 2. Oblicz destynację
    $year  = now()->format('Y');
    $month = now()->format('m');
    $uuid  = (string) Str::uuid();
    $slug  = Str::slug(pathinfo($originalName, PATHINFO_FILENAME));
    $ext   = pathinfo($originalName, PATHINFO_EXTENSION);
    $relDir  = "orders/{$year}/{$month}/{$orderId}/source";
    $relPath = "{$relDir}/{$uuid}-{$slug}.{$ext}";

    // 3. Przenieś plik (atomic rename gdy na tym samym filesystem)
    Storage::disk('local_private')->makeDirectory($relDir);
    rename($tempPath, Storage::disk('local_private')->path($relPath));

    // 4. Zapisz do DB
    return DB::transaction(function () use ($relPath, $originalName, $sha256, $uuid, $orderId) {
        $plik = Plik::create([
            'uuid' => $uuid,
            'original_name' => $originalName,
            'mime_type' => mime_content_type(Storage::disk('local_private')->path($relPath)),
            'size_bytes' => Storage::disk('local_private')->size($relPath),
            'checksum_sha256' => $sha256,
            'local_path' => $relPath,
            'disk' => 'local_private',
            'scan_status' => 'pending',
            'uploaded_by_id' => auth()->id(),
        ]);

        WersjaPliku::create([
            'plik_id' => $plik->id,
            'version_number' => 1,
            'local_path' => $relPath,
            'size_bytes' => $plik->size_bytes,
            'checksum_sha256' => $sha256,
            'is_active' => true,
            'uploaded_by_id' => auth()->id(),
        ]);

        if ($orderId) {
            PowiazaniePliku::create([
                'plik_id' => $plik->id,
                'fileable_type' => Zamowienie::class,
                'fileable_id' => $orderId,
                'rola' => 'artwork',
            ]);
        }

        return $plik;
    });
}
```

---

## 5. Bezpieczny dostęp — **nigdy direct URL**

Pliki w `storage/app/private/` **nie są** publicznie dostępne (brak symlinka, nginx block). Dostęp wyłącznie przez kontroler z autoryzacją.

### Route

```php
Route::get('/files/{uuid}/{versionId?}', [FileDownloadController::class, 'show'])
    ->middleware(['auth'])
    ->name('file.download');

// Publiczny link z token (approvals, klient bez logowania)
Route::get('/files/public/{token}', [PublicFileDownloadController::class, 'show'])
    ->middleware('throttle:30,1')
    ->name('file.public');
```

### Kontroler + Policy

```php
public function show(string $uuid, ?int $versionId, FileAccessService $svc): StreamedResponse
{
    $plik = Plik::where('uuid', $uuid)->firstOrFail();
    $this->authorize('view', $plik);   // FilePolicy

    $version = $versionId
        ? $plik->versions()->findOrFail($versionId)
        : $plik->activeVersion;

    return $svc->stream($plik, $version);
}
```

### `FilePolicy::view`

```php
public function view(User $user, Plik $p): bool
{
    // Admin / menedżer — wszystko
    if ($user->hasAnyRole(['admin', 'menedzer', 'ksiegowosc'])) return true;

    // Projektant — pliki zamówień, które prowadzi
    if ($user->hasRole('projektant')) {
        return $p->powiazania->contains(fn ($pw) =>
            $pw->fileable_type === Zamowienie::class
            && Zamowienie::find($pw->fileable_id)?->designer_id === $user->id
        );
    }

    // Operator — tylko pliki production swojego etapu
    if ($user->hasRole('operator')) {
        return $p->powiazania->contains(fn ($pw) =>
            $pw->rola === 'production'
            && $pw->fileable_type === Zamowienie::class
            && EtapProdukcji::where('operator_id', $user->id)
                ->where('zadanie_id', ZadanieProdukcyjne::where('zamowienie_id', $pw->fileable_id)->value('id'))
                ->exists()
        );
    }

    // Klient portalu — pliki swoich zamówień
    if ($user->hasRole('klient')) {
        return $p->powiazania->contains(fn ($pw) =>
            $pw->fileable_type === Zamowienie::class
            && Zamowienie::find($pw->fileable_id)?->klient_id === $user->klient_id
        );
    }

    return false;
}
```

### `FileAccessService::stream` — X-Accel-Redirect (nginx)

```php
public function stream(Plik $p, WersjaPliku $v): Response
{
    $absPath = Storage::disk($p->disk)->path($v->local_path);

    if (!file_exists($absPath)) {
        abort(404);
    }

    // nginx internal redirect (wydajne — Laravel nie buforuje pliku w RAM)
    if (config('uploads.use_x_accel_redirect', true)) {
        return response()->noContent()
            ->header('X-Accel-Redirect', '/_protected/' . $v->local_path)
            ->header('Content-Type', $p->mime_type)
            ->header('Content-Disposition', 'inline; filename="' . addslashes($p->original_name) . '"');
    }

    // Fallback: StreamedResponse (gdy brak nginxa)
    return response()->streamDownload(function () use ($absPath) {
        $stream = fopen($absPath, 'rb');
        fpassthru($stream);
        fclose($stream);
    }, $p->original_name, ['Content-Type' => $p->mime_type]);
}
```

### Konfiguracja nginx

```nginx
server {
    # ...

    # Chroniona lokalizacja — tylko przez X-Accel-Redirect
    location /_protected/ {
        internal;                                            # NIEdostępne z zewnątrz
        alias /var/www/drukarnia-erp/storage/app/private/;
    }

    # Laravel (reszta requestów)
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
}
```

**Rezultat:** klient żąda `/files/{uuid}` → Laravel sprawdza uprawnienia → zwraca pusty response z nagłówkiem `X-Accel-Redirect` → nginx serwuje plik bezpośrednio (zero obciążenia PHP).

### Temporary signed URLs (publiczny link z terminem)

```php
$signedUrl = URL::temporarySignedRoute('file.public', now()->addHour(), [
    'token' => $p->createPublicToken(60)   // krótkoterminowy token w DB
]);
```

Użycie: link do akceptacji PDF dla klienta bez konta (approvals module).

---

## 6. Polimorficzne powiązania

Tabela `powiazania_plikow(plik_id, fileable_type, fileable_id, rola)`. Pozwala jednemu plikowi być powiązanym z wieloma encjami (np. ten sam plik = `source` na zamówieniu i `evidence` w reklamacji).

```php
// W modelu Plik
public function powiazania(): HasMany
{
    return $this->hasMany(PowiazaniePliku::class);
}

// Dostęp z modelu Zamowienie
public function pliki(): MorphToMany
{
    return $this->morphToMany(Plik::class, 'fileable', 'powiazania_plikow')
        ->withPivot('rola')
        ->withTimestamps();
}
```

Role plików: `artwork`, `proof`, `production`, `evidence`, `invoice_pdf`, `shipment_label`, `design_export`, `design_scene`, `message_attachment`.

---

## 7. Miniatury

Biblioteki:
- `intervention/image` v3 — resize JPG/PNG.
- `spatie/pdf-to-image` (wymaga Ghostscript) — PDF → PNG pierwszej strony.
- `imagick` lub `gd` — backend dla `intervention/image`.

Job:

```php
// app/Jobs/GenerateThumbnailsJob.php
public function handle(): void
{
    $p = Plik::find($this->plikId);
    $src = Storage::disk($p->disk)->path($p->activeVersion->local_path);

    $pngPath = match (true) {
        str_starts_with($p->mime_type, 'image/') => $src,
        $p->mime_type === 'application/pdf'      => $this->pdfToPng($src),
        default                                   => null,
    };
    if (!$pngPath) return;

    foreach (['sm' => 150, 'md' => 400, 'lg' => 1200] as $size => $px) {
        $this->generate($p, $pngPath, $size, $px);
    }
}

private function generate(Plik $p, string $pngPath, string $size, int $px): void
{
    $manager = new \Intervention\Image\ImageManager(new \Intervention\Image\Drivers\Imagick\Driver());
    $img = $manager->read($pngPath)->scaleDown(width: $px);
    $thumbRel = dirname($p->activeVersion->local_path).'/thumbs/'.$size.'.png';
    Storage::disk($p->disk)->put($thumbRel, (string) $img->encode(new \Intervention\Image\Encoders\PngEncoder()));

    Miniatura::updateOrCreate(
        ['plik_id' => $p->id, 'rozmiar' => $size],
        ['local_path' => $thumbRel, 'width_px' => $img->width(), 'height_px' => $img->height()]
    );
}
```

**Kolejka:** `preflight`, `tries: 2`, `backoff: [30, 120]`. Failure nie blokuje — plik jest nadal użyteczny.

**Alternatywa:** `spatie/laravel-medialibrary` generuje konwersje automatycznie (via `registerMediaConversions`), zastępując ręczne jobsy. Dla MVP rekomendacja: **Media Library** + custom `tus` loader, który po finalize wywołuje `$model->addMediaFromDisk($tempPath, 'local_private')`.

---

## 8. Wersjonowanie

Re-upload tego samego pliku źródłowego = **nowy wiersz `wersje_plikow`** (v2, v3…). Stare wersje pozostają (immutable), flag `is_active` na najnowszej.

UI w DAMS: dropdown z listą wersji + „przywróć wersję N" (ustawia `is_active` na starej; nie usuwa nowej).

**Retencja wersji:** wszystkie wersje pliku trzymane przez 90 dni po soft-delete `plik.deleted_at`.

---

## 9. Backup

### Lokalny drugi dysk
Codziennie 03:00 przez scheduler:
```php
// app/Console/Kernel.php / routes/console.php
$schedule->exec('pg_dump -Fc drukarnia_erp -f /mnt/backup/drukarnia-erp/db/'.now()->format('Y-m-d').'.dump')
    ->dailyAt('03:00');
$schedule->exec('rsync -av --delete /var/www/drukarnia-erp/storage/app/private/ /mnt/backup/drukarnia-erp/storage/')
    ->dailyAt('03:30');
$schedule->command('backup:purge-old --days=30')->dailyAt('04:00');
```

### Off-site (opcjonalne)
Backblaze B2 **tylko jako encrypted backup** (rclone + age encryption):
```bash
rclone copy /mnt/backup/drukarnia-erp/db b2:drukarnia-erp-db --crypt
```
**Nigdy nie jako warstwa dostępowa aplikacji.**

### Retencja
- Lokalny: 30 dni (30 dumps + 30 snapshotów storage).
- Off-site: 90 dni.
- Po retencji: hard delete przez `backup:purge-old`.

---

## 11. Retencja i cleanup

### Plików (tabela `pliki`)
Soft-delete → fizyczny `deleted_at`, plik zostaje na dysku 90 dni. Cron `CleanupService::purgeDeletedFiles()`:

```php
$oldFiles = Plik::onlyTrashed()
    ->where('deleted_at', '<=', now()->subDays(90))
    ->get();

foreach ($oldFiles as $p) {
    foreach ($p->versions as $v) Storage::disk($p->disk)->delete($v->local_path);
    foreach ($p->miniatures as $m) Storage::disk($p->disk)->delete($m->local_path);
    $p->forceDelete();
}
```

### Dokumentów księgowych
`dokumenty_finansowe` — **5 lat retencji prawnej** (PL). Soft-delete, po 5 latach → `FinanceCleanupService::purge()`.

### Niedokończone tus uploads
Codziennie: usuwanie z `temp/tus/` plików starszych niż 48 h.

---

## 12. Limity i quota

| Ograniczenie | Wartość | Konfiguracja |
|--------------|---------|--------------|
| Max rozmiar pliku | 5 GB | `config/uploads.php` + `php.ini: upload_max_filesize=5G, post_max_size=5G, max_execution_time=600` |
| Max chunk tus | 20 MB | `tus-php` config |
| Max plików per zamówienie | 50 | walidacja w `StoreOrderFileRequest` |
| Quota per klient (portal) | 20 GB | `klienci.storage_quota_bytes` — liczone z `SUM(pliki.size_bytes)` |
| MIME whitelist | `application/pdf, application/postscript, application/illustrator, image/tiff, image/png, image/jpeg, application/zip, application/x-indesign` | `config/uploads.php` → `'allowed_mimes'` |

Walidacja MIME:
```php
$file->getMimeType();           // sniff by finfo/magic bytes — zaufany
$file->getClientMimeType();     // od klienta — NIEzaufany, tylko do logów
```

---

## 13. Uprawnienia filesystem (OS)

Po deployu (jednorazowo):
```bash
chown -R www-data:www-data /var/www/drukarnia-erp/storage
chmod -R 750 /var/www/drukarnia-erp/storage/app/private
chmod -R 755 /var/www/drukarnia-erp/storage/app/public
chmod -R 770 /var/www/drukarnia-erp/storage/app/temp
```

**SELinux** (jeśli aktywny):
```bash
semanage fcontext -a -t httpd_sys_rw_content_t "/var/www/drukarnia-erp/storage(/.*)?"
restorecon -R /var/www/drukarnia-erp/storage
```

---

## 14. Monitoring pojemności

Komenda Artisan `storage:health`:
```bash
php artisan storage:health
# Output:
#   storage/app/private: 347 GB / 500 GB (69%)
#   storage/app/temp:    12 GB  / 500 GB (2%)
#   /mnt/backup:         89 GB  / 1 TB  (9%)
#   Critical threshold: 85%      ✓ OK
```

Zintegrowane z Laravel Pulse card + alert Slack/email gdy ≥85%.

---

## 15. Checklist implementacji DAMS

1. Migracje: `pliki`, `wersje_plikow`, `powiazania_plikow`, `miniatury` (lub media-library tables).
2. Modele `Plik`, `WersjaPliku`, `PowiazaniePliku`, `Miniatura` z relacjami.
3. `config/filesystems.php` z dyskami `local_private`, `local_public`, `tus_temp`, `backup_local`.
4. `config/uploads.php` z limitami i MIME whitelist.
5. `AppServiceProvider` — bind `Tus\Server` singleton.
6. Route `/tus/uploads/...`.
7. `FileUploader`, `FileAccessService`, `ThumbnailGenerator` services.
8. `FinalizeTusUpload` listener.
9. Jobs: `GenerateThumbnailsJob`.
10. `FilePolicy` z regułami per-rola.
11. `FileDownloadController` + `PublicFileDownloadController`.
12. nginx: `location /_protected/` `internal`.
14. Install Ghostscript + ImageMagick.
15. Cron `backup:run` + `storage:cleanup` + `storage:health`.
16. Vue component `<FileUploader>` używający `tus-js-client`.
17. Testy Feature: upload z tus, finalize, stream, permission denial, version retrieval.
