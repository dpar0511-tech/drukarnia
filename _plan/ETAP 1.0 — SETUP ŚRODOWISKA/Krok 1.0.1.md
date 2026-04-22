## Plan: Krok 1.0.1 — Inicjalizacja projektu & UX/UI Blueprint

TL;DR: Skonfigurowanie infrastruktury backendowej (Laravel 11, PostgreSQL, Redis) oraz frontendowej (Vue 3, Inertia v3, shadcn-vue). Od pierwszego dnia wdrażamy skalowalną architekturę UX/UI, obsługę real-time (Reverb) i rygorystyczne zasady logiki biznesowej.

**Steps**
1. **Środowisko Backend**
   - `composer create-project laravel/laravel drukarnia-erp`
   - Setup `.env` (PostgreSQL 16, Redis dla Cache/Queue/Session, MinIO, Reverb, Horizon).
   - Setup Docker Compose dla wyżej wymienionych usług.
   - Instalacja core pakietów (Spatie, Horizon, Reverb, Sanctum, Tus-PHP).
2. **Środowisko Frontend (Inertia v3 & shadcn-vue)**
   - Instalacja Vue 3, `@inertiajs/vue3`, Tailwind CSS v3.
   - Inicjalizacja `shadcn-vue` (styl "New York", kolor "Slate", `cssVariables: true`).
   - Aliasy w `tsconfig.json` i `vite.config.js`.
3. **Konfiguracja Architektury Cross-modułowej (Real-time & State)**
   - Middleware `HandleInertiaRequests`: wstrzyknięcie globalnych danych (`auth.user`, liczba powiadomień, system flash messages).
   - Laravel Echo + Reverb: konfiguracja nasłuchiwania na frontendzie dla powiadomień real-time.
4. **Zbudowanie UI/UX Blueprintów**
   - Stworzenie `resources/js/Layouts/AppLayout.vue` z podziałem na nawigację (sidebar) i topbar (wyszukiwarka + notyfikacje).
   - Instalacja bazowych komponentów shadcn-vue (`button`, `dropdown-menu`, `toast`, `form`, `card`).
5. **Tuning Biznesowy & Wyglądu (Best Practices)**
   - *Backend*: Aktywacja `Model::shouldBeStrict()` w `AppServiceProvider` zapobiegająca N+1 queries i silent discards.
   - *Backend*: Skonfigurowanie Horizon pod kątem retries (exponential backoff w `config/queue.php`).
   - *Frontend*: Przygotowanie obsługi Deferred Props i `<Form>` component dla błyskawicznego UX.

**Relevant files**
- `app/Providers/AppServiceProvider.php` — Dodanie `Model::shouldBeStrict()` dla bezpieczeństwa.
- `app/Http/Middleware/HandleInertiaRequests.php` — Udostępnianie globalnego stanu (notyfikacje, użytkownik).
- `resources/js/Layouts/AppLayout.vue` — Blueprint dla całej aplikacji (operator/admin).
- `bootstrap/app.php` — Centralna rejestracja middleware i wyjątów (Laravel 11 style).

**Verification**
1. Uruchom kontenerów bazy i redisa przez `docker-compose up -d`.
2. Uruchomienie `php artisan serve`, `php artisan reverb:start`, i `npm run dev`.
3. Sprawdzenie Inertia render i czy ciemny motyw z shadcn-vue ładuje się poprawnie.
4. Test wysłania testowego zdarzenia (Event) i weryfikacja odbioru przez Laravel Echo.

**Decisions**
- Użycie Inertia v3 do eliminacji konieczności tworzenia osobnego REST API dla frontendu (ADR-001).
- Całkowity zakaz customowego CSS dla UI (wymuszone użycie shadcn-vue dla spójności wg dokumentacji).
- Zastosowanie struktury katalogów i routingu wymuszonej przez Laravel 11.

**Further Considerations**
1. Czy skonfigurować autoryzację z użyciem Laravel Fortify już na tym etapie, czy potraktować to jako osobny podkrok w module Access Control (1.1)? Rekomenduję zachować czystą inicjalizację, a Auth zrobić w 1.1.