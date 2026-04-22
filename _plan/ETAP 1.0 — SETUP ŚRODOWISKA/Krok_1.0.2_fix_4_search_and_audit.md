# Plan Naprawczy: Krok 1.0.2 — Baza danych: fundament [DB] (Naprawa Wyszukiwarki i Audytu)

W wyniku ponownego audytu kroku "1.0.2 — Baza danych: fundament [DB]" pod kątem błędów zgłoszonych w dokumentacji oraz problemów z działaniem wyszukiwania ("POШУK ШО НЕ ПРАЦЮЄ"), zidentyfikowano krytyczne rozbieżności w integracji Laravel Scout z Meilisearch oraz w systemie uprawnień i audytu.

## Zidentyfikowane Problemy (Bugi):

1. **Błędne mapowanie w modelu `Klient` (Krytyczne dla wyszukiwania):**
   Metoda `toSearchableArray()` w `app/Models/Klient.php` odwołuje się do nieistniejących pól `nazwa` i `email`. Prawidłowe kolumny to `imie_nazwa` i `email_glowny`. Powoduje to, że wyszukiwarka Meilisearch indeksuje puste wartości (`null`).

2. **Brak asynchronicznego indeksowania Scout (Wydajność):**
   Brak ustawienia `SCOUT_QUEUE=true` powoduje, że każda operacja zapisu (nawet seedowanie) wysyła synchroniczne zapytanie HTTP do Meilisearch, co spowalnia system i może powodować błędy timeout podczas inicjalizacji bazy.

3. **Błędy w Policies (Krytyczne dla autoryzacji):**
   W `UserPolicy` i `RolePolicy` używana jest metoda `$user->hasPermission()`, która nie istnieje w pakiecie `spatie/laravel-permission`. Powinno być używane wbudowane `hasPermissionTo()`.

4. **Niespójne relacje polimorficzne Audytu:**
   Relacja `auditLogs()` w modelu `User` nie korzysta w pełni z mechanizmów polimorficznych Laravela (`morphMany`), co utrudnia filtrowanie logów i jest niezgodne ze standardami projektu.

5. **Spam w logach audytu podczas seedowania:**
   `DatabaseSeeder` powinien korzystać z traita `WithoutModelEvents`, aby tabela `activity_logs` nie była zapełniana setkami rekordów "systemowych" podczas komendy `db:seed`.

## Szczegółowy Plan Implementacji:

### Krok 1: Naprawa Wyszukiwania (Laravel Scout) [BE]
- **Model `Klient`:** Zaktualizuj `toSearchableArray()`, aby zwracał prawidłowe klucze: `imie_nazwa`, `nip`, `email_glowny` oraz `telefon_glowny`.
- **Model `User`:** Dodaj pole `aktywny` do indeksu wyszukiwania, aby umożliwić filtrowanie wyników w przyszłości.
- **Konfiguracja:** 
  - Opublikuj konfigurację scouta: `php artisan vendor:publish --provider="Laravel\Scout\ScoutServiceProvider"`.
  - Dodaj `SCOUT_QUEUE=true` do `.env` oraz `.env.example`.

### Krok 2: Naprawa Policies i Autoryzacji [BE]
- Zmień `$user->hasPermission()` na `$user->hasPermissionTo()` we wszystkich metodach w `app/Policies/UserPolicy.php` oraz `app/Policies/RolePolicy.php`.
- Dodaj `app()[PermissionRegistrar::class]->forgetCachedPermissions();` na początku `RoleAndPermissionSeeder::run()`.

### Krok 3: Optymalizacja Audytu i Relacji [BE]
- **Model `User`:** Popraw relację `auditLogs()` tak, aby używała `morphMany(AuditLog::class, 'auditable', 'model', 'model_id')`.
- **Model `AuditLog`:** Dodaj brakującą relację zwrotną `auditable()` typu `morphTo`.
- **Seeder:** Upewnij się, że `DatabaseSeeder` używa traita `WithoutModelEvents`.

### Krok 4: Re-inicjalizacja i Weryfikacja [TEST]
- Uruchom pełny proces: `php artisan migrate:fresh --seed`.
- Przeindeksuj modele w Meilisearch:
  - `php artisan scout:import "App\Models\Klient"`
  - `php artisan scout:import "App\Models\User"`
- Zweryfikuj poprawność wyszukiwania za pomocą Tinker: `App\Models\Klient::search('NazwaFirmy')->get()`.
