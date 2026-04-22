# Plan naprawczy: Krok 1.0.1 — Inicjalizacja projektu [CFG]

## Cel
Naprawa błędów konfiguracyjnych, błędnych wersji zależności oraz niespójności w fazie inicjalizacji projektu (Krok 1.0.1), aby zapewnić stabilny fundament zgodny z dokumentacją `_docs`.

## Znalezione błędy i rozbieżności (Audit)

1.  **Błędy wersji w manifestach (Krytyczne)**:
    - `composer.json`: Zadeklarowano `laravel/framework: ^13.0` (wersja v13 nie istnieje, aktualna to v11).
    - `package.json`: Zadeklarowano `vite: ^8.0.0` oraz `typescript: ^6.0.3` (wersje nieistniejące w kanale stabilnym).
    - `package.json`: `laravel-vite-plugin: ^3.0.0` (aktualna stabilna to v1.2.x).

2.  **Niespójność konfiguracji Storage (MinIO)**:
    - `config/filesystems.php` dla dysku `minio` oczekuje zmiennych `MINIO_KEY` / `MINIO_SECRET`.
    - `docker-compose.yml` oraz `.env.example` używają standardowych zmiennych `AWS_ACCESS_KEY_ID` / `AWS_SECRET_ACCESS_KEY`.

3.  **Błędy Case Sensitivity (Importy frontendowe)**:
    - W `resources/js/Layouts/AppLayout.vue` występują importy typu `@/components/ui/...` (mała litera), podczas gdy struktura folderów to `resources/js/Components/...` (duża litera). Spowoduje to błędy budowania w środowisku Docker/Linux.

4.  **Błędne wartości domyślne w środowisku**:
    - `.env.example` ustawia `DB_CONNECTION=sqlite` zamiast wymaganego `pgsql`.
    - `.env.example` ustawia `QUEUE_CONNECTION=database` zamiast wymaganego `redis`.
    - `BROADCAST_CONNECTION` ustawione na `null` zamiast `reverb`.

## Kroki do wykonania

### 1. Korekta plików manifestu
- **Zadanie**: Zaktualizować `composer.json` oraz `package.json` do rzeczywistych, stabilnych wersji.
- **Pliki**: `composer.json`, `package.json`.
- **Działanie**: Zmiana `laravel/framework` na `^11.0`, `vite` na `^6.0.0`, `typescript` na `^5.0.0`.

### 2. Standaryzacja konfiguracji MinIO/S3
- **Zadanie**: Ujednolicić zmienne środowiskowe w `config/filesystems.php`.
- **Plik**: `config/filesystems.php`.
- **Działanie**: Zmiana mapowania w dysku `minio`, aby korzystał z `AWS_ACCESS_KEY_ID` i `AWS_SECRET_ACCESS_KEY` dla spójności z resztą ekosystemu.

### 3. Aktualizacja szablonu środowiska (.env.example)
- **Zadanie**: Dostosować wartości domyślne do docelowej architektury (PostgreSQL, Redis, Reverb).
- **Plik**: `.env.example`.
- **Działanie**: Ustawienie poprawnych połączeń, hostów (zgodnie z `docker-compose.yml`) oraz kluczy dla Reverb.

### 4. Naprawa importów komponentów UI
- **Zadanie**: Poprawić wielkość liter w ścieżkach importów, aby uniknąć błędów na systemach Linux.
- **Plik**: `resources/js/Layouts/AppLayout.vue`.
- **Działanie**: Zmiana `@/components/ui/` na `@/Components/ui/`.

### 5. Finalna weryfikacja techniczna
- **Zadanie**: Uruchomienie instalacji i sprawdzenie spójności.
- **Działania**:
    - `composer update`
    - `npm install`
    - `npm run build` (weryfikacja ścieżek)
    - Test połączenia z DB/Redis wewnątrz kontenerów.

## Definicja Gotowości (DoD)
- Projekt buduje się bez błędów (`npm run build`).
- Plik `.env` zawiera poprawne dane do PostgreSQL i Redis.
- Aplikacja poprawnie ładuje layout Inertia bez błędów 404 dla komponentów.
