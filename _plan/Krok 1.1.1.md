## Plan: Krok 1.1.1 Backend Auth & UX/UI Blueprint

Wdrożenie uwierzytelniania na bazie Laravel Fortify, połączenie RBAC (Spatie) i Inertia.js (shadcn-vue), wraz z routingiem zależnym od roli użytkownika (Kros-modułowa interakcja).

**Steps**

*Faza 1: Backend & DB (Fortify, Modele, Logika)*
1. Instalacja i publikacja Fortify (`composer require laravel/fortify`, `php artisan vendor:publish...`).
2. Konfiguracja `config/fortify.php`: włączenie widoków (obsługiwanych przez Inertię). Opcjonalnie przygotowanie pod 2FA dla Menedżerów/Adminów.
3. Wdrożenie customowej logiki w `FortifyServiceProvider`:
   - Bindowanie widoków np. `Fortify::loginView(fn () => Inertia::render('Auth/Login'));`.
   - Nadpisanie `Fortify::authenticateUsing` aby sprawdzało pole `aktywny` w tabeli `users` (nieaktywni = zablokowany dostęp).
4. *Event Listeners:* Stworzenie `UpdateLastLogin` podpiętego pod event `Illuminate\Auth\Events\Login`, by nadpisywać czas ostatniego logowania w `users.last_login_at`.
5. Integracja z modułem audytu: Podpięcie powiadomień/logowania na logowanie/wylogowanie przez `spatie/laravel-activitylog` (Security/Audyt).

*Faza 2: Frontend & UX/UI Blueprint (shadcn-vue & Inertia)*
1. Skonstruowanie struktury stron i layoutów:
   - `GuestLayout.vue`: Minimalistyczny layout (bez bocznego menu).
   - `AppLayout.vue`: Pusty szkielet dla przyszłych modułów: z belką górną i lewym menu nawigacyjnym.
2. Zbudowanie `Auth/Login.vue` z wykorzystaniem `shadcn-vue` (w szczególności: `FormField`, `Input`, `Button`).
   - Wdrożenie Inertia `useForm` z obsługą błędów po stronie serwera.
3. Utworzenie inteligentnego przekierowania po logowaniu w `RouteServiceProvider` lub `HomeController`:
   - Jeśli rola = Klient → `/portal` (Moduł 17)
   - Jeśli rola = Operator → `/kanban` (Moduł 9)
   - Jeśli rola = Admin/Menedżer → `/dashboard` (Moduł 16)

**Relevant files**
- `composer.json` — instalacja `laravel/fortify`
- `config/fortify.php` i `app/Providers/FortifyServiceProvider.php` — konfiguracja auth i bindowanie Inertii.
- `app/Listeners/UpdateLastLogin.php` — zapis `last_login_at`.
- `routes/web.php` — definicja `DashboardController` i powrotu dla Fortify.
- `resources/js/Layouts/GuestLayout.vue` — UI dla strefy niezalogowanej (Blueprint).
- `resources/js/Pages/Auth/Login.vue` — interfejs logowania (shadcn-vue Form).
- `app/Http/Controllers/DashboardController.php` — inteligentny routing po roli.

**Verification**
1. Test (PHPUnit): `php artisan test --filter LoginTest` — czy użytkownik nieaktywny otrzymuje błąd (403 lub Validation error).
2. Test (PHPUnit): Czy poprawne logowanie aktualizuje pole `last_login_at`.
3. Manualne testy: Logowanie użytkownikiem z bazowego seedera (`AdminUserSeeder.php`) → poprawne wejście i przechwycenie danych przez Inertia.

**Decisions & Tuning (Biznes & UX)**
- **Tuning Biznesowy:** Pole `aktywny` pozwala zablokować pracowników lub klientów B2B (np. w przypadku zaległości finansowych). Odcięcie w `authenticateUsing` zmniejsza ryzyko wycieku danych.
- **Tuning Wyglądu (UX Blueprint):** Interfejs "Split Screen" - lewa strona to atrakcyjna grafika branżowa / komunikaty systemowe (np. "System Drukarni: Przerwa techniczna o 22:00"), prawa to czysty, biały (lub ciemny w trybie dark mode z `shadcn-vue`) formularz z wyraźnymi przyciskami (Primary Button) i obsługą enter-to-submit.
- **Kros-modulność:** Logowanie to "wejście" dla Modułu 1 (System Core) i Modułu 13 (Access Control). Po udanym uwierzytelnieniu aktywowany jest "Communication Hub" (wszystkie powiadomienia WebSocket startują).

**Further Considerations**
1. Czy włączyć 2FA (Two Factor Authentication) w pierwszej fazie domyślnie dla ról wyższych niż 'Klient', dla zachowania rygoru bezpieczeństwa (np. używając wbudowanego 2FA z Fortify)?