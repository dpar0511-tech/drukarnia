## Plan Naprawczy: Krok 1.0.2 — Baza danych i Użytkownicy (Rozwiązywanie Rozbieżności)

W wyniku dogłębnego audytu kroku "1.0.2 — Baza danych: fundament [DB]" na podstawie dokumentacji `_docs`, zidentyfikowano następujące krytyczne błędy implementacyjne oraz rozbieżności:

### Zidentyfikowane Problemy:
1. **Błędne użycie atrybutów PHP 8 w Eloquent (Krytyczne):**
   W modelach `App\Models\User` oraz `App\Models\Klient` zastosowano nieistniejące atrybuty `#[Fillable]` oraz `#[Hidden]`. Laravel wymaga tradycyjnych właściwości chronionych (`$fillable`, `$hidden`). Obecny stan uniemożliwia jakiekolwiek zapisy do bazy danych (MassAssignmentException).
2. **Niespójna konfiguracja Spatie Permission (Średnie):**
   Mimo stworzenia własnego modelu `App\Models\Role`, plik `config/permission.php` wciąż wskazuje na domyślny model Spatie. Powoduje to błędy przy przypisywaniu uprawnień i ról.
3. **Blokada zdarzeń audytowych podczas seedowania (Średnie):**
   `DatabaseSeeder` używa trait-a `WithoutModelEvents`, co uniemożliwia zarejestrowanie utworzenia administratora przez `AuditLogObserver`. W systemie ERP audyt musi działać od pierwszego rekordu.
4. **Niezgodność ze schematem bazy danych (Niskie):**
   Migracja `users` nie respektuje limitów długości kolumn `imie` i `nazwisko` (100 znaków) określonych w `04_DATABASE_SCHEMA.md`.

### Szczegółowy Plan Implementacji:

#### 1. Refaktoryzacja Modeli (Naprawa atrybutów)
- **Plik:** `app/Models/User.php`
  - Usunięcie `use Illuminate\Database\Eloquent\Attributes\Fillable;` i `Hidden`.
  - Zamiana `#[Fillable(...)]` na `protected $fillable = [...];`.
  - Zamiana `#[Hidden(...)]` na `protected $hidden = [...];`.
- **Plik:** `app/Models/Klient.php`
  - Analogiczne zmiany (usunięcie atrybutu `#[Fillable]` i importu, dodanie właściwości `$fillable`).

#### 2. Synchronizacja konfiguracji Uprawnień
- **Plik:** `config/permission.php`
  - Zmiana `'role' => Spatie\Permission\Models\Role::class` na `'role' => App\Models\Role::class`.

#### 3. Aktywacja logów audytu w procesie wstępnym
- **Plik:** `database/seeders/DatabaseSeeder.php`
  - Usunięcie trait-a `WithoutModelEvents`, aby umożliwić działanie observerów podczas inicjalizacji bazy.

#### 4. Korekta migracji (Zgodność z ADR/Schema)
- **Plik:** `database/migrations/0001_01_01_000000_create_users_table.php`
  - Zmiana definicji kolumn na: `$table->string('imie', 100)->nullable();` oraz `$table->string('nazwisko', 100)->nullable();`.

#### 5. Weryfikacja
- Uruchomienie `php artisan migrate:fresh --seed`.
- Sprawdzenie tabeli `audit_logs` pod kątem wpisu o utworzeniu administratora.
- Test jednostkowy tworzenia użytkownika w celu potwierdzenia poprawnego działania `$fillable`.
