## Plan: Krok 1.0.2 — Baza danych i Użytkownicy (UX/UI & DB)

Fundament dla zarządzania dostępem (Moduł 13). Rezygnujemy z niestandardowego pola JSONB dla ról (zaproponowanego w 04_DATABASE_SCHEMA.md) na rzecz natywnego pakietu `spatie/laravel-permission`. Rozszerzamy tabelę użytkowników pod kątem powiązań między modułami (CRM, Produkcja) oraz projektujemy UI/UX panelu administracyjnego z wykorzystaniem `shadcn-vue`.

**Kroki (Steps)**
1. **Instalacja i konfiguracja pakietu Spatie**
   - Publikacja migracji `spatie/laravel-permission` oraz konfiguracja.
   - *Tuning logiki:* Rezygnacja z własnej tabeli `roles` z kolumną `permissions JSONB`. Zamiast tego używamy sprawdzonych tabel Spatie (`roles`, `permissions`, `model_has_roles`), które są automatycznie buforowane (cache) i współpracują z natywnymi mechanizmami Laravel Gates.
2. **Rozszerzenie migracji `users` (Logika biznesowa i Bezpieczeństwo)**
   - Dodanie: `imie`, `nazwisko`, `aktywny` (boolean, domyślnie: true), `last_login_at` (timestamp).
   - Dodanie `SoftDeletes` — krytyczne dla zachowania spójności `spatie/laravel-activitylog` (aby akcje zwolnionych pracowników nie stawały się osieroconymi rekordami).
   - *Interakcja między modułami:* Dodanie `klient_id` (FK nullable) w celu powiązania z Modułem 2 (CRM). Pozwoli to pracownikom klientów (B2B) logować się do Portalu Klienta (Moduł 17).
3. **Modele i Enums**
   - Model `User`: podłączenie traitów `HasRoles`, `SoftDeletes`. Utworzenie relacji `belongsTo(Klient::class)`. Dodanie lokalnego scope'a `scopeActive(Builder $q)` w celu szybkiego odfiltrowywania zablokowanych kont.
   - Utworzenie `App\Enums\SystemRole` ze stałymi wartościami: `Admin`, `Menedzer`, `Projektant`, `Operator`, `Ksiegowosc`, `Klient`.
4. **Seedery fundamentu**
   - `RoleAndPermissionSeeder`: tworzy wszystkie 6 ról na bazie Enuma i przypisuje podstawową pulę uprawnień (np. `manage_orders`, `operate_machines`).
   - `AdminUserSeeder`: tworzy `admin@drukarnia.local` i przypisuje mu rolę `Admin`.
5. **Backend API / Kontrolery (Wydajność)**
   - Utworzenie `UserController` i `UserResource`.
   - *Tuning wydajności:* Wykorzystanie Eager Loadingu (`User::with(['roles', 'klient'])->paginate()`) w celu uniknięcia problemu N+1 przy wyświetlaniu listy użytkowników.
6. **Architektura UI / UX (Vue 3 + shadcn-vue Blueprints)**
   - **Widok ogólny (DataTable):** Utworzenie komponentu tabeli pracowników i klientów. Kolumny: "Użytkownik" (Awatar + Email), "Rola" (Badge), "Firma" (Link do CRM, jeśli to Klient), "Status" (`Switch` aktywny/zablokowany), "Akcje" (Dropdown).
   - **Kodowanie kolorami (Tuning wyglądu):** Rola `Admin` — Destructive Badge, `Menedzer` — Primary Badge, `Operator` — Secondary, `Klient` — Outline. Pozwala to na wizualne oddzielenie poziomów dostępu.
   - **Formularz zaproszenia (Dialog + AutoForm):** Zamiast zwykłego tworzenia użytkownika z hasłem, przycisk "Zaproś" wysyła e-mail z linkiem do ustawienia hasła. Jeśli wybrano rolę `Klient`, pojawia się pole typu Combobox (Command z shadcn) do wyszukiwania firmy z CRM.
   - **Zarządzanie Rolami (Matrix):** Utworzenie dla Super Admina widoku macierzy (Wiersze - Uprawnienia, Kolumny - Role) z checkboxami, umożliwiającej szybką, wizualną korektę polityk uprawnień.

**Odnośne pliki (Relevant files)**
- `database/migrations/xxxx_create_users_table.php` — dodanie kolumn, `klient_id` oraz `SoftDeletes`.
- `app/Models/User.php` — konfiguracja relacji i traitów.
- `database/seeders/RoleAndPermissionSeeder.php` — tworzenie ról i uprawnień.
- `app/Enums/SystemRole.php` — deklaracja ról.
- `resources/js/Pages/Admin/Users/Index.vue` — implementacja interfejsu tabeli.

**Weryfikacja (Verification)**
1. Napisanie `UserRoleTest.php` (Pest lub PHPUnit) w celu potwierdzenia braku problemu N+1 podczas pobierania listy użytkowników wraz z ich rolami.
2. Weryfikacja autoryzacji: jeśli administrator ustawi `aktywny = false` za pomocą przełącznika w UI, sesja tego użytkownika jest natychmiast anulowana, a kolejna próba logowania zostaje odrzucona przez odpowiedni Middleware.

**Decyzje (Decisions)**
- **Spatie zamiast niestandardowej logiki**: Zrezygnowanie z własnego pola JSONB oraz `role_id` na rzecz tabel z pakietu redukuje dług technologiczny i upraszcza integrację z mechanizmem Laravel Gates.
- **`klient_id` w tabeli Users**: Unifikuje system uwierzytelniania dla pracowników ERP oraz klientów B2B, tworząc w ten sposób fundament dla Modułu 17 (Shop & Portal).

**Dalsze rozważania (Further Considerations)**
1. Czy istnieje potrzeba, aby jeden użytkownik (np. freelancer-projektant) był przypisany do kilku firm-klientów jednocześnie? Czy powiązanie z `klienci` będzie ściśle typu "Jeden do Wielu" (obecnie zaplanowano jako `belongsTo` - 1 użytkownik = 1 firma)?