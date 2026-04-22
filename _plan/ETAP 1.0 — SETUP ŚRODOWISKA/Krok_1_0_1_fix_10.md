# Plan Implementacji - Poprawki po Audycie Etapu 1.0 (Setup Środowiska)

**Cel:** Rozwiązanie problemów architektonicznych i konfiguracyjnych zidentyfikowanych w Kroku 1.0.1 oraz 1.0.2. Wdrożenie pełnej, prawidłowej integracji `spatie/laravel-activitylog` oraz synchronizacja dokumentacji z rzeczywistym kodem.

## Zmiany do wprowadzenia

### 1. Pełna Integracja `spatie/laravel-activitylog`
Zgodnie z wyborem, przechodzimy na natywną i rekomendowaną konfigurację pakietu Spatie, usuwając autorskie i zduplikowane rozwiązania:
- **Usunięcie customowej migracji i modelu:**
  - Usunąć plik migracji `database/migrations/2026_04_20_180935_create_audit_logs_table.php`.
  - Usunąć autorski model `app/Models/AuditLog.php`.
- **Publikacja plików Spatie:**
  - Uruchomić publikację migracji Spatie: `php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-migrations"`.
  - Uruchomić publikację konfiguracji Spatie: `php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-config"`.
- **Aktualizacja modelu `User.php`:**
  - Usunąć autorską metodę `auditLogs()` (korzystającą z ręcznej relacji `morphMany`).
  - Dodać trait `Spatie\Activitylog\Traits\LogsActivity` (jeśli potrzebne logowanie samych zmian na modelu Użytkownika) oraz `Spatie\Activitylog\Traits\CausesActivity` (aby poprawnie przypinać logi do użytkownika wykonującego akcję).

### 2. Synchronizacja Dokumentacji `_docs/04_DATABASE_SCHEMA.md` z Kodem
Dokumentacja bazy danych posiadała drobne nieścisłości względem faktycznych (i lepszych) implementacji w kodzie. Należy:
- **Tabela `users`:**
  - Usunąć z opisu pole `role_id BIGINT REFERENCES roles(id)`. Relacje ról są poprawnie zarządzane polimorficznie przez tabelę `model_has_roles` wygenerowaną przez paczkę `spatie/laravel-permission`.
  - Dodać pole `deleted_at TIMESTAMP NULL` do opisu schematu (migracja poprawnie korzysta z `softDeletes()`).
- **Logi aktywności:**
  - Zaktualizować nazwę i strukturę tabeli z autorskiej `activity_logs` (gdzie używano m.in. `user_id`, `model`, `changes`) na strukturę generowaną przez Spatie (która domyślnie wykorzystuje `activity_log` oraz pola: `log_name`, `description`, `subject_type`, `subject_id`, `causer_type`, `causer_id`, `properties`). Należy zaktualizować sekcję ERD oraz szczegółowe opisy tabel w pliku markdown.

### 3. Poprawka Konfiguracji Vite (`vite.config.js`)
Obecnie `app.blade.php` korzysta z dyrektywy `@vite(['resources/js/app.js', ...])`, a główny plik JS (`resources/js/app.js`) posiada import pliku CSS (`import '../css/app.css';`). Jest to poprawne podejście dla aplikacji Inertia w Vue, jednak konfiguracja w `vite.config.js` posiada zbędny dubel w postaci oddzielnego punktu wejścia dla CSS.
- **Poprawka:** Zaktualizować `vite.config.js`, zamieniając `input: ['resources/css/app.css', 'resources/js/app.js']` na `input: ['resources/js/app.js']`. Dzięki temu Vite nie będzie generował niepotrzebnych, zdublowanych wpisów w manifeście, a cały styl wstrzyknie się prawidłowo z poziomu importu w kodzie JavaScript.

## Weryfikacja
- Usunąć (cofnąć) autorską migrację z bazy, a następnie wykonać `php artisan migrate` w celu wygenerowania prawidłowej tabeli `activity_log` poprzez opublikowaną migrację Spatie.
- Uruchomić `php artisan db:seed` w celu weryfikacji, czy powiązania ról i dane działają poprawnie.
- Uruchomić polecenie kompilacji `npm run build`, aby upewnić się, że zaktualizowana konfiguracja Vite generuje pliki prawidłowo i bez ostrzeżeń.
