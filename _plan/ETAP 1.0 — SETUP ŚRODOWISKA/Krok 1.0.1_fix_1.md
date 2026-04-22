## Plan: Krok 1.0.1 — Poprawki Inicjalizacji (Akapit [CFG])

Audyt środowiska wykazał kilka rozbieżności z dokumentem Krok 1.0.1 oraz dobrymi praktykami ekosystemu Laravel. Główne braki dotyczą niekompletnej infrastruktury Dockerowej, błędów w konfiguracji MinIO oraz optymalizacji konfiguracji Horizon.

**Steps**
1. **Zaktualizowanie pliku `.env`**
   - Poprawienie definicji bucketa MinIO (`AWS_BUCKET=drukarnia-files`).
   - Dodanie wymaganego dla środowiska deweloperskiego wpisu `AWS_ENDPOINT=http://localhost:9000`.
2. **Rozbudowa `docker-compose.yml`**
   - Dodanie brakujących serwisów: `meilisearch`, `app` (PHP), `nginx` oraz kontenera roboczego `horizon`.
   - Opcjonalnie dodanie skryptu inicjalizującego MinIO z użyciem obrazu `minio/mc`, aby automatycznie zakładał bucket `drukarnia-files` z uprawnieniami prywatnymi.
3. **Poprawa konfiguracji kolejek i Horizona**
   - Zmiana limitu `tries` w Horizonie (`config/horizon.php` -> `defaults`), aby umożliwić zadziałanie zaimplementowanego Exponential Backoff.
   - Podział głównego workera na obsługę różnych priorytetów (high, default, notifications, low).
   - Zmiana skryptu developerskiego `"dev"` w `composer.json`, aby używał Horizona zamiast `queue:listen`.
4. **Inicjalizacja repozytorium Git**
   - Wykonanie `git init`, dodanie plików i wykonanie initial commita (wymagane na końcu Kroku 1.0.1).

**Relevant files**
- `.env` — aktualizacja `AWS_BUCKET` oraz dodanie `AWS_ENDPOINT`.
- `docker-compose.yml` — dopisanie brakujących serwisów.
- `config/horizon.php` — w sekcji `defaults.supervisor-1` zmiana `'tries' => 3` oraz rozszerzenie tablicy `'queue' => ['high', 'default', 'notifications', 'low']`.
- `composer.json` — podmiana `php artisan queue:listen --tries=1 --timeout=0` na `php artisan horizon` w definicji skryptu `dev`.

**Verification**
1. Zrestartowanie środowiska poleceniem `docker-compose down && docker-compose up -d` i weryfikacja poprawności dodanych kontenerów.
2. Odpalenie `npm run dev` i sprawdzenie w konsoli czy Horizon loguje poprawne nasłuchiwanie na 4 kanałach.
3. Wykonanie w Tinker: `Storage::disk('s3')->put('test.txt', 'test')` aby potwierdzić udany test połączenia do MinIO.
4. Wykonanie i potwierdzenie `git status`.

**Decisions**
- Chociaż `artisan serve` działa sprawnie dla lokalnego API, dokument Krok 1.0.1 jawnie wymaga dodania infrastruktury `app`, `nginx` oraz `meilisearch` w `docker-compose`, by zachować spójność ze środowiskiem produkcyjnym/stagingowym.

**Further Considerations**
1. Czy w kontenerach `app`/`nginx` planujecie klasyczne podejście z PHP-FPM, czy może preferujecie wdrożenie szybszego Laravel Octane (np. z obrazem FrankenPHP)?