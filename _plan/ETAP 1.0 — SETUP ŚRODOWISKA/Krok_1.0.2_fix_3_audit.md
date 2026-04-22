# Plan Naprawczy: Krok 1.0.2 — Baza danych: fundament [DB] (Głęboki Audyt)

W wyniku ponownego, dogłębnego audytu kroku "1.0.2 — Baza danych: fundament [DB]" na podstawie dokumentacji `_docs/04_DATABASE_SCHEMA.md` oraz rzeczywistego stanu bazy kodu, wykryto **krytyczne błędy implementacyjne**. Poprzedni plan zawierał nieaktualne lub błędne tezy (np. o atrybutach PHP 8 `#[Fillable]`, których w kodzie nie ma). Poniżej znajduje się rzeczywista lista zidentyfikowanych rozbieżności i bugów.

## Zidentyfikowane Problemy (Rzeczywiste Bugi):

1. **Krytyczny Błąd Autoryzacji (Broken Policies):**
   W plikach `App\Policies\UserPolicy` oraz `App\Policies\RolePolicy` używana jest nieistniejąca metoda `$user->hasPermission(...)`. Została ona usunięta we wcześniejszych krokach naprawczych jako przestarzała. Pakiet `spatie/laravel-permission` (zgodnie z `00_MASTER_PLAN_CONSOLIDATED.md`) wymaga stosowania wbudowanej metody `hasPermissionTo(...)`. Obecny kod powoduje `BadMethodCallException` przy każdej próbie weryfikacji uprawnień.

2. **Brak Czyszczenia Cache'u Spatie w Seederach (Błąd Konfiguracji):**
   Plik `database/seeders/RoleAndPermissionSeeder.php` tworzy role i uprawnienia, ale zapomina o wyczyszczeniu cache'u. Zgodnie z oficjalną dokumentacją Spatie, jest to obowiązkowy krok przed tworzeniem ról/uprawnień w seederach, aby uniknąć trudnych do namierzenia błędów uwierzytelniania w kolejnych etapach oraz w testach.

3. **Spamowanie Tabeli Audytu (Brak Zabezpieczeń w Seederach):**
   Skrypt inicjujący `database/seeders/DatabaseSeeder.php` odpala tworzenie ról, uprawnień, klientów i administratora bez deaktywacji zdarzeń modeli. Powoduje to, że `AuditLogObserver` niepotrzebnie loguje setki wpisów "systemowych" do tabeli `activity_logs` podczas komendy `db:seed`, w których m.in. `user_id` i `ip_address` są równe `null`. Logi audytowe powinny śledzić wyłącznie akcje rzeczywistych użytkowników ERP.

4. **Niepoprawne Polimorficzne Relacje Audytu (Dług Technologiczny):**
   Model `User` posiada metodę `auditLogs()`, która manualnie buduje zapytanie bazodanowe (`hasMany()->where('model', self::class)`), pomijając wbudowane mechanizmy Laravel dla relacji polimorficznych (`morphMany`). Z kolei model `AuditLog` nie posiada relacji zwrotnej (`morphTo`).

## Szczegółowy Plan Implementacji (Checklist)

### Krok 1: Naprawa Krytycznych Błędów w Policies [BE]
- **Plik:** `app/Policies/UserPolicy.php`
  - Zamień wszystkie wystąpienia `$user->hasPermission(...)` na `$user->hasPermissionTo(...)`.
- **Plik:** `app/Policies/RolePolicy.php`
  - Zamień wszystkie wystąpienia `$user->hasPermission(...)` na `$user->hasPermissionTo(...)`.

### Krok 2: Naprawa mechanizmu Cache dla Uprawnień [DB]
- **Plik:** `database/seeders/RoleAndPermissionSeeder.php`
  - Dodaj na samym początku metody `run()` instrukcję:
    `app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();`

### Krok 3: Optymalizacja Seedowania (Zapobieganie Spamowi Audytu) [BE]
- **Plik:** `database/seeders/DatabaseSeeder.php`
  - Zaimportuj i dodaj trait `Illuminate\Database\Console\Seeds\WithoutModelEvents` do klasy seeder'a głównego. Zapewni to czystą tabelę `activity_logs` podczas pierwszego postawienia systemu, ignorując logi deweloperskie i systemowe.

### Krok 4: Wdrożenie Poprawnych Relacji Polimorficznych [BE]
- **Plik:** `app/Models/User.php`
  - Zmodyfikuj metodę `auditLogs()` tak, aby używała natywnej relacji:
    `return $this->morphMany(AuditLog::class, 'auditable', 'model', 'model_id');`
- **Plik:** `app/Models/AuditLog.php`
  - Dodaj brakującą relację zwrotną:
    `public function auditable() { return $this->morphTo(__FUNCTION__, 'model', 'model_id'); }`

### Krok 5: Weryfikacja [TEST]
- [ ] Wywołaj `php artisan optimize:clear`.
- [ ] Uruchom `php artisan migrate:fresh --seed` i upewnij się, że baza zainicjowała się bez błędów, a tabela `activity_logs` (reprezentowana przez model `AuditLog`) jest na start pusta.
- [ ] Opcjonalnie: Zweryfikuj, że autoryzacja w widokach/endpointach działa poprawnie i nie powoduje rzucania wyjątków przez Policies.
