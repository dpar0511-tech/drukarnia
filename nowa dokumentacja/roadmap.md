# DRUKARNIA ERP — ROADMAP IMPLEMENTACJI (v2, 2026-04-24)

> **Status:** aktywny pokrokowy plan implementacyjny zgodny z [`nowa dokumentacja/`](./nowa%20dokumentacja/00_INDEX.md).
> **Poprzednia wersja:** zastąpiona. Zobacz Appendix E dla listy różnic.
> **Każdy numer kroku = jeden zamknięty commit lub PR.**
> **Stack:** Laravel 11 · PHP 8.3 · Vue 3 · Inertia v3 · PostgreSQL 16 · Redis 7 · Reverb · shadcn-vue · **lokalne sklepowanie plików** (bez MinIO/S3).

---

## SPIS TREŚCI

0. [Metadane, legenda, konwencje](#metadane)
1. [FAZA 0 — Scaffolding (3 dni)](#faza-0)
2. [FAZA 1 — MVP (10 tygodni)](#faza-1)
3. [FAZA 2 — Portal + Approvals markup + Pełny pricing UI + Full Finance (8–10 tygodni)](#faza-2)
4. [FAZA 3 — Shop + Design Editor + Analytics + Complaints full (10–12 tygodni)](#faza-3)
5. [FAZA 4 — Enterprise (otwarte)](#faza-4)
6. [Cross-cutting concerns (obowiązują w każdej fazie)](#cross-cutting)
7. [Struktura katalogów (referencja)](#struktura)
8. [Tracking postępu](#tracking)
9. [Appendiksy A–E](#appendiksy)

---

<a id="metadane"></a>
## 0. METADANE I LEGENDA

### 0.1. Źródło prawdy

Źródłem prawdy jest folder `nowa dokumentacja/` (14 plików, ~6500 linii, stan 2026-04-23):

| Nr | Plik | Link |
|----|------|------|
| 00 | INDEX | [00_INDEX.md](./nowa%20dokumentacja/00_INDEX.md) |
| 01 | Wizja i założenia | [01_WIZJA_I_ZALOZENIA.md](./nowa%20dokumentacja/01_WIZJA_I_ZALOZENIA.md) |
| 02 | Stack technologiczny | [02_STACK_TECHNOLOGICZNY.md](./nowa%20dokumentacja/02_STACK_TECHNOLOGICZNY.md) |
| 03 | Architektura systemu | [03_ARCHITEKTURA_SYSTEMU.md](./nowa%20dokumentacja/03_ARCHITEKTURA_SYSTEMU.md) |
| 04 | Model domenowy i baza | [04_MODEL_DOMENOWY_I_BAZA_DANYCH.md](./nowa%20dokumentacja/04_MODEL_DOMENOWY_I_BAZA_DANYCH.md) |
| 05 | Moduły biznesowe | [05_MODULY_BIZNESOWE.md](./nowa%20dokumentacja/05_MODULY_BIZNESOWE.md) |
| 06 | Workflow i eventy | [06_WORKFLOW_I_EVENTY.md](./nowa%20dokumentacja/06_WORKFLOW_I_EVENTY.md) |
| 07 | Silnik cenowy | [07_SILNIK_CENOWY_INTEGRACJA.md](./nowa%20dokumentacja/07_SILNIK_CENOWY_INTEGRACJA.md) |
| 08 | DAMS lokalny | [08_DAMS_LOKALNE_PRZECHOWYWANIE_PLIKOW.md](./nowa%20dokumentacja/08_DAMS_LOKALNE_PRZECHOWYWANIE_PLIKOW.md) |
| 09 | Szybki start MVP | [09_SZYBKI_START_MVP_SCAFFOLDING.md](./nowa%20dokumentacja/09_SZYBKI_START_MVP_SCAFFOLDING.md) |
| 10 | Integracje zewnętrzne | [10_INTEGRACJE_ZEWNETRZNE.md](./nowa%20dokumentacja/10_INTEGRACJE_ZEWNETRZNE.md) |
| 11 | Bezpieczeństwo i RBAC | [11_BEZPIECZENSTWO_I_RBAC.md](./nowa%20dokumentacja/11_BEZPIECZENSTWO_I_RBAC.md) |
| 12 | Strategia testowa | [12_STRATEGIA_TESTOWA.md](./nowa%20dokumentacja/12_STRATEGIA_TESTOWA.md) |
| 13 | Plan wdrożenia i DevOps | [13_PLAN_WDROZENIA_I_DEVOPS.md](./nowa%20dokumentacja/13_PLAN_WDROZENIA_I_DEVOPS.md) |
| 14 | Glosariusz i referencje | [14_GLOSARIUSZ_I_REFERENCJE.md](./nowa%20dokumentacja/14_GLOSARIUSZ_I_REFERENCJE.md) |

### 0.2. Legenda tagów

| Tag | Znaczenie |
|-----|-----------|
| `[BE]` | Backend (Laravel, PHP) |
| `[FE]` | Frontend (Vue 3, Inertia) |
| `[DB]` | Migracja / zmiana schematu DB |
| `[CFG]` | Konfiguracja / DevOps |
| `[TEST]` | Testy (Pest / PHPUnit) |
| `[INT]` | Integracja zewnętrzna (Saloon adapter) |
| `[UI]` | UI/UX na shadcn-vue (komponenty, strony) |
| `[SEC]` | Bezpieczeństwo (RBAC, webhooks, audit) |
| `[DEVOPS]` | Deploy, CI/CD, supervisor, nginx |
| `[DOC]` | Dokumentacja / README |

### 0.3. Konwencje kodu (bezwzględne)

- **FQCN:** `App\Models\{Domena}\{CamelCase}` — np. `App\Models\Orders\Zamowienie`. Pricingu: `App\Models\{Core,Parameters,Products,Exclusions}` (**ang.**).
- **Serwisy:** `App\Services\{Domena}\{NazwaSerwisu}` — np. `App\Services\Pricing\PricingPipeline`.
- **Eventy:** `App\Events\{CzasPrzeszły}` — np. `OrderCreated`, `PriceCalculated`, `FileUploaded`.
- **Listenery:** `App\Listeners\{NazwaAkcji}` — np. `CreateProformaAfterPricing`, `BroadcastOrderStatusToClient`.
- **Jobs:** `App\Jobs\{Nazwa}Job` — np. `GenerateThumbnailsJob`, `ProcessUploadedFileJob`.
- **Value Objects:** `readonly class` PHP 8.2+, w `App\Services\{Domena}\ValueObjects\*`.
- **Traits:** `App\Traits\{Nazwa}Trait` — np. `CostPriceTrait`, `Auditable`.
- **Enums:** `App\Enums\{Nazwa}` — PHP 8.1+ native enums (`ProductType`, `EngineType`, `PartRole`).
- **DI:** kontrakty (interfejsy) wiązane w `AppServiceProvider::register()`.
- **Migracje:** `2026_MM_DD_HHMMSS_{opisowy_sufiks_po_ang}.php`.
- **Tabele:** domena biznesowa — polskie `snake_case` (`zamowienia`, `pozycje_zamowien`, `kalkulacje_ceny`). Warstwa pricingu — **angielskie 1:1 z kodem silnika** (`pricing_rules`, `zadruk_option`, `material_size`, `exclusion_rule`, `product_parameter_option`).
- **Pieniądze:** **wyłącznie `brick/math` `BigDecimal`** ze scale 10 (lub `bcmath` z `bcscale(10)`). **Nigdy `float`.**
- **UI (polityka bezwzględna z [02_STACK_TECHNOLOGICZNY.md](./nowa%20dokumentacja/02_STACK_TECHNOLOGICZNY.md) sekcja 2):**
  - Komponenty wyłącznie z `resources/js/Components/ui/` (shadcn-vue). Brakujące → `npx shadcn-vue@latest add <name>`.
  - Ikony wyłącznie `lucide-vue-next`.
  - Kolory wyłącznie semantyczne (`bg-primary`, `text-muted-foreground`, `border-input`). **Zakaz** `bg-blue-500`, `text-gray-400`.
  - Odstępy: `flex gap-*` / `grid gap-*`. **Zakaz** `space-x-*`, `space-y-*`.
  - Kwadraty/ikony: `size-*` (np. `size-10`). **Zakaz** `w-10 h-10`.
  - Formy: `<FormField>` + `<FormItem>` + `<FormLabel>` + `<FormControl>` + `<FormMessage>` — nie ręczne divy.
  - Modale: `<Dialog>` + `<DialogTrigger asChild>` + `<DialogContent>`.
  - `v-model` (nie `:value` + `@update:*`).
  - Dynamiczne klasy: `cn(...)` z `@/lib/utils`.
- **Commit message:** konwencja Conventional Commits (`feat:`, `fix:`, `refactor:`, `test:`, `docs:`, `chore:`).
- **Pint:** `vendor/bin/pint --dirty --format agent` przed każdym commitem. CI blokuje `pint --test`.

### 0.4. Branching

- `main` — chroniony, zielony CI wymagany.
- `feat/<numer-kroku>-<krotki-opis>` — gałąź per krok roadmapy (np. `feat/1.4-fsm-19-states`).
- `release/<semver>` — przygotowanie wydania.
- PR → review → squash merge do `main` → auto-deploy staging.

### 0.5. Role i osoby

- 1× full-time backend/fullstack dev (główny).
- 1× zewnętrzny reviewer (kod + pricing test coverage) — audyt co 2 tygodnie.
- Właściciel drukarni — akceptant UX (tydzień 4, 10, 20, 32).

---

<a id="faza-0"></a>
## FAZA 0 — SCAFFOLDING (3 DNI)

**Cel:** w 72h przejść od pustego folderu do działającej aplikacji z auth (Breeze), panelem admin (Filament), komponentami shadcn-vue, Docker Compose, CI i `composer run dev`. Od Dnia 4 zaczyna się pisanie logiki biznesowej.
**Źródło:** [09_SZYBKI_START_MVP_SCAFFOLDING.md](./nowa%20dokumentacja/09_SZYBKI_START_MVP_SCAFFOLDING.md).

### Etap 0.1 — Laravel 11 + Breeze (Vue + Inertia + Pest + TS + Dark) `[CFG]`

- [ ] **0.1.1** `composer create-project laravel/laravel drukarnia-erp "11.*"`
- [ ] **0.1.2** `cd drukarnia-erp && git init && git add . && git commit -m "chore: initial laravel 11"`
- [ ] **0.1.3** `composer require laravel/breeze --dev`
- [ ] **0.1.4** `php artisan breeze:install vue --inertia --ssr --typescript --pest --dark`
  - Efekt: auth stack (Login/Register/ForgotPassword/Reset/EmailVerification/ProfileUpdate), Tailwind 3, `cn()`, `AuthenticatedLayout.vue`, `GuestLayout.vue`, Pest, TS, dark toggle.
- [ ] **0.1.5** `npm install && npm run build` → sanity check.
- [ ] **0.1.6** `.env` — baza danych PostgreSQL (`DB_CONNECTION=pgsql`, `DB_HOST=db`), Redis (`REDIS_HOST=redis`), mail (`MAIL_MAILER=smtp`, `MAIL_HOST=mailpit`, `MAIL_PORT=1025`).
- [ ] **0.1.7** Commit: `feat(scaffold): laravel 11 + breeze vue inertia`.

### Etap 0.2 — shadcn-vue init + 30 komponentów `[UI]`

- [ ] **0.2.1** `npx shadcn-vue@latest init` (opcje: style `default`, base color `neutral`, CSS variables `yes`).
- [ ] **0.2.2** Batch add komponentów:
  ```bash
  npx shadcn-vue@latest add \
    button card dialog form input label select sheet table tabs toast \
    dropdown-menu avatar badge skeleton command separator alert tooltip \
    scroll-area popover checkbox radio-group switch textarea calendar date-picker \
    breadcrumb navigation-menu progress hover-card
  ```
- [ ] **0.2.3** Usunięcie Breeze komponentów kolidujących (`InputError.vue`, `PrimaryButton.vue`, `SecondaryButton.vue`, `DangerButton.vue`, `TextInput.vue`, `InputLabel.vue`, `Checkbox.vue`, `Dropdown.vue`, `DropdownLink.vue`, `Modal.vue`, `NavLink.vue`, `ResponsiveNavLink.vue`, `ApplicationLogo.vue`) → wszystkie strony Breeze przepisane na shadcn-vue (Button/Input/Label/FormField/Dialog/NavigationMenu).
- [ ] **0.2.4** Commit: `feat(ui): shadcn-vue init + 30 komponentów + breeze migration`.

### Etap 0.3 — Pakiety core backend `[BE] [CFG]`

- [ ] **0.3.1** `composer require` (jeden command, versioned):
  ```bash
  composer require \
    inertiajs/inertia-laravel:"^2.0" \
    tightenco/ziggy:"^2.0" \
    laravel/horizon:"^5.0" \
    laravel/reverb:"^1.0" \
    laravel/fortify:"^1.0" \
    laravel/sanctum:"^4.0" \
    laravel/scout:"^11.1" \
    meilisearch/meilisearch-php:"^1.0" \
    spatie/laravel-permission:"^6.0" \
    spatie/laravel-model-states:"^2.0" \
    spatie/laravel-activitylog:"^4.0" \
    spatie/laravel-medialibrary:"^11.0" \
    spatie/pdf-to-image:"^3.0" \
    intervention/image:"^3.0" \
    ankitpokhrel/tus-php:"^2.0" \
    saloonphp/saloon:"^4.0" \
    brick/math:"^0.12" \
    league/csv:"^9.0"
  ```
- [ ] **0.3.2** `composer require --dev`:
  ```bash
  composer require --dev \
    laravel/pint:"^1.0" \
    laravel/pail:"^1.0" \
    laravel/telescope:"^5.0" \
    nunomaduro/collision:"^8.0" \
    pestphp/pest-plugin-laravel
  ```
- [ ] **0.3.3** `vendor:publish`:
  ```bash
  php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
  php artisan vendor:publish --provider="Spatie\ActivityLog\ActivityLogServiceProvider"
  php artisan vendor:publish --provider="Spatie\MediaLibrary\MediaLibraryServiceProvider" --tag="medialibrary-migrations"
  ```
- [ ] **0.3.4** Install commands: `horizon:install`, `reverb:install`, `fortify:install`, `sanctum:install`, `scout:install`, `telescope:install`.
- [ ] **0.3.5** `php artisan migrate` (wszystkie pakiety).
- [ ] **0.3.6** Commit: `feat(deps): add core backend packages + publish configs`.

### Etap 0.4 — Docker Compose (dev) `[DEVOPS]`

- [ ] **0.4.1** `docker/app/Dockerfile` — PHP 8.3-fpm + extensions (bcmath, pdo_pgsql, redis, intl, gd, imagick) + Composer + Node 20.
- [ ] **0.4.2** `docker/nginx/default.conf` — reverse proxy → app:9000 + `client_max_body_size 5G` dla tus.
- [ ] **0.4.3** `docker-compose.yml` — services:
  - `app` (PHP-FPM, wolumen bind mount)
  - `nginx` (port 80)
  - `db` (postgres:16, wolumen `pgdata`, port 5432)
  - `redis` (redis:7-alpine, wolumen `redisdata`, port 6379)
  - `meilisearch` (getmeili/meilisearch:v1.11, wolumen `meilidata`, port 7700)
  - `mailpit` (axllent/mailpit, porty 1025+8025)
  - `horizon` (PHP + `php artisan horizon`)
  - `reverb` (PHP + `php artisan reverb:start --host=0.0.0.0 --port=8080`, port 8080)
- [ ] **0.4.4** `.dockerignore` — `vendor/`, `node_modules/`, `.git/`, `storage/*.log`.
- [ ] **0.4.5** `docker compose up -d` — <3 min od zero.
- [ ] **0.4.6** `docker compose exec app php artisan migrate --seed`.
- [ ] **0.4.7** Commit: `feat(devops): docker-compose dev stack`.

### Etap 0.5 — Filament v3 admin `[BE] [UI]`

- [ ] **0.5.1** `composer require filament/filament:"^3.0" -W`.
- [ ] **0.5.2** `php artisan filament:install --panels`.
- [ ] **0.5.3** `php artisan make:filament-user` → admin + hasło z `.env`.
- [ ] **0.5.4** `App\Providers\Filament\AdminPanelProvider`: brand, colors (neutral), `navigationGroups`: Pricing / Domena / System.
- [ ] **0.5.5** Zabezpieczenie: Filament tylko dla roli `Admin` (`canAccessPanel(Panel $panel): bool`).
- [ ] **0.5.6** Sanity check: `https://localhost/admin` → login → pusty dashboard.
- [ ] **0.5.7** Commit: `feat(admin): filament v3 panel`.

### Etap 0.6 — Generacja Filament resources (16 szt.) `[BE] [UI]`

- [ ] **0.6.1** Pricing (13):
  ```bash
  php artisan make:filament-resource GlobalSetting PatternSetting ProjectSetting \
    CustomFormatSetting MediaFormat Format Material ZadrukOption TimeOption \
    PageConfig Parameter SimpleParameter Product --no-interaction
  ```
- [ ] **0.6.2** Domena (4):
  ```bash
  php artisan make:filament-resource Klient Zamowienie DokumentFinansowy Maszyna --no-interaction
  ```
- [ ] **0.6.3** Każdy resource — ~5 min customizacji (columns, filters, validation). W MVP zostawiamy defaulty.
- [ ] **0.6.4** Commit: `feat(admin): 16 filament resources generated`.

### Etap 0.7 — `PricingDemoSeeder` `[BE] [TEST]`

- [ ] **0.7.1** `php artisan make:seeder PricingDemoSeeder`.
- [ ] **0.7.2** Port `seed_demo.py` z `iwp_calc_generator-main` → PHP:
  - 1× `GlobalSetting` (PAPER_BASE_PRICE, PRINT_COST_PER_SHEET, itd.).
  - 1× `PatternSetting` (default multiplier 1.0).
  - 1× `ProjectSetting` (base 50 PLN).
  - 1× `CustomFormatSetting`.
  - 3× `MediaFormat` (SRA3 450×320, A4 297×210, Rolka 610mm).
  - 3× `Material` (130g Kreda, 350g Kreda, Folia Monomeryczna) z `CostPriceTrait` polami.
  - 3× `ZadrukOption` (4/0, 4/4, sublimacja).
  - 2× `TimeOption` (Standard ×1.0, Express ×1.5).
  - 4× `PageConfig` (4+8, 4+12, 4+20, 4+32).
  - 3× `Parameter` + opcje (Uszlachetnianie: brak/mat/błysk/folia soft-touch; Składanie: brak/C/Z; Narożniki: zwykłe/zaokrąglone).
  - 4× Produkty:
    - `Ulotki A4` (calculated, SheetEngine) z parametrami.
    - `Wizytówki` (calculated, SheetEngine).
    - `Banner vinylowy` (calculated, LinearMeterEngine).
    - `Kubki firmowe` (simple + `SimpleProductDiscount`).
- [ ] **0.7.3** `php artisan db:seed --class=PricingDemoSeeder` → sprawdzenie w Filament.
- [ ] **0.7.4** Commit: `test(pricing): demo seeder with 4 products`.

### Etap 0.8 — CI/CD GitHub Actions `[DEVOPS] [TEST]`

- [ ] **0.8.1** `.github/workflows/ci.yml` — services postgres:16 + redis:7 + meilisearch:v1.11; steps:
  1. `actions/checkout@v4`.
  2. `shivammathur/setup-php@v2` z ext: `bcmath, pdo, pgsql, redis, intl, gd, imagick`.
  3. `composer install --prefer-dist --no-progress`.
  4. `cp .env.ci .env && php artisan key:generate && php artisan migrate`.
  5. `vendor/bin/pint --test`.
  6. `./vendor/bin/pest --compact --coverage --min=80` (dla `app/Services/Pricing`).
  7. `actions/setup-node@v4` (20).
  8. `npm ci && npm run build`.
- [ ] **0.8.2** `.env.ci` — stub config dla CI (testowa baza).
- [ ] **0.8.3** `phpunit.xml` — konfiguracja coverage: `app/Services/Pricing/*` threshold 80%.
- [ ] **0.8.4** Branch protection rule na `main`: require CI green.
- [ ] **0.8.5** Commit: `ci: github actions pint + pest + coverage gate`.

### Etap 0.9 — `composer run dev` + Echo + AppLayout `[FE] [CFG]`

- [ ] **0.9.1** `composer.json` scripts:
  ```json
  "dev": [
    "Composer\\Config::disableProcessTimeout",
    "npx concurrently -c '#93c5fd,#c4b5fd,#fb7185,#fcd34d' \"php artisan serve\" \"php artisan queue:listen --tries=1\" \"php artisan reverb:start\" \"npm run dev\" --names=serve,queue,reverb,vite"
  ]
  ```
- [ ] **0.9.2** `npm install laravel-echo pusher-js`.
- [ ] **0.9.3** `resources/js/echo.ts`:
  ```ts
  import Echo from 'laravel-echo';
  import Pusher from 'pusher-js';
  window.Pusher = Pusher;
  window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT,
    forceTLS: false,
    enabledTransports: ['ws', 'wss'],
  });
  ```
- [ ] **0.9.4** Import `echo.ts` w `resources/js/app.ts`.
- [ ] **0.9.5** `resources/js/Layouts/AppLayout.vue` — sidebar + navbar + bell (shadcn-vue: `Sheet`, `Command`, `DropdownMenu`, `Avatar`, `Button`, `Badge`). Nawigacja per rola (`can('orders.view')`, `can('production.view')`).
- [ ] **0.9.6** `resources/js/composables/useAuth.ts`: `user = $page.props.auth.user`; `can(perm: string): boolean`.
- [ ] **0.9.7** `resources/js/composables/useNotifications.ts`: subscribe do `private-user.{id}`, reaktywna lista, licznik nieprzeczytanych.
- [ ] **0.9.8** Commit: `feat(dev): composer run dev + echo + app layout`.

### Etap 0.10 — Checklist „gotowości po 3 dniach" `[DOC]`

- [ ] **0.10.1** `docker compose up -d` w <3 min.
- [ ] **0.10.2** `php artisan serve` odpowiada na `/` (Welcome).
- [ ] **0.10.3** `php artisan migrate` przechodzi.
- [ ] **0.10.4** `php artisan db:seed --class=PricingDemoSeeder` daje dane demo.
- [ ] **0.10.5** `/admin` → login Admin → widać CRUD 16 zasobów.
- [ ] **0.10.6** `npm run dev` serwuje HMR.
- [ ] **0.10.7** `vendor/bin/pest` → zielone (dummy testy Breeze).
- [ ] **0.10.8** GitHub Actions CI przechodzi na świeżym pushu.
- [ ] **0.10.9** Login/Register/ProfileUpdate działają (Breeze).
- [ ] **0.10.10** Sidebar + shadcn-vue komponenty renderują się.
- [ ] **0.10.11** `git tag v0.0.0-scaffolded`.
- [ ] **0.10.12** Commit: `docs: scaffolding checklist complete`.

**Rezultat Fazy 0:** od Dnia 4 możesz pisać logikę biznesową, nie boilerplate.

---

<a id="faza-1"></a>
## FAZA 1 — MVP (10 TYGODNI)

**Cel:** Email → Wycena → Pliki → Produkcja → Wysyłka. Drukarnia obsługuje zamówienia end-to-end bez papieru.
**Moduły aktywne:** RBAC (15), CRM (1), Orders (2), Products (3), Pricing (4) — pełny pipeline, DAMS (7), Production (8), Finance (10) — Proforma, Logistics (11) — InPost, CommHub (13), Integrations (16) — GUS/Subiekt stub/P24/InPost.
**Moduły odłożone:** Quotes (5), Approvals z markup (6), Inventory (9) — uproszczony, Complaints (12), Reports/BI (14), Shop (17), Design Editor (18).
**Czas:** 10 tygodni, 1 dev full-time.

### Tydzień 1 — Pricing scaffold + Filament config + seedery

#### Etap 1.1 — Migracje warstwy pricing (angielskie nazwy) `[DB]`

- [ ] **1.1.1** `2026_MM_DD_create_global_settings_table.php` — singleton (SingletonTrait): paper_base_price, print_cost_per_sheet, default_margin_pct, project_base_price, vat_rate_default.
- [ ] **1.1.2** `pattern_settings` — singleton: pattern_multiplier default 1.0, thresholds JSONB.
- [ ] **1.1.3** `project_settings` — singleton: base_project_price, complexity_multipliers JSONB.
- [ ] **1.1.4** `custom_format_settings` — singleton: custom_format_surcharge_pct, max_dimensions.
- [ ] **1.1.5** `media_formats` — id, name, width_mm, height_mm, is_roll (bool), roll_width_mm nullable.
- [ ] **1.1.6** `formats` — id, name, width_mm, height_mm, bleed_mm default 3.
- [ ] **1.1.7** `materials` — id, name, weight_gsm, cost fields z `CostPriceTrait` (`cost_per_sheet`, `cost_per_piece`, `cost_per_item`, `cost_per_kg`, `cost_per_m2`, `cost_per_mb`), `margin_base_pct`, `margin_min_pln`.
- [ ] **1.1.8** `zadruk_options` — id, name, sides (1/2), color_mode (cmyk/rgb/spot), `CostPriceTrait` pola.
- [ ] **1.1.9** `time_options` — id, name, multiplier (DECIMAL 10,4), days_min, days_max.
- [ ] **1.1.10** `page_configs` — id, name, cover_pages, interior_pages.
- [ ] **1.1.11** `parameters` — id, name, slug, role (ENUM: `common`/`cover`/`interior`).
- [ ] **1.1.12** `parameter_options` — id, parameter_id FK, name, slug, `CostPriceTrait` pola, `pattern_multiplier` nullable.
- [ ] **1.1.13** `simple_parameters` / `simple_parameter_options` — analogicznie dla produktów prostych.
- [ ] **1.1.14** `products` — id, name, slug UNIQUE, product_type (ENUM `calculated`/`simple`), engine_type (ENUM `sheet`/`linear_meter`/`multipage` nullable), has_multipage (bool), multipage_type (ENUM nullable), description TEXT, active (bool), `CostPriceTrait` pola, `margin_base_pct`, `margin_min_pln`.
- [ ] **1.1.15** `product_override` — id, product_id FK UNIQUE (1:1), override_fields JSONB (nadpisuje GlobalSetting/PatternSetting per produkt).
- [ ] **1.1.16** Pivots `product_{material,zadruk_option,time_option,page_config}` — z `is_default` bool.
- [ ] **1.1.17** `product_parameter` — pivot z rolą (common/cover/interior) + `is_required` bool.
- [ ] **1.1.18** 🔥 `product_parameter_option` — **PK używany w `CalculationConfig.selectedParameterOptionIds`**. Kolumny: id, product_id FK, parameter_option_id FK, role (ENUM), is_default (bool), price_override DECIMAL nullable.
- [ ] **1.1.19** `product_simple_parameter` / `product_simple_parameter_option` — analogicznie.
- [ ] **1.1.20** `simple_product_discount` — id, product_id FK UNIQUE, discount_base_pct, decay_factor, min_discount_pct.
- [ ] **1.1.21** `exclusion_rule` — id, product_id FK, excluded_pairs JSONB (np. materiał 350g + zadruk 4/4 → wyklucz).
- [ ] **1.1.22** `material_size` — pivot materiał × media_format (custom widths per material).
- [ ] **1.1.23** `kalkulacje_ceny` (polska nazwa) — zamowienie_id FK, pozycja_id FK, pricing_result JSONB (snapshot pełnego `PricingResult`), config JSONB (snapshot `CalculationConfig`), created_at.
- [ ] **1.1.24** `php artisan migrate` + rollback test (`migrate:refresh`).
- [ ] **1.1.25** Commit: `feat(pricing): 24 migrations for pricing layer`.

#### Etap 1.2 — Modele Eloquent + trait `CostPriceTrait` `[BE]`

- [ ] **1.2.1** `App\Traits\CostPriceTrait` — 8 pól jako `$casts` (BigDecimal), metoda `getCostPerUnit(string $unitType): string` (zwraca BigDecimal).
- [ ] **1.2.2** `App\Models\Core\GlobalSetting`, `PatternSetting`, `ProjectSetting`, `CustomFormatSetting` — z `SingletonTrait`.
- [ ] **1.2.3** `App\Models\Parameters\{MediaFormat, Format, Material, ZadrukOption, TimeOption, PageConfig, Parameter, ParameterOption, SimpleParameter, SimpleParameterOption}`.
- [ ] **1.2.4** `App\Models\Products\{Product, ProductOverride, ProductParameterOption, SimpleProductDiscount, MaterialSize}`.
- [ ] **1.2.5** `App\Models\Exclusions\ExclusionRule`.
- [ ] **1.2.6** `App\Enums\{ProductType, EngineType, PartRole, UnitType}`.
- [ ] **1.2.7** Observer `ProductObserver` — walidacje z [07_SILNIK_CENOWY_INTEGRACJA.md](./nowa%20dokumentacja/07_SILNIK_CENOWY_INTEGRACJA.md) sekcja 2: calculated ⇒ engine_type NOT NULL; simple ⇒ engine_type NULL + has_multipage=false + wymaga SimpleProductDiscount; multipage ⇒ multipage_type NOT NULL + has_multipage=true.
- [ ] **1.2.8** Commit: `feat(pricing): models + CostPriceTrait + validators`.

#### Etap 1.3 — Filament resources dopracowane `[UI]`

- [ ] **1.3.1** `ProductResource` z zakładkami: Ogólne / Override / Formaty / Materiały / Zadruki / Parametry / Czasy / Strony / Wykluczenia. Używa `Forms\Components\Tabs` + `Forms\Components\Repeater` dla pivotów.
- [ ] **1.3.2** `MaterialResource` z inline `material_size` (repeater).
- [ ] **1.3.3** `ParameterResource` z nested `ParameterOption` (repeater z `CostPriceTrait` polami).
- [ ] **1.3.4** `GlobalSettingResource` / `PatternSettingResource` / `ProjectSettingResource` — single record edit (`canCreate()=false`, `canDelete()=false`).
- [ ] **1.3.5** Commit: `feat(admin): pricing resources with tabs and repeaters`.

#### Etap 1.4 — Smoke testy Filament `[TEST]`

- [ ] **1.4.1** Pest test: Admin może otworzyć `/admin/products` i utworzyć produkt.
- [ ] **1.4.2** Non-Admin dostaje 403 na `/admin`.
- [ ] **1.4.3** `PricingDemoSeeder` uruchomiony → liczba rekordów zgodna.
- [ ] **1.4.4** Commit: `test(admin): smoke filament access`.

### Tydzień 2 — PricingPipeline: engines + SettingResolver + 25/49 testów

#### Etap 2.1 — Value Objects `[BE]`

- [ ] **2.1.1** `App\Services\Pricing\ValueObjects\CalculationConfig` (`readonly class`) — 18 pól zgodnie z [07_SILNIK_CENOWY_INTEGRACJA.md](./nowa%20dokumentacja/07_SILNIK_CENOWY_INTEGRACJA.md) sekcja 3.2. **Krytyczne:** `selectedParameterOptionIds` to PK tabeli `product_parameter_option` (NIE `parameter_option`).
- [ ] **2.1.2** `App\Services\Pricing\ValueObjects\PricingResult` (`readonly class`) — 13 pól (`engineResult`, `extrasCost`, `productExtraCost`, `totalCost`, `marginAmount`, `basePrice`, `extrasPrice`, `productExtraPrice`, `projectPrice`, `patternMultipliedPrice`, `timeMultiplier`, `finalPriceNet`, `details`).
- [ ] **2.1.3** `App\Services\Pricing\ValueObjects\EngineResult` (`readonly class`) — 6 pól (`unitType`, `unitsConsumed`, `imposition`, `materialCost`, `printCost`, `details`).
- [ ] **2.1.4** Commit: `feat(pricing): value objects`.

#### Etap 2.2 — EngineContract + 3 engines `[BE]`

- [ ] **2.2.1** `App\Services\Pricing\Engines\Contracts\EngineContract`:
  ```php
  interface EngineContract { public function calculate(Product $p, CalculationConfig $c): EngineResult; }
  ```
- [ ] **2.2.2** `App\Services\Pricing\Engines\SheetEngine` — algorytm arkuszowy (imposition, sheets_base, sheets_final z odpadem). Port 1:1 z `_nowy_SILNIK_CENOWY/02_sheet_engine.md`.
- [ ] **2.2.3** `App\Services\Pricing\Engines\LinearMeterEngine` — metry bieżące (dla rolki).
- [ ] **2.2.4** `App\Services\Pricing\Engines\MultipageEngine` — cover + interior sections w `details['sections']`. Port z `_nowy_SILNIK_CENOWY/03_multipage_engine.md`.
- [ ] **2.2.5** `App\Services\Pricing\EngineResolver::get(EngineType $type): EngineContract` — binding w `AppServiceProvider`.
- [ ] **2.2.6** Commit: `feat(pricing): 3 engines + resolver`.

#### Etap 2.3 — SettingResolver + ProjectPageResolver `[BE]`

- [ ] **2.3.1** `App\Services\Pricing\Resolvers\SettingResolver` — kaskada `ProductOverride → PatternSetting → GlobalSetting`. Metoda `resolve(Product $p, string $key): BigDecimal`.
- [ ] **2.3.2** `App\Services\Pricing\Resolvers\ProjectPageResolver` — zwraca `PageConfig` (cover_pages, interior_pages).
- [ ] **2.3.3** Cache: Redis `pricing:setting:{product_id}:{key}` TTL 1h (invalidacja na `ProductSaved` event).
- [ ] **2.3.4** Commit: `feat(pricing): setting + project page resolvers`.

#### Etap 2.4 — Testy Pest: CalculatorTest + SimplePricingTest (25 testów) `[TEST]`

- [ ] **2.4.1** `tests/Feature/Pricing/CalculatorTest.php` — 15 testów z `_nowy_SILNIK_CENOWY/05_tests_calculator.md` (port 1:1 z Django):
  - basic_sheet_engine_single_material
  - sheet_engine_with_bleed
  - sheet_engine_multiple_patterns
  - sheet_engine_with_overrides
  - linear_meter_engine_roll
  - linear_meter_custom_width
  - multipage_engine_basic
  - multipage_engine_cover_interior_different_materials
  - multipage_engine_patterns
  - sheet_engine_exclusion_rule_blocks
  - pricing_with_common_parameters
  - pricing_with_cover_interior_parameters
  - project_price_applied
  - time_multiplier_applied
  - imposition_calculation
- [ ] **2.4.2** `tests/Feature/Pricing/SimplePricingTest.php` — 10 testów:
  - simple_product_base_price
  - simple_product_quantity_discount_exponential
  - simple_product_min_discount_cap
  - simple_product_with_simple_parameters
  - simple_product_no_engine_required
  - simple_product_ignores_has_multipage
  - simple_product_missing_discount_raises
  - simple_product_discount_base_pct
  - simple_product_decay_factor
  - simple_product_snapshot_saved_to_kalkulacje_ceny
- [ ] **2.4.3** Coverage ≥80% na `app/Services/Pricing/Engines/*`.
- [ ] **2.4.4** Commit: `test(pricing): 25/49 tests (calculators + simple)`.

### Tydzień 3 — PricingPipeline 12 kroków + MultipageEngine integracja + 24/49 testów

#### Etap 3.1 — `PricingPipeline::calculate()` — 12 kroków `[BE]`

- [ ] **3.1.1** `App\Services\Pricing\PricingPipeline` (final class) z DI (`EngineResolver`, `SettingResolver`, `ProjectPageResolver`).
- [ ] **3.1.2** KROK 1 — Silnik obliczeniowy (`$engine = $this->engines->get($product->engine_type); $engineResult = $engine->calculate(...)`).
- [ ] **3.1.3** KROK 2 — Koszty parametrów (`extrasCost`): pobranie `ProductParameterOption` z `selectedParameterOptionIds`, segregacja per rola (common/cover/interior), sumowanie `CostPriceTrait` pól per unit_type.
- [ ] **3.1.4** KROK 3 — Koszty produktowe (`productExtraCost`): `sumCostFields([$product], $engineResult->unitType, $engineResult->unitsConsumed, $config->quantity)`.
- [ ] **3.1.5** KROK 4 — `totalCost = engineResult->materialCost + engineResult->printCost + extrasCost + productExtraCost`.
- [ ] **3.1.6** KROK 5 — Marża dwupoziomowa: `marginAmount = max(totalCost * margin_base_pct, margin_min_pln)`.
- [ ] **3.1.7** KROK 6 — `basePrice = totalCost + marginAmount`.
- [ ] **3.1.8** KROK 7 — `projectPrice` (jeśli `config->orderProject == true`): z `ProjectPageResolver` + `ProjectSetting.base_project_price`.
- [ ] **3.1.9** KROK 8 — `patternMultipliedPrice = basePrice * patternMultiplier` (z `PatternSetting` lub `ParameterOption.pattern_multiplier`).
- [ ] **3.1.10** KROK 9 — `timeMultiplier` z `TimeOption` × `patternMultipliedPrice`.
- [ ] **3.1.11** KROK 10 — Sprawdzenie `ExclusionRule` (przed wynikiem!). Jeśli naruszenie → `throw new ExclusionRuleViolated`.
- [ ] **3.1.12** KROK 11 — Assembly `PricingResult` z pełnym breakdownem.
- [ ] **3.1.13** KROK 12 — Snapshot w `kalkulacje_ceny` (listener na `PriceCalculated`).
- [ ] **3.1.14** Event `App\Events\PriceCalculated(PricingResult $result, Zamowienie $order, PozycjaZamowienia $item)` dispatch na końcu pipeline.
- [ ] **3.1.15** Commit: `feat(pricing): 12-step pipeline`.

#### Etap 3.2 — Listener `SavePricingSnapshot` `[BE]`

- [ ] **3.2.1** `App\Listeners\SavePricingSnapshot implements ShouldQueue` (queue `pricing`).
- [ ] **3.2.2** Zapisuje kompletny JSONB snapshot w `kalkulacje_ceny` — każde przeliczenie = nowy rekord (audit trail).
- [ ] **3.2.3** `EventServiceProvider` — rejestracja.
- [ ] **3.2.4** Commit: `feat(pricing): save snapshot listener`.

#### Etap 3.3 — Endpoint `POST /api/pricing/calculate` `[BE]`

- [ ] **3.3.1** Route z middleware Sanctum.
- [ ] **3.3.2** `PricingController::calculate(PricingCalculateRequest $req)` — walidacja, mapowanie na `CalculationConfig`, wywołanie `PricingPipeline`, zwrot `PricingResult` jako JSON.
- [ ] **3.3.3** Rate limiting: 60 req/min per user.
- [ ] **3.3.4** Performance target: <500 ms (99p).
- [ ] **3.3.5** Filament action „Przelicz cenę" na `PozycjaZamowienia` — modal z konfiguracją → wywołanie pipeline → widok breakdownu.
- [ ] **3.3.6** Commit: `feat(pricing): API endpoint + filament action`.

#### Etap 3.4 — Testy Pest: PipelineTest + MultipageEngineTest (24 testów) `[TEST]`

- [ ] **3.4.1** `tests/Feature/Pricing/PipelineTest.php` — 14 testów (`_nowy_SILNIK_CENOWY/06_tests_pipeline.md`):
  - pipeline_calculates_basic_calculated_product
  - pipeline_applies_margin_base_pct
  - pipeline_applies_margin_min_pln_when_base_pct_too_low
  - pipeline_applies_pattern_multiplier
  - pipeline_applies_time_multiplier_express
  - pipeline_applies_project_price_when_order_project_true
  - pipeline_saves_snapshot_to_kalkulacje_ceny
  - pipeline_dispatches_PriceCalculated_event
  - pipeline_throws_on_exclusion_rule_violation
  - pipeline_handles_common_cover_interior_parameters
  - pipeline_setting_resolver_cascades_override_pattern_global
  - pipeline_performance_under_500ms
  - pipeline_multiple_patterns_multiplies_correctly
  - pipeline_big_decimal_precision_10_digits
- [ ] **3.4.2** `tests/Feature/Pricing/MultipageEngineTest.php` — 10 testów:
  - multipage_cover_interior_sections
  - multipage_cover_material_different
  - multipage_interior_page_count
  - multipage_imposition_cover_vs_interior
  - multipage_common_parameters_apply_to_both
  - multipage_cover_parameters_only_cover
  - multipage_interior_parameters_only_interior
  - multipage_sections_in_details
  - multipage_page_config_applied
  - multipage_invalid_multipage_type_raises
- [ ] **3.4.3** Coverage ≥80% na całym `app/Services/Pricing/*` — CI blocker.
- [ ] **3.4.4** Commit: `test(pricing): 24/49 pipeline + multipage tests`.

### Tydzień 4 — CRM + Orders + FSM 19 stanów

#### Etap 4.1 — Migracje CRM `[DB]`

- [ ] **4.1.1** `klienci` — id, typ ENUM(B2B/B2C), imie_nazwa, nip nullable, regon nullable, email_glowny UNIQUE, telefon_glowny, adres (ulica, miasto, kod, kraj PL default), status ENUM(aktywny/vip/zablokowany), uwagi TEXT, poziom_lojalnosci_id FK nullable, anonimizowany_at nullable (RODO), created_at, updated_at, deleted_at (soft delete).
- [ ] **4.1.2** `osoby_kontaktowe` — id, klient_id FK, imie, nazwisko, email, telefon, stanowisko, glowny bool, created_at.
- [ ] **4.1.3** `tagi` — id, nazwa UNIQUE, kolor_hex.
- [ ] **4.1.4** `klient_tagi` — pivot.
- [ ] **4.1.5** `progi_lojalnosciowe` — id, nazwa, prog_obrotow_rocznych, rabat_procent, priorytet_realizacji, opis.
- [ ] **4.1.6** Commit: `feat(crm): 5 migrations`.

#### Etap 4.2 — NIP validator + GUS adapter (Saloon) `[BE] [INT]`

- [ ] **4.2.1** `App\Services\CRM\NipValidator::validate(string $nip): bool` — algorytm sumy kontrolnej (wagi 6,5,7,2,3,4,5,6,7).
- [ ] **4.2.2** `App\Integrations\Gus\Connector` (Saloon) — GUS BIR1 SOAP endpoint.
- [ ] **4.2.3** `App\Integrations\Gus\Requests\LoginRequest` → sesja.
- [ ] **4.2.4** `App\Integrations\Gus\Requests\GetByNipRequest`.
- [ ] **4.2.5** `App\Integrations\Gus\GusAdapter implements GusAdapterContract` — `getByNip(string $nip): ?GusCompanyData`, cache Redis 24h per NIP.
- [ ] **4.2.6** Fallback: GUS offline → zwraca `null` → UI „Uzupełnij ręcznie".
- [ ] **4.2.7** Binding w `AppServiceProvider`.
- [ ] **4.2.8** Test: `Http::fake` GUS odpowiedzi.
- [ ] **4.2.9** Commit: `feat(crm): NIP validator + GUS adapter`.

#### Etap 4.3 — Model `Klient` + `ClientRegistrar` `[BE]`

- [ ] **4.3.1** `App\Models\Clients\Klient` — relacje (`osobyKontaktowe`, `tagi`, `poziomLojalnosci`, `zamowienia`), scope `active()`, `vip()`.
- [ ] **4.3.2** `App\Services\CRM\ClientRegistrar::create(array $data): Klient` — walidacja NIP, wywołanie `GusAdapter`, auto-fill danych firmy.
- [ ] **4.3.3** `App\Services\CRM\ClientAnonymizationService::anonymize(Klient $k): void` — RODO (prawo do bycia zapomnianym), nadpisuje PII, zachowuje ID + historię zamówień (księgowość).
- [ ] **4.3.4** Event `ClientCreated` + listener `LogAuditEntry`.
- [ ] **4.3.5** Commit: `feat(crm): klient model + registrar + GDPR`.

#### Etap 4.4 — Migracje Orders `[DB]`

- [ ] **4.4.1** `zamowienia` — id, numer UNIQUE (`DRK-2026-XXXXX`), klient_id FK, menedzer_id FK → users, status VARCHAR(32) (FSM), priorytet ENUM(normal/wysoki/pilny), zrodlo ENUM(email/portal/telefon/osobiscie/sklep/reklamacja), termin_realizacji DATE, uwagi_wewnetrzne TEXT, uwagi_klienta TEXT, parent_order_id FK nullable (dodruk/reklamacja), cancellable_until TIMESTAMP nullable (B2C 14 dni odstąpienia), tenant_id nullable (prep multi-tenant), created_at, updated_at, deleted_at.
- [ ] **4.4.2** `pozycje_zamowien` — id, zamowienie_id FK, product_id FK, nazwa_produktu, naklad, format_szer_mm, format_wys_mm, material_podloze, kolorystyka VARCHAR, uszlachetnienie TEXT[], oprawa nullable, cena_jednostkowa_netto DECIMAL 12,2, ilosc, rabat_procent DECIMAL 5,2, pricing_config JSONB (snapshot `CalculationConfig`), created_at.
- [ ] **4.4.3** `specyfikacje_produktowe` — id, pozycja_id FK, parametry JSONB (flexible per typ).
- [ ] **4.4.4** `historia_statusow_zamowien` — id, zamowienie_id FK, stary_status, nowy_status, user_id FK nullable, komentarz TEXT, metadata JSONB, created_at.
- [ ] **4.4.5** Commit: `feat(orders): 4 migrations`.

#### Etap 4.5 — FSM 19 stanów (`spatie/laravel-model-states`) `[BE]`

- [ ] **4.5.1** `App\States\Order\OrderState extends State` (abstract).
- [ ] **4.5.2** 19 klas stanów: `New`, `Pricing`, `WaitingFiles`, `WaitingPayment`, `Designing`, `WaitingApproval`, `Revision`, `Rejected`, `Approved`, `InProduction`, `Hold`, `ReadyToShip`, `Shipped`, `PersonalPickup`, `Delivered`, `Completed`, `Complaint`, `Suspended`, `Cancelled`.
- [ ] **4.5.3** Każdy stan z `label()`, `color()`, `icon()`, `name()` (kod PL).
- [ ] **4.5.4** `OrderState::config()` — rejestracja wszystkich legalnych transitions wg [06_WORKFLOW_I_EVENTY.md](./nowa%20dokumentacja/06_WORKFLOW_I_EVENTY.md) sekcja 1 tabela.
- [ ] **4.5.5** Klasy transitions w `App\States\Order\Transitions\*` — side-effecty w `handle()` (dispatch eventów, notyfikacje).
- [ ] **4.5.6** Observer `OrderStateObserver` — na `OrderStatusChanged` → zapis do `historia_statusow_zamowien`, broadcast Reverb `orders.{id}`.
- [ ] **4.5.7** Testy: każda legalna tranzycja przechodzi; każda nielegalna rzuca `TransitionNotFound`.
- [ ] **4.5.8** Commit: `feat(orders): FSM 19 states + transitions`.

#### Etap 4.6 — Services Orders `[BE]`

- [ ] **4.6.1** `App\Services\Orders\OrderNumberGenerator::next(): string` — format `DRK-2026-XXXXX`, atomic `SELECT ... FOR UPDATE` per rok.
- [ ] **4.6.2** `App\Services\Orders\OrderCreator::create(array $data, User $user): Zamowienie` — validation, numer, eventy.
- [ ] **4.6.3** `App\Services\Orders\OrderDuplicator::duplicate(Zamowienie $src): Zamowienie` — kopiuje pozycje, specyfikacje, linkuje oryginalne pliki (nie kopia fizyczna), `parent_order_id`, event `OrderDuplicated`.
- [ ] **4.6.4** `App\Services\Orders\StatusTransitioner::transition(Zamowienie $z, string $to, ?string $comment): void` — wrapper na `$z->status->transitionTo(...)` + audit.
- [ ] **4.6.5** Commit: `feat(orders): 4 services`.

#### Etap 4.7 — Eventy + Listenery Orders `[BE]`

- [ ] **4.7.1** Eventy: `OrderCreated`, `OrderStatusChanged(Zamowienie, from, to)`, `OrderCancelled(reason)`, `OrderDuplicated(source, new)`.
- [ ] **4.7.2** Listenery:
  - `TriggerInitialPricing` (queue `pricing`) — na `OrderCreated` jeśli pozycje mają `pricing_config`.
  - `AddToCommThread` (queue `default`) — tworzy/znajduje wątek dla klienta.
  - `NotifyManager` (queue `emails`).
  - `LogAuditEntry` (queue `default`).
  - `BroadcastOrderStatusToClient` (default, ShouldBroadcast).
  - `CancelPendingProductionJob` na `OrderCancelled`.
  - `RefundIfPaid` na `OrderCancelled`.
  - `CopyFileLinks` na `OrderDuplicated`.
- [ ] **4.7.3** Commit: `feat(orders): events + listeners`.

#### Etap 4.8 — UI CRM (Inertia + Vue + shadcn-vue) `[FE] [UI]`

- [ ] **4.8.1** `resources/js/Pages/Crm/Clients/Index.vue` — DataTable (`@tanstack/vue-table`) z filtrami (B2B/B2C/status/tag), search Meilisearch.
- [ ] **4.8.2** `Pages/Crm/Clients/Create.vue` — wizard B2B vs B2C. NIP field z debounce 500ms → `POST /api/crm/gus-lookup` → auto-fill.
- [ ] **4.8.3** `Pages/Crm/Clients/Show.vue` — zakładki: Informacje / Zamówienia / Kontakty / Historia / Faktury.
- [ ] **4.8.4** `Components/Crm/ClientStatusBadge.vue`, `Components/Crm/LoyaltyTierBadge.vue`.
- [ ] **4.8.5** Commit: `feat(crm): ui pages + components`.

#### Etap 4.9 — UI Orders `[FE] [UI]`

- [ ] **4.9.1** `Pages/Orders/Index.vue` — lista z filtrami (status, menedżer, klient, termin, priorytet).
- [ ] **4.9.2** `Pages/Orders/Create.vue` — wizard 4-stopniowy: Klient → Pozycje → Specyfikacja → Podgląd. shadcn-vue `Stepper` (custom lub własny).
- [ ] **4.9.3** `Pages/Orders/Show.vue` — header (numer + klient + status badge + deadline + priorytet). Tabs: Pozycje / Pliki / Wycena / Produkcja / Dokumenty / Wysyłka / Komunikacja / Historia. Sidebar: szybkie akcje.
- [ ] **4.9.4** `Components/Orders/OrderStatusBadge.vue` — 19 semantic colors z FSM. Live update przez Reverb `orders.{id}`.
- [ ] **4.9.5** `Components/Orders/StatusTimeline.vue` — renderuje `historia_statusow_zamowien`.
- [ ] **4.9.6** `Components/Orders/ChangeStatusModal.vue` — Dialog + dropdown z **tylko legalnymi** tranzycjami (filtered z `OrderState::config()`).
- [ ] **4.9.7** Commit: `feat(orders): ui pages + components`.

#### Etap 4.10 — Testy `[TEST]`

- [ ] **4.10.1** Pest: `Orders/FsmTest.php` — każda z ~50 legalnych tranzycji + 20 nielegalnych.
- [ ] **4.10.2** Pest: `Orders/OrderNumberGeneratorTest.php` — unikalność, race condition (parallel insert).
- [ ] **4.10.3** Feature: `POST /orders` tworzy zamówienie, emituje `OrderCreated`, widoczne w Filament.
- [ ] **4.10.4** Commit: `test(orders): fsm + generator + feature`.

### Tydzień 5 — DAMS lokalny (tus.io + medialibrary + thumbnails)

#### Etap 5.1 — Konfiguracja `filesystems.php` (5 dysków) `[CFG]`

- [ ] **5.1.1** `local_private` (default) — `storage/app/private`, perms 0600/0750 (z [08_DAMS_LOKALNE_PRZECHOWYWANIE_PLIKOW.md](./nowa%20dokumentacja/08_DAMS_LOKALNE_PRZECHOWYWANIE_PLIKOW.md) sekcja 2).
- [ ] **5.1.2** `local_public` — `storage/app/public` (symlink via `storage:link`).
- [ ] **5.1.4** `tus_temp` — `storage/app/temp/tus`.
- [ ] **5.1.5** `backup_local` — `env('BACKUP_PATH', '/mnt/backup/drukarnia-erp')`.
- [ ] **5.1.6** `.env`: `FILESYSTEM_DISK=local_private`.
- [ ] **5.1.7** Struktura katalogów (`storage/app/private/orders/{yyyy}/{mm}/{order_id}/{source,proofs,production,thumbs}/`, `designs/`, `invoices/`, `complaints/`, `shipment_labels/`, `messages/`).
- [ ] **5.1.8** Commit: `feat(dams): filesystem config + directory structure`.

#### Etap 5.2 — Migracje DAMS `[DB]`

- [ ] **5.2.1** `pliki` — id, uuid UNIQUE, nazwa_oryginalna, slug, mime_type, rozmiar_bajtow BIGINT, disk VARCHAR (default `local_private`), sciezka, checksum_sha256 UNIQUE, uploadowany_przez_id FK, status ENUM(clean/deleted), created_at, updated_at, deleted_at.
- [ ] **5.2.2** `wersje_plikow` — id, plik_id FK, numer_wersji INT, sciezka, komentarz, uploadowany_przez_id FK, aktywna bool, created_at.
- [ ] **5.2.3** `powiazania_plikow` — polymorphic (plik_id FK, fileable_type, fileable_id, rola ENUM(artwork/dokument/dowod/zdjecie/label/proof/production), created_at).
- [ ] **5.2.4** `media` — Spatie medialibrary table (publish migrations).
- [ ] **5.2.5** `temporary_uploads` — id, tus_upload_id VARCHAR UNIQUE, plik_id FK nullable, status, metadata JSONB, expires_at, created_at.
- [ ] **5.2.6** Commit: `feat(dams): 5 migrations`.

#### Etap 5.3 — tus.io server (`ankitpokhrel/tus-php`) `[BE]`

- [ ] **5.3.1** Route `POST /tus/uploads` i `PATCH /tus/uploads/{id}` + `HEAD`/`OPTIONS` (tus protokół) — middleware `auth`.
- [ ] **5.3.2** Controller `TusController` — wrapper na `TusPhp\Tus\Server`, storage `tus_temp` disk.
- [ ] **5.3.3** Hook `OnUploadComplete` → `ProcessUploadedFileJob::dispatch`.
- [ ] **5.3.4** Nginx — `proxy_request_buffering off` dla `/tus/uploads/`, `client_max_body_size 5G`, `client_body_timeout 600s`.
- [ ] **5.3.5** Commit: `feat(dams): tus.io resumable upload server`.

#### Etap 5.4 — Job `ProcessUploadedFileJob` `[BE]`

- [ ] **5.4.1** `App\Jobs\ProcessUploadedFileJob` (queue `default`, `tries=3`).
- [ ] **5.4.2** Kroki:
  1. SHA-256 checksum całego pliku.
  2. Deduplikacja po `checksum_sha256` (jeśli istnieje → link do istniejącego `Plik`).
  3. Przeniesienie z `tus_temp` do `local_private/orders/{yyyy}/{mm}/{order_id}/source/{uuid}-{slug}.{ext}`.
  4. Utworzenie `Plik` + `WersjaPlik v1`.
  5. Dispatch `GenerateThumbnailsJob` (jeśli PDF/TIFF/PNG/JPG).
  6. Event `FileUploaded(Plik, WersjaPliku, uploader)`.
- [ ] **5.4.3** Commit: `feat(dams): process uploaded file job`.

#### Etap 5.5 — Job `GenerateThumbnailsJob` `[BE]`

- [ ] **5.5.1** Queue `thumbnails`, timeout 300s.
- [ ] **5.5.2** PDF: `spatie/pdf-to-image` (wymaga Ghostscript) → PNG pierwszej strony.
- [ ] **5.5.3** Image: `intervention/image` v3 resize do 3 rozmiarów (sm 150px, md 400px, lg 1200px).
- [ ] **5.5.4** Zapis w `storage/app/private/orders/{yyyy}/{mm}/{order_id}/thumbs/{uuid}-{size}.png`.
- [ ] **5.5.5** Update `Plik.thumbnail_*_url` (signed URL generator).
- [ ] **5.5.6** Testy: mock PDF 5-stronicowy → sprawdź PNG wygenerowany.
- [ ] **5.5.7** Commit: `feat(dams): thumbnail generation job`.

#### Etap 5.7 — `FileAccessService` (nginx X-Accel-Redirect) `[BE] [SEC]`

- [ ] **5.7.1** `App\Services\DAMS\FileAccessService::streamForUser(Plik $p, User $u): StreamedResponse`.
- [ ] **5.7.2** Policy check: user ma dostęp do `Plik` (owner? team? admin?).
- [ ] **5.7.3** Response header: `X-Accel-Redirect: /_protected/{relative_path}`.
- [ ] **5.7.4** Route `GET /files/{plik:uuid}` → controller → service.
- [ ] **5.7.5** `FilePolicy` — view/download/delete.
- [ ] **5.7.6** Nginx `location /_protected/ { internal; alias /var/www/.../storage/app/private/; }`.
- [ ] **5.7.7** Commit: `feat(dams): secure file access via x-accel`.

#### Etap 5.8 — UI DAMS `[FE] [UI]`

- [ ] **5.8.1** `Components/Files/FileUploader.vue` — Uppy + `@uppy/tus` + `@uppy/drag-drop` + `@uppy/progress-bar`. Max 5GB, MIME whitelist (PDF, AI, EPS, TIFF, PNG, JPG, ZIP InDesign).
- [ ] **5.8.2** `Components/Files/FileList.vue` — lista plików z miniaturami, wersje, menu (pobierz/wersje/usuń).
- [ ] **5.8.3** `Components/Files/FilePreview.vue` — PDF.js 4 dla PDF, `<img>` dla obrazów.
- [ ] **5.8.4** `Components/Files/FileCard.vue` — miniatura + nazwa + rozmiar + status badge.
- [ ] **5.8.5** Integracja w `Pages/Orders/Show.vue` tab Pliki.
- [ ] **5.8.6** Commit: `feat(dams): ui components`.

#### Etap 5.9 — `CleanupService` (soft-delete 90 dni) `[BE]`

- [ ] **5.9.1** `App\Services\DAMS\CleanupService` + scheduled job codziennie 04:00.
- [ ] **5.9.2** Usuwa pliki `deleted_at < now()->subDays(90)` — fizyczne usunięcie z dysku + `Plik::forceDelete()`.
- [ ] **5.9.3** Commit: `feat(dams): cleanup service for soft-deleted files`.

#### Etap 5.10 — Testy `[TEST]`

- [ ] **5.10.1** Upload 500MB mock PDF → `ProcessUploadedFileJob` → thumbnail + clean.
- [ ] **5.10.3** Cross-user download blocked przez `FilePolicy`.
- [ ] **5.10.4** Resume po zerwaniu połączenia tus.
- [ ] **5.10.5** Dedup: 2× upload tego samego checksumu → 1 fizyczny plik, 2 `PowiazaniePliku`.
- [ ] **5.10.5** Commit: `test(dams): e2e upload + policy`.

### Tydzień 6 — Production Kanban (basic) + Approvals (basic)

#### Etap 6.1 — Migracje Production + Approvals `[DB]`

- [ ] **6.1.1** `zadania_produkcyjne` — id, zamowienie_id FK, status ENUM(oczekuje/w_trakcie/wstrzymane/gotowe), priorytet, data_planowana DATE, notatki TEXT, created_at.
- [ ] **6.1.2** `etapy_produkcji` — id, zlecenie_id FK, typ ENUM(prepress/druk/postpress_ciecie/postpress_laminat/postpress_inne/packaging), kolejnosc INT, status ENUM(oczekuje/w_trakcie/gotowe/pominiety), maszyna_id FK nullable, operator_id FK nullable, czas_start TIMESTAMP nullable, czas_stop TIMESTAMP nullable, czas_normatywny_min INT nullable, czas_rzeczywisty_min INT nullable (generated), notatki_operatora TEXT, created_at.
- [ ] **6.1.3** `maszyny` — id, nazwa, typ ENUM(druk_offset/druk_cyfrowy/wielkoformat/ciecie/laminat/inne), status ENUM(dostepna/zajeta/serwis/wylaczona), koszt_godz DECIMAL, opis, created_at.
- [ ] **6.1.4** `operatorzy` — id, user_id FK UNIQUE, specjalizacja VARCHAR[], dostepny bool, created_at.
- [ ] **6.1.5** `zadania_akceptacji` — id, zamowienie_id FK, plik_wersja_id FK, status ENUM(oczekuje/zaakceptowane/do_poprawy/odrzucone/wygasle), link_token VARCHAR(64) UNIQUE, runda_nr INT default 1, wyslane_przez_id FK, wyslane_at, odpowiedz_at nullable, wygasa_at, komentarz_klienta TEXT nullable, created_at.
- [ ] **6.1.6** `komentarze_akceptacji` — id, zadanie_id FK, tresc TEXT, pozycja_x FLOAT nullable, pozycja_y FLOAT nullable, strona INT nullable, autor_typ ENUM(klient/pracownik), autor_id BIGINT, created_at.
- [ ] **6.1.7** Commit: `feat(prod-approval): 6 migrations`.

#### Etap 6.2 — Services Production `[BE]`

- [ ] **6.2.1** `App\Services\Production\JobCreator::createFromOrder(Zamowienie $z): ZadanieProdukcyjne` — pobiera szablon etapów z JSON config per typ produktu.
- [ ] **6.2.2** `App\Services\Production\KanbanService::moveCard(EtapProdukcji $e, string $newStatus): void`.
- [ ] **6.2.3** `App\Services\Production\TimeLogger::start/stop(EtapProdukcji $e, Operator $op): void`.
- [ ] **6.2.4** Listener `CreateProductionJob` na `ApprovalApproved` → `JobCreator`.
- [ ] **6.2.5** Eventy: `ProductionJobCreated`, `ProductionStarted`, `ProductionStageUpdated implements ShouldBroadcast`, `ProductionCompleted`.
- [ ] **6.2.6** Commit: `feat(production): services + events`.

#### Etap 6.3 — UI Kanban `[FE] [UI]`

- [ ] **6.3.1** `Pages/Production/Kanban.vue` — 5 kolumn (Prepress / Druk / Postpress / Packaging / Gotowe).
- [ ] **6.3.2** Drag & drop: `sortablejs` + `@vueuse/integrations`.
- [ ] **6.3.3** Karta: `ProductionCard.vue` — numer, klient, produkt, nakład, maszyna, operator, deadline, badge status.
- [ ] **6.3.4** Widok etapu: przycisk „Start" / „Stop" → `TimeLogger`.
- [ ] **6.3.5** Presence channel Reverb `production.kanban` → wszystkie ekrany synchronizowane.
- [ ] **6.3.6** `ProductionStagePolicy` — Operator NIE widzi `cost_*`/`margin_*` (scope w Inertia props przez `$this->when($user->can('production.view_cost'), ...)`).
- [ ] **6.3.7** Commit: `feat(production): kanban ui + policy`.

#### Etap 6.4 — Services Approvals (basic — bez markup) `[BE]`

- [ ] **6.4.1** `App\Services\Designs\ApprovalRequestSender::send(Zamowienie $z, WersjaPliku $v, ?User $designer): ZadanieAkceptacji` — UUID v4 token, `wygasa_at = now()+7d`, event `ApprovalRequested`.
- [ ] **6.4.2** `App\Services\Designs\ApprovalResponseHandler::handle(string $token, string $action, ?string $comment): void` — sprawdza ważność, dispatch odpowiedniego eventu (`ApprovalApproved`/`ApprovalRevisionRequested`/`ApprovalRejected`).
- [ ] **6.4.3** `App\Services\Designs\EscalationDetector` — cron, flaguje rundy ≥4 → event `ApprovalEscalated` + notify menedżer + upcharge alert.
- [ ] **6.4.4** Cron `CheckApprovalTimeouts` — scheduler hourly:
  - `wygasa_at - 48h` → event `ApprovalExpiringSoon` → email reminder klient.
  - `wygasa_at < now()` → event `ApprovalExpired` → `ORDER_SUSPENDED`.
- [ ] **6.4.5** Listenery eventów Approvals w `EventServiceProvider`.
- [ ] **6.4.6** Commit: `feat(approvals): services + events + escalation`.

#### Etap 6.5 — UI Approvals (public link basic) `[FE] [UI]`

- [ ] **6.5.1** Route guest `GET /approval/{token}` — brak middleware auth, walidacja tokenu.
- [ ] **6.5.2** `Pages/Public/Approval.vue` — header z brandingiem drukarni, PDF.js viewer, 3 przyciski (Akceptuję / Zmiany / Odrzucam). **Markup = Faza 2** (MVP bez klikalnej mapy).
- [ ] **6.5.3** Widok pracownika: `Pages/Orders/Show.vue` tab „Akceptacja" — historia rund, wybór wersji pliku, „Wyślij do akceptacji".
- [ ] **6.5.4** `Components/Approval/ApprovalStatus.vue` — runda + czas do wygaśnięcia + ostatnia odpowiedź.
- [ ] **6.5.5** Commit: `feat(approvals): public link + manager tab`.

#### Etap 6.6 — Testy `[TEST]`

- [ ] **6.6.1** Pest: full flow approval (accept → status APPROVED → production job created).
- [ ] **6.6.2** Pest: revision flow (3 rundy, w 4. → escalation).
- [ ] **6.6.3** Pest: timeout expire (>7d → ORDER_SUSPENDED).
- [ ] **6.6.4** Pest: Operator nie widzi kosztów w Kanban.
- [ ] **6.6.5** Commit: `test(prod-approval): e2e flows`.

### Tydzień 7 — Finance (Proforma) + Subiekt stub + Przelewy24

#### Etap 7.1 — Migracje Finance + webhooks `[DB]`

- [ ] **7.1.1** `dokumenty_finansowe` — id, zamowienie_id FK, typ ENUM(proforma/faktura_zaliczkowa/faktura_vat/paragon/faktura_korygujaca), numer_subiekt VARCHAR nullable, kwota_netto DECIMAL 12,2, kwota_brutto DECIMAL 12,2, vat DECIMAL 12,2, stawka_vat DECIMAL 5,2 (default 23), status ENUM(wystawiony/oplacony/przeterminowany/anulowany), link_platnosci VARCHAR nullable, termin_platnosci DATE nullable, wystawiony_at TIMESTAMP nullable, oplacony_at TIMESTAMP nullable, mpp_flag bool default false, ksef_number VARCHAR nullable, ksef_submitted_at nullable, pdf_plik_id FK nullable, retencja_do DATE (default wystawiony_at + 5 years), created_at, updated_at.
- [ ] **7.1.2** `platnosci` — id, dokument_id FK, kwota DECIMAL 12,2, metoda ENUM(przelew/blik/karta/gotowka/platnosc_odroczona), data_platnosci DATE, transaction_id_provider VARCHAR UNIQUE nullable, zrodlo ENUM(przelewy24/payU/subiekt_import/reczne), created_at.
- [ ] **7.1.3** `metody_platnosci` — lookup table.
- [ ] **7.1.4** `webhook_events` — id, provider VARCHAR(32) (przelewy24/inpost/dpd/subiekt), provider_event_id VARCHAR(128) UNIQUE, payload JSONB, signature_valid bool, processed_at TIMESTAMP nullable, error TEXT nullable, created_at.
- [ ] **7.1.5** Commit: `feat(finance): 4 migrations`.

#### Etap 7.2 — Subiekt adapter (Saloon, stub) `[BE] [INT]`

- [ ] **7.2.1** `App\Integrations\Subiekt\Contracts\SubiektAdapterContract`.
- [ ] **7.2.2** `App\Integrations\Subiekt\Connector` (Saloon v4) — base URL, auth header.
- [ ] **7.2.3** `App\Integrations\Subiekt\Requests\{CreateContrahentRequest, CreateProformaRequest, CheckPaymentStatusRequest}`.
- [ ] **7.2.4** `App\Integrations\Subiekt\SubiektAdapter` — **w MVP stub**: `createContrahent` zwraca fake `SUB-{nanoid}`, `createProforma` zwraca fake `FPR-{nanoid}`, `checkPaymentStatus` zwraca losowo.
- [ ] **7.2.5** Binding w `AppServiceProvider` — prawdziwy adapter zastąpi stub w Fazie 2.
- [ ] **7.2.6** Retry policy: `retry(3, fn, backoff [0, 30s, 300s])`.
- [ ] **7.2.7** Logowanie do `IntegrationLog` (nowa tabela nieobecna w MASTER_PLAN → do Fazy 2? — w MVP skip, zamiast tego `laravel-activitylog`).
- [ ] **7.2.8** Commit: `feat(finance): subiekt adapter stub`.

#### Etap 7.3 — Przelewy24 adapter (Saloon, pełny) `[BE] [INT] [SEC]`

- [ ] **7.3.1** `App\Integrations\Przelewy24\{Connector, Przelewy24Adapter}` + Requesty.
- [ ] **7.3.2** `createPayment(DokumentFinansowy $d): string` — zwraca `link_platnosci`.
- [ ] **7.3.3** Route `POST /webhooks/przelewy24` → `Przelewy24WebhookController`.
- [ ] **7.3.4** **Weryfikacja 3-stopniowa** PRZED jakąkolwiek logiką:
  1. IP whitelist (konfiguracja w `.env`).
  2. CRC signature: `md5("{sessionId}|{orderId}|{amount}|{currency}|{crcKey}")` vs `sign` z request.
  3. Idempotency: `webhook_events::firstOrCreate(['provider'=>'przelewy24','provider_event_id'=>$sessionId.':'.$orderId], ...)`.
- [ ] **7.3.5** Po weryfikacji → dispatch `ProcessPrzelewy24WebhookJob` (queue `webhooks`).
- [ ] **7.3.6** Job emituje event `PaymentReceived(Platnosc)`.
- [ ] **7.3.7** `.env`: `P24_MERCHANT_ID`, `P24_POS_ID`, `P24_CRC_KEY`, `P24_SANDBOX=true`, `P24_IP_WHITELIST=1.2.3.4,5.6.7.8`.
- [ ] **7.3.8** Commit: `feat(finance): przelewy24 adapter + webhook verification`.

#### Etap 7.4 — Finance Orchestrator services `[BE]`

- [ ] **7.4.1** `App\Services\Finance\ProformaGenerator::generate(Zamowienie $z): DokumentFinansowy` — calls Subiekt, creates `DokumentFinansowy(typ=proforma)`, Przelewy24 link, event `ProformaCreated`.
- [ ] **7.4.2** `App\Services\Finance\InvoiceOrchestrator::handlePayment(Platnosc $p): void` — mark opłacony, event `PaymentReceived`, recalc loyalty tier, generate Faktura Zaliczkowa B2B.
- [ ] **7.4.3** `App\Services\Finance\MppEvaluator::isRequired(DokumentFinansowy $d): bool` — `brutto > 15000 && B2B && containsGoodsFromAppendix15`.
- [ ] **7.4.4** Listener `CreateProformaAfterPricing` na `PriceCalculated` (queue `webhooks`).
  - B2C: zawsze proforma.
  - B2B: tylko jeśli `Klient.kredyt_kupiecki = false` (flaga w `klienci`).
- [ ] **7.4.5** Listener `MarkDocumentPaid` + `CreateInvoiceAdvanceIfB2B` + `RecalculateLoyaltyTier` na `PaymentReceived`.
- [ ] **7.4.6** Scheduler `CheckOverduePayments` daily 08:00 — proforma > 7 dni → reminder; > 14 dni → `ORDER_SUSPENDED`.
- [ ] **7.4.7** Commit: `feat(finance): orchestrator + listeners`.

#### Etap 7.5 — UI Finance `[FE] [UI]`

- [ ] **7.5.1** Tab `Finanse` w `Pages/Orders/Show.vue` — lista dokumentów, status badge live (Reverb), link do płatności (kopiuj/otwórz).
- [ ] **7.5.2** `Pages/Finance/Documents/Index.vue` (Menedżer/Księgowość) — lista wszystkich dokumentów + filtry.
- [ ] **7.5.3** Policy: Admin/Menedżer/Księgowość mają dostęp; Operator 0; Klient — tylko własne w portalu (Faza 2).
- [ ] **7.5.4** Commit: `feat(finance): ui tab + list page`.

#### Etap 7.6 — Testy `[TEST]`

- [ ] **7.6.1** Webhook Przelewy24 z fałszywym CRC → 401.
- [ ] **7.6.2** Duplikat webhook (ten sam `sessionId+orderId`) → idempotent, 1 raz przetworzony.
- [ ] **7.6.3** Pełen flow: `OrderCreated` → `PriceCalculated` → `ProformaCreated` → webhook P24 → `PaymentReceived` → status `WAITING_FILES`.
- [ ] **7.6.4** Subiekt offline (`Http::fake` timeout) → retry 3x → DLQ.
- [ ] **7.6.5** MPP: B2B brutto > 15000 + appendix15 goods → `mpp_flag = true`.
- [ ] **7.6.6** Commit: `test(finance): webhook sec + e2e payment`.

### Tydzień 8 — Logistics (InPost) + etykiety + tracking

#### Etap 8.1 — Migracje Logistics `[DB]`

- [ ] **8.1.1** `przesylki` — id, zamowienie_id FK, metoda ENUM(inpost_paczkomat/inpost_kurier/dpd/dhl/gls/odbior_osobisty), status ENUM(przygotowywana/wyslana/w_transporcie/dostarczona/nieudana/zwrot), numer_listu VARCHAR nullable, tracking_number VARCHAR UNIQUE nullable, tracking_url VARCHAR nullable, etykieta_plik_id FK nullable, koszt_wysylki_netto DECIMAL 10,2, data_nadania DATE nullable, data_dostawy DATE nullable, created_at, updated_at.
- [ ] **8.1.2** `adresy_wysylki` — id, przesylka_id FK, imie_nazwisko, firma nullable, ulica, nr_domu, nr_lokalu nullable, kod_pocztowy, miasto, kraj (default PL), telefon, email, punkt_odbioru_id VARCHAR nullable (paczkomat).
- [ ] **8.1.3** Commit: `feat(logistics): 2 migrations`.

#### Etap 8.2 — InPost adapter (Saloon) `[INT]`

- [ ] **8.2.1** `App\Integrations\InPost\{Connector, InPostAdapter}` — ShipX API v1 + OAuth2 bearer token.
- [ ] **8.2.2** `createShipment(Wysylka $w): array` → `tracking_number` + `etykieta_url`.
- [ ] **8.2.3** `getTracking(string $trackingNumber): string` → status live.
- [ ] **8.2.4** Webhook `POST /webhooks/inpost` → weryfikacja HMAC → idempotency → event `DeliveryStatusChanged` lub `DeliveryConfirmed`.
- [ ] **8.2.5** `.env`: `INPOST_API_KEY`, `INPOST_ORGANIZATION_ID`, `INPOST_SANDBOX`.
- [ ] **8.2.6** Commit: `feat(logistics): inpost adapter + webhook`.

#### Etap 8.3 — Services Shipping `[BE]`

- [ ] **8.3.1** `App\Services\Logistics\ShippingLabelService::createLabel(Przesylka $p): Plik` — wywołanie adaptera, pobranie etykiety PDF, zapis w `storage/app/private/shipment_labels/{yyyy}/{mm}/{uuid}.pdf`, utworzenie `Plik` + `PowiazaniePliku`.
- [ ] **8.3.2** `App\Services\Logistics\TrackingService::updateFromWebhook(array $payload): void`.
- [ ] **8.3.3** `App\Services\Logistics\CourierSelector::recommendFor(Zamowienie $z): string` — heurystyka: waga ≤25kg + wymiary paczkomatu → InPost; paleta → DPD.
- [ ] **8.3.4** Eventy: `ShipmentCreated`, `ShipmentDispatched`, `DeliveryConfirmed`, `DeliveryFailed`.
- [ ] **8.3.5** Listenery:
  - `GenerateLabelJob` na `ShipmentCreated` (queue `webhooks`).
  - `TransitionOrderToShipped` + `SendTrackingEmail` + `CreateVatInvoiceForB2B` (stub w MVP) na `ShipmentDispatched`.
  - `TransitionOrderToDelivered` + `SendThankYouEmail` na `DeliveryConfirmed`.
- [ ] **8.3.6** Commit: `feat(logistics): services + events`.

#### Etap 8.4 — UI Shipping `[FE] [UI]`

- [ ] **8.4.1** Tab `Wysyłka` w `Pages/Orders/Show.vue`:
  - Formularz adresu (prefill z `Klient`).
  - Radio kuriera (InPost Paczkomat / InPost Kurier / odbiór osobisty).
  - InPost Paczkomat: search po kod pocztowy → autocomplete → wybór punktu (opcjonalnie mapa Faza 2).
  - Button „Generuj etykietę" → disable w trakcie job → podgląd PDF + print.
  - Tracking URL po nadaniu + status live (Reverb `orders.{id}`).
- [ ] **8.4.2** Commit: `feat(logistics): shipping tab`.

#### Etap 8.5 — Testy `[TEST]`

- [ ] **8.5.1** `Http::fake` InPost — happy path create shipment + label.
- [ ] **8.5.2** Webhook InPost tracking → aktualizacja statusu przesyłki.
- [ ] **8.5.3** Odbiór osobisty flow (status → `PERSONAL_PICKUP` → `COMPLETED`).
- [ ] **8.5.4** Commit: `test(logistics): e2e`.

### Tydzień 9 — CommHub (IMAP + outbound + inbox + notifications)

#### Etap 9.1 — Migracje CommHub `[DB]`

- [ ] **9.1.1** `watki_komunikacji` — id, zamowienie_id FK nullable, klient_id FK nullable, temat, status ENUM(otwarty/zamkniety/archiwum), ostatnia_wiadomosc_at TIMESTAMP, created_at.
- [ ] **9.1.2** `wiadomosci` — id, watek_id FK, kierunek ENUM(przychodzacy/wychodzacy), kanal ENUM(email/sms/system/notatka), tresc_html TEXT, tresc_plain TEXT, nadawca_id BIGINT nullable, nadawca_email VARCHAR nullable, odbiorca_email VARCHAR nullable, odbiorca_telefon VARCHAR nullable, **external_message_id VARCHAR UNIQUE nullable** (Message-ID z IMAP), status_dostarczenia ENUM(pending/sent/delivered/bounced/failed), przeczytana bool default false, created_at.
- [ ] **9.1.3** `zalaczniki_wiadomosci` — pivot `wiadomosc_id × plik_id`.
- [ ] **9.1.4** `szablony_wiadomosci` — id, nazwa UNIQUE, kanal, temat nullable, tresc_html TEXT, tresc_plain TEXT, zmienne VARCHAR[], aktywny bool, created_at.
- [ ] **9.1.5** `powiadomienia` — id, user_id FK, typ VARCHAR, tytul, tresc, link nullable, przeczytane bool default false, created_at.
- [ ] **9.1.6** Commit: `feat(comm): 5 migrations`.

#### Etap 9.2 — IMAP polling `[BE] [INT]`

- [ ] **9.2.1** `App\Console\Commands\ImapPollCommand` — używa PHP `imap_*` lub `webklex/php-imap`.
- [ ] **9.2.2** `App\Services\Comm\ImapPoller::fetch(): Collection` — pobiera nieprzeczytane, parsuje.
- [ ] **9.2.3** `App\Services\Comm\InboundMessageHandler::handle(array $parsed): Wiadomosc` —
  1. Dedupe po `external_message_id` (Message-ID).
  2. Match klienta po `From:` → `Klient.email_glowny` lub `OsobaKontaktowa.email`.
  3. Nowy klient → draft z `ClientRegistrar` (wymaga weryfikacji).
  4. Znajdź/utwórz `WatekKomunikacji`.
  5. Utwórz `Wiadomosc` + `ZalacznikiWiadomosci` (jeśli są — ingest do DAMS).
  6. Event `MessageReceived`.
- [ ] **9.2.4** Scheduler: `$schedule->command('imap:poll')->everyTwoMinutes()->withoutOverlapping()`.
- [ ] **9.2.5** `.env`: `IMAP_HOST`, `IMAP_PORT=993`, `IMAP_USERNAME`, `IMAP_PASSWORD`, `IMAP_ENCRYPTION=ssl`.
- [ ] **9.2.6** Commit: `feat(comm): imap poller + handler`.

#### Etap 9.3 — Outbound (email + SMS) `[BE]`

- [ ] **9.3.1** `App\Services\Comm\TemplateRenderer::render(string $templateName, array $vars): array` — zwraca `['subject', 'html', 'plain']`, parsowanie `{{zmienna}}`.
- [ ] **9.3.2** `App\Services\Comm\MailSender::send(string $template, array $vars, string $to): Wiadomosc`.
- [ ] **9.3.3** Laravel Mail z drive `smtp` (prod) / `log` (dev) / Mailpit (docker).
- [ ] **9.3.4** `App\Services\Comm\SmsSender::send(string $to, string $text): Wiadomosc` — SMSAPI adapter (stub w MVP, pełny Faza 2).
- [ ] **9.3.5** Eventy: `MessageSent`, `SmsSent`.
- [ ] **9.3.6** Commit: `feat(comm): outbound email + sms`.

#### Etap 9.4 — Seedery szablonów `[BE]`

- [ ] **9.4.1** `SzablonyWiadomosciSeeder` — 9 szablonów:
  - `oferta_wyslana`
  - `proforma_wyslana`
  - `platnosc_otrzymana`
  - `prosba_o_pliki`
  - `link_akceptacji`
  - `zamowienie_w_produkcji`
  - `zamowienie_gotowe`
  - `zamowienie_wyslane`
  - `dziekujemy_za_zamowienie`
- [ ] **9.4.2** Commit: `feat(comm): 9 email templates`.

#### Etap 9.5 — In-app notifications (Reverb) `[BE] [FE]`

- [ ] **9.5.1** `App\Services\Comm\NotificationDispatcher::dispatch(User $u, NotificationData $n): Powiadomienie`.
- [ ] **9.5.2** Event `NotificationCreated implements ShouldBroadcast` → channel `private-user.{id}`.
- [ ] **9.5.3** Listener `BroadcastOrderStatusToClient` na `OrderStatusChanged` — broadcasts do kanału klienta.
- [ ] **9.5.4** Frontend: `useNotifications()` composable — subscribe, reactive list, unread counter.
- [ ] **9.5.5** `Components/Notifications/BellIcon.vue` — shadcn-vue `Popover` + lista 5 ostatnich + link „Wszystkie".
- [ ] **9.5.6** Commit: `feat(comm): in-app notifications via reverb`.

#### Etap 9.6 — UI Inbox `[FE] [UI]`

- [ ] **9.6.1** `Pages/Inbox/Index.vue` — split view: lista wątków po lewej + czat po prawej (shadcn-vue `ScrollArea`).
- [ ] **9.6.2** Filtry: nieprzeczytane / per klient / per zamówienie.
- [ ] **9.6.3** `Components/Comm/MessageThread.vue` — lista wiadomości, pole reply z edytorem, attach files z DAMS.
- [ ] **9.6.4** Oznaczanie jako przeczytane (automatycznie po otwarciu).
- [ ] **9.6.5** Commit: `feat(comm): inbox ui`.

#### Etap 9.7 — Testy `[TEST]`

- [ ] **9.7.1** Mock IMAP — fetch 3 emaile, dedupe 1 duplikat, match 2 istniejących klientów + 1 draft nowy.
- [ ] **9.7.2** Template render z `{{klient_imie}}` → poprawnie podstawione.
- [ ] **9.7.3** `NotificationCreated` dispatch → broadcast event fired na kanale user.
- [ ] **9.7.4** Commit: `test(comm): imap + template + notif`.

### Tydzień 10 — E2E + Dashboard + Global Search + Deploy

#### Etap 10.1 — Dashboard role-dependent `[FE] [UI]`

- [ ] **10.1.1** `Pages/Dashboard.vue` — widgety (Admin/Menedżer):
  - „Zamówienia dziś" (liczba + wartość, compared to yesterday).
  - „Status counts" (pie chart — Chart.js).
  - „Zamówienia pilne / po terminie" (tabela).
  - „Inbox preview" (ostatnie 5 wiadomości).
  - „Produkcja w toku" (lista zleceń z etapami).
- [ ] **10.1.2** Operator dashboard — tylko „Moje etapy dziś".
- [ ] **10.1.3** Klient dashboard (Faza 2) — portal.
- [ ] **10.1.4** On-the-fly agregacje (materialized views dopiero Faza 3).
- [ ] **10.1.5** Reverb live update: nowe zamówienie → counter +1 bez refresh.
- [ ] **10.1.6** Commit: `feat(dashboard): role-dependent widgets`.

#### Etap 10.2 — Global Search (Meilisearch + Ctrl+K) `[BE] [FE]`

- [ ] **10.2.1** Laravel Scout indexy: `Zamowienie`, `Klient`, `Wiadomosc`.
- [ ] **10.2.2** Polski tokenizer w Meilisearch config.
- [ ] **10.2.3** Endpoint `GET /search?q=` — top 5 per model.
- [ ] **10.2.4** `Components/Search/SearchModal.vue` — shadcn-vue `Command` + keyboard shortcut Ctrl+K (cmd+K na macOS).
- [ ] **10.2.5** `php artisan scout:import` — wszystkie istniejące rekordy.
- [ ] **10.2.6** Commit: `feat(search): global search with meilisearch`.

#### Etap 10.3 — E2E test scenariusz `[TEST]`

- [ ] **10.3.1** Pest Feature `tests/Feature/E2eMvpTest.php`:
  1. Mock email IMAP → `MessageReceived`.
  2. Menedżer tworzy `Klient` (auto GUS) + `Zamowienie` z pozycją.
  3. `PricingPipeline::calculate` → `PriceCalculated` → `ProformaCreated` (Subiekt stub) → link P24.
  4. Webhook P24 → `PaymentReceived` → status `WAITING_FILES`.
  5. Klient uploaduje plik (mock tus) → `FileUploaded` → status `WAITING_APPROVAL` (pomińmy approval w E2E — direct `APPROVED`).
  6. `ApprovalApproved` → `ProductionJobCreated` → etapy.
  7. Operator klika „Gotowe" → `ProductionCompleted` → status `READY_TO_SHIP`.
  8. Menedżer wybiera InPost → `ShipmentCreated` → `GenerateLabelJob` (mock InPost) → `ShipmentDispatched`.
  9. Webhook InPost delivered → `DeliveryConfirmed` → status `COMPLETED`.
  10. Asercje: numery zamówień, kwoty, statusy, powiadomienia, pliki powiązane.
- [ ] **10.3.2** Commit: `test(e2e): mvp full scenario`.

#### Etap 10.4 — Bug fixing + polishing `[BE] [FE]`

- [ ] **10.4.1** Przejście przez wszystkie strony w dev — scan `v-if` edge cases.
- [ ] **10.4.2** Pint + Pest zielone.
- [ ] **10.4.3** Lighthouse audit dashboardu (>85 performance).
- [ ] **10.4.4** Commit: `chore: mvp polishing`.

#### Etap 10.5 — Deploy pipeline (Deployer v7) `[DEVOPS]`

- [ ] **10.5.1** `composer require deployer/deployer --dev`.
- [ ] **10.5.2** `deploy.php` wg [13_PLAN_WDROZENIA_I_DEVOPS.md](./nowa%20dokumentacja/13_PLAN_WDROZENIA_I_DEVOPS.md) sekcja 3.
- [ ] **10.5.3** VPS setup (Ubuntu 24.04):
  - Instalacja: Nginx, PHP 8.3-fpm + ext, PostgreSQL 16, Redis 7, Meilisearch, Ghostscript, ImageMagick, Supervisor, certbot.
  - Użytkownik deploy (klucze SSH).
  - firewall (ufw: 22/80/443), fail2ban, unattended-upgrades.
- [ ] **10.5.4** Supervisor: `horizon.conf`, `reverb.conf` (z pliku 13 sekcja 4).
- [ ] **10.5.5** Nginx: `drukarnia-erp.conf` (z pliku 13 sekcja 5) z `X-Accel-Redirect`, tus uploads, Reverb WS proxy.
- [ ] **10.5.6** Let's Encrypt: `certbot --nginx -d erp.drukarnia.pl`.
- [ ] **10.5.7** `dep deploy prod` — zero-downtime deploy.
- [ ] **10.5.8** Health check: `curl -sf https://erp.drukarnia.pl/health` → 200.
- [ ] **10.5.9** Commit: `feat(devops): deploy pipeline`.

#### Etap 10.6 — Backup + monitoring `[DEVOPS]`

- [ ] **10.6.1** Cron 03:00: `pg_dump drukarnia_erp | gzip > /mnt/backup/pg/{date}.sql.gz`.
- [ ] **10.6.2** Cron 03:30: `rsync -a --delete storage/app/private/ /mnt/backup/files/`.
- [ ] **10.6.3** Rotacja: 30 dni lokalnie, opcjonalnie encrypted upload do Backblaze B2 (**tylko backup, nie storage**).
- [ ] **10.6.4** Uptime Kuma self-host na backup VPS.
- [ ] **10.6.5** Sentry (self-host lub SaaS) integracja.
- [ ] **10.6.6** Laravel Pulse config.
- [ ] **10.6.7** Commit: `feat(devops): backup + monitoring`.

#### Etap 10.7 — Smoke test produkcyjny + tag v1.0 `[TEST]`

- [ ] **10.7.1** Login admin → `/admin` działa.
- [ ] **10.7.2** Utworzenie testowego klienta + zamówienia + wyceny.
- [ ] **10.7.3** Upload pliku 100MB PDF → miniatura + AV clean.
- [ ] **10.7.4** Generacja proformy (Subiekt stub) + link P24.
- [ ] **10.7.5** Push testowy webhook P24 sandbox → `PaymentReceived`.
- [ ] **10.7.6** Kanban widoczny, real-time działa (2 przeglądarki).
- [ ] **10.7.7** `git tag v1.0.0-mvp && git push --tags`.
- [ ] **10.7.8** Commit: `docs: release v1.0.0-mvp`.

**Rezultat Fazy 1:** działający ERP obsługujący zamówienia end-to-end. Menedżer pracuje w przeglądarce, klient komunikuje się emailem, drukarnia nie używa papieru.

---

<a id="faza-2"></a>
## FAZA 2 — PORTAL + APPROVALS MARKUP + PEŁNY PRICING UI + FULL FINANCE (8–10 TYGODNI)

**Cel:** Klient ma własny portal (samoobsługa). Akceptuje projekty online z markup na PDF. Pełny 12-krokowy `PricingPipeline` aktywny w konfiguratorze Vue (sklep-ready). Finance generuje Faktury VAT/Paragon/Korekty przez prawdziwy Subiekt nexo (KSeF via Subiekt). Kurierzy DPD + DHL + GLS. SMS powiadomienia. AI Layer basic.

### Tydzień 11–12 — Client Portal

#### Etap 11.1 — Routing + middleware Portal `[BE]`

- [ ] **11.1.1** Route group `Route::middleware(['auth', 'role:Klient'])->prefix('portal')`.
- [ ] **11.1.2** Layout `resources/js/Layouts/PortalLayout.vue` — uproszczony (logo drukarni, minimal sidebar, własny color accent).
- [ ] **11.1.3** Middleware `EnsurePortalClient` — user musi mieć powiązanie z `Klient`.
- [ ] **11.1.4** Commit: `feat(portal): routing + layout`.

#### Etap 11.2 — Rejestracja + magic link `[BE] [FE]`

- [ ] **11.2.1** Route `POST /portal/register` — klient podaje email + NIP (B2B) / imię (B2C). Jeśli email już jest w `Klient` → link do konfiguracji hasła wysłany.
- [ ] **11.2.2** `App\Services\Auth\PortalRegistrar` — tworzy `User` z rolą `Klient`, link do `Klient`.
- [ ] **11.2.3** Magic link (1-click login): `GET /portal/magic/{token}` — jednorazowy token z `temporary_signed_url`.
- [ ] **11.2.4** `Pages/Portal/Auth/Register.vue`, `Login.vue`, `MagicLink.vue`.
- [ ] **11.2.5** Commit: `feat(portal): registration + magic link`.

#### Etap 11.3 — Moje zamówienia `[FE] [UI]`

- [ ] **11.3.1** `Pages/Portal/Orders/Index.vue` — lista własnych zamówień (scope `klient_id = $user->klient_id`).
- [ ] **11.3.2** `Pages/Portal/Orders/Show.vue` — uproszczona karta:
  - Status badge + timeline.
  - Pliki (upload tus + preview PDF.js).
  - Dokumenty finansowe (pobierz PDF + link do płatności).
  - Historia komunikacji (read-only).
- [ ] **11.3.3** Policy `PortalOrderPolicy` — klient widzi TYLKO własne zamówienia.
- [ ] **11.3.4** Commit: `feat(portal): own orders page`.

#### Etap 11.4 — 2FA (Fortify TOTP) `[SEC] [FE]`

- [ ] **11.4.1** Fortify `features` — włączyć `two-factor-authentication`.
- [ ] **11.4.2** `Pages/Profile/TwoFactor.vue` — QR code enroll + recovery codes.
- [ ] **11.4.3** Aktywne dla ról Admin/Menedżer; opcjonalne dla Klient.
- [ ] **11.4.4** Commit: `feat(sec): 2fa totp`.

### Tydzień 13 — Approvals z markup PDF

#### Etap 13.1 — PdfMarkupViewer `[FE] [UI]`

- [ ] **13.1.1** `Components/Approval/PdfMarkupViewer.vue` — PDF.js 4 + canvas overlay.
- [ ] **13.1.2** Kliknięcie na PDF → pin z komentarzem `(x, y, page)`.
- [ ] **13.1.3** Lista komentarzy per strona (sidebar).
- [ ] **13.1.4** Multi-page nawigacja + zoom (+/- , fit to width).
- [ ] **13.1.5** Commit: `feat(approval): pdf markup viewer`.

#### Etap 13.2 — Publiczny link approval z markup `[FE]`

- [ ] **13.2.1** Update `Pages/Public/Approval.vue` — zastąpić basic viewer `PdfMarkupViewer`.
- [ ] **13.2.2** Przycisk „Do poprawy" wymaga **minimum 1 komentarza** (validation).
- [ ] **13.2.3** Zapis komentarzy w `komentarze_akceptacji` per `ZadanieAkceptacji`.
- [ ] **13.2.4** Po submit → email do designera z linkiem do listy komentarzy.
- [ ] **13.2.5** Commit: `feat(approval): markup on public link`.

#### Etap 13.3 — Widok pracownika: historia rund `[FE]`

- [ ] **13.3.1** `Pages/Orders/Approvals/History.vue` — tabela rund z datami, odpowiedziami, komentarzami.
- [ ] **13.3.2** Preview PDF per wersja (dropdown wersji).
- [ ] **13.3.3** Komentarze nałożone na PDF (`PdfMarkupViewer` read-only dla pracownika).
- [ ] **13.3.4** Eskalacja runda ≥4 — banner ostrzeżenia + button „Alert menedżer + upcharge".
- [ ] **13.3.5** Commit: `feat(approval): manager history view`.

#### Etap 13.4 — Testy `[TEST]`

- [ ] **13.4.1** Pest: submit „Do poprawy" bez komentarza → walidacja fail.
- [ ] **13.4.2** Pest: 4 rundy → `ApprovalEscalated` event.
- [ ] **13.4.3** Commit: `test(approval): markup validations`.

### Tydzień 14 — Pełny Pricing Configurator Vue (sklep-ready)

#### Etap 14.1 — `ProductConfigurator.vue` `[FE] [UI]`

- [ ] **14.1.1** Komponent `Components/Pricing/ProductConfigurator.vue` — full config form:
  - Format (select z `MediaFormat` + custom).
  - Materiał cover / interior / default (zależne od produktu).
  - Zadruk option.
  - Ilość (number input + quick buttons 100/250/500/1000/2500/5000/10000).
  - Pattern count (wzorów).
  - Order project checkbox + PageConfig select.
  - Time option (Standard / Express).
  - Parameters (common/cover/interior) — multi-select per rola.
- [ ] **14.1.2** Live re-pricing debounce 300 ms → `POST /api/pricing/calculate` → update preview.
- [ ] **14.1.3** `Components/Pricing/PriceBreakdown.vue` — rozpisane: cena bazowa, modyfikatory, rabat, VAT, brutto.
- [ ] **14.1.4** Commit: `feat(pricing): product configurator ui`.

#### Etap 14.2 — Price Grid (qty × time) `[FE] [BE]`

- [ ] **14.2.1** `Components/Pricing/PriceGrid.vue` — 9 progów ilości × 2 kolumny (Standard / Express).
- [ ] **14.2.2** Endpoint `POST /api/pricing/grid` — batch computation 18 kalkulacji.
- [ ] **14.2.3** Backend: batch w `PricingPipeline` z `SettingResolver` cached (per request).
- [ ] **14.2.4** Redis cache per `(product_id, config_hash)` TTL 1h.
- [ ] **14.2.5** Performance target: <1500 ms (99p).
- [ ] **14.2.6** Commit: `feat(pricing): price grid batch`.

#### Etap 14.3 — Testy performance `[TEST]`

- [ ] **14.3.1** Pest performance: `PricingPipeline::calculate` <500 ms.
- [ ] **14.3.2** Pest performance: `/api/pricing/grid` <1500 ms.
- [ ] **14.3.3** Commit: `test(pricing): perf gates`.

### Tydzień 15 — Pełny Finance (VAT/Paragon/Korekta) + KSeF via Subiekt

#### Etap 15.1 — Subiekt adapter: prawdziwa integracja `[INT]`

- [ ] **15.1.1** Zamiana stuba z Fazy 1 na prawdziwe wywołania Subiekt nexo REST (Sfera) API.
- [ ] **15.1.2** OAuth2 autoryzacja + token refresh.
- [ ] **15.1.3** `createContrahent`, `createProforma`, `createFakturaVat`, `createParagon`, `createFakturaKorygujaca`, `checkPaymentStatus`, `getContrahent`.
- [ ] **15.1.4** Mapowanie polskich stawek VAT (23/8/5/0/zw).
- [ ] **15.1.5** Testy Http::fake z Subiekt API contracts.
- [ ] **15.1.6** Commit: `feat(finance): real subiekt adapter`.

#### Etap 15.2 — Invoice Orchestrator full `[BE]`

- [ ] **15.2.1** Update `InvoiceOrchestrator::handlePayment` — po `ShipmentDispatched`:
  - B2B → `createFakturaVat` → `DispatchToKsef` (via Subiekt).
  - B2C → `createParagon` (lub Faktura do paragonu na żądanie).
- [ ] **15.2.2** `App\Services\Finance\KsefDispatcher::submit(DokumentFinansowy $d): string` — stub przekazujący do Subiekta (własna integracja KSeF = Faza 4).
- [ ] **15.2.3** `App\Services\Finance\CorrectionInvoiceTrigger` — listener na `ReprintOrderCreated` / `RefundIssued` → `createFakturaKorygujaca`.
- [ ] **15.2.4** Event `KsefSubmitted(DokumentFinansowy, ksef_number)` → zapis `ksef_number` + `ksef_submitted_at`.
- [ ] **15.2.5** Commit: `feat(finance): full invoice orchestration + ksef`.

#### Etap 15.3 — Retention 5 lat `[BE]`

- [ ] **15.3.1** Scheduled job `PurgeExpiredFinancialDocsJob` — miesięcznie.
- [ ] **15.3.2** Usuwa dokumenty gdzie `retencja_do < now()` (soft-delete + po 30 dniach `forceDelete`).
- [ ] **15.3.3** Commit: `feat(finance): 5yr retention purge`.

### Tydzień 16 — Logistics full (DPD + DHL + GLS) + Express

#### Etap 16.1 — Adaptery DPD / DHL / GLS (Saloon) `[INT]`

- [ ] **16.1.1** `App\Integrations\Dpd\{Connector, DpdAdapter}` — analogia do InPost (DPD RESTful API).
- [ ] **16.1.2** `App\Integrations\Dhl\{Connector, DhlAdapter}` — DHL Express API.
- [ ] **16.1.3** `App\Integrations\Gls\{Connector, GlsAdapter}`.
- [ ] **16.1.4** Wspólny `CourierAdapterContract` — `createShipment`, `getLabel`, `getTracking`.
- [ ] **16.1.5** Webhooki dla każdego kuriera → uniform `ShipmentStatusChanged` event.
- [ ] **16.1.6** Commit: `feat(logistics): dpd dhl gls adapters`.

#### Etap 16.2 — Express shipping flag `[BE] [FE]`

- [ ] **16.2.1** Kolumna `express` bool w `zamowienia` + migracja.
- [ ] **16.2.2** Priority queue produkcji: sortowanie po `(express DESC, termin_realizacji ASC)`.
- [ ] **16.2.3** SLA 24h — alert jeśli etap zablokowany >4h.
- [ ] **16.2.4** UI badge „EXPRESS" na kartach zamówień (czerwony).
- [ ] **16.2.5** Commit: `feat(logistics): express flag + priority`.

### Tydzień 17 — SMS + Quotes (moduł 5)

#### Etap 17.1 — SMSAPI adapter `[INT]`

- [ ] **17.1.1** `App\Integrations\Smsapi\{Connector, SmsapiAdapter}` (SMSAPI.pl REST API).
- [ ] **17.1.2** `send(string $to, string $text): SmsResult` — max 160 znaków (lub segmenty).
- [ ] **17.1.3** Webhook raport dostarczenia → aktualizacja `status_dostarczenia` w `wiadomosci`.
- [ ] **17.1.4** `.env`: `SMSAPI_TOKEN`, `SMSAPI_FROM` (alphanumeric sender ID).
- [ ] **17.1.5** Commit: `feat(comm): smsapi adapter`.

#### Etap 17.2 — Migracje Quotes `[DB]`

- [ ] **17.2.1** `wyceny` — id, klient_id FK, numer UNIQUE (`WYC-YYYY-XXXXX`), status ENUM(draft/wyslana/zaakceptowana/odrzucona/wygasla/przeksztalcona), link_token VARCHAR UNIQUE nullable, wartosc_netto DECIMAL 12,2, vat DECIMAL 12,2, wartosc_brutto DECIMAL 12,2, expires_at TIMESTAMP (default created_at + 14 days), zaakceptowana_at nullable, zamowienie_id FK nullable (po konwersji), created_at, updated_at.
- [ ] **17.2.2** `pozycje_wycen` — analogicznie do `pozycje_zamowien`, z `pricing_config` JSONB + snapshot `PricingResult`.
- [ ] **17.2.3** Commit: `feat(quotes): 2 migrations`.

#### Etap 17.3 — Services Quotes `[BE]`

- [ ] **17.3.1** `App\Services\Quotes\QuoteGenerator::generate(Klient $k, array $items): Wycena`.
- [ ] **17.3.2** `App\Services\Quotes\QuoteConverter::convertToOrder(Wycena $q): Zamowienie` — kopiuje pozycje + pricing snapshoty (zachowuje cenę!), link `parent_quote_id` (kolumna w `zamowienia`).
- [ ] **17.3.3** Scheduler `ExpireQuotesJob` — daily 00:00, oznacza wygasłe.
- [ ] **17.3.4** Commit: `feat(quotes): generator + converter`.

#### Etap 17.4 — UI Quotes `[FE] [UI]`

- [ ] **17.4.1** `Pages/Quotes/Index.vue` — lista z filtrami (status, klient, termin).
- [ ] **17.4.2** `Pages/Quotes/Create.vue` — wizard podobny do Orders.
- [ ] **17.4.3** Publiczny link `/quotes/{token}` → `Pages/Public/Quote.vue` (podgląd + 2 guziki: Akceptuję / Odrzucam).
- [ ] **17.4.4** Po akceptacji → `convertToOrder` → redirect do portalu z nowym zamówieniem.
- [ ] **17.4.5** Commit: `feat(quotes): ui`.

### Tydzień 18–19 — AI Layer basic + Web Push

#### Etap 18.1 — OpenAI adapter `[BE] [INT]`

- [ ] **18.1.1** `composer require openai-php/laravel`.
- [ ] **18.1.2** `App\Services\Ai\AiAssistant::suggestEmailReply(Wiadomosc $m, Klient $k): string`.
- [ ] **18.1.3** `AiAssistant::suggestSpecification(string $clientQuery): array` — zwraca JSON propozycji pozycji zamówienia.
- [ ] **18.1.4** `AiAssistant::explainPricing(PricingResult $r): string` — natural-language breakdown.
- [ ] **18.1.5** **Anonimizacja PII** przed wysłaniem:
  ```php
  $text = str_replace(
    [$k->imie_nazwa, $k->nip, $k->email_glowny, $k->telefon_glowny],
    ['[KLIENT]', '[NIP]', '[EMAIL]', '[TEL]'],
    $text
  );
  ```
- [ ] **18.1.6** Cache `prompt_hash` (MD5) w Redis 1h → jeśli HIT, zwróć cached.
- [ ] **18.1.7** Config: `max_tokens: 500`, model `gpt-4o`.
- [ ] **18.1.8** `.env`: `OPENAI_API_KEY`, `OPENAI_MODEL=gpt-4o`, `OPENAI_MAX_TOKENS=500`.
- [ ] **18.1.9** Commit: `feat(ai): openai assistant`.

#### Etap 18.2 — Migracja ai_sugestie `[DB]`

- [ ] **18.2.1** `ai_sugestie` — id, zamowienie_id FK nullable, watek_id FK nullable, typ ENUM(odpowiedz_email/specyfikacja/wycena_opis/historia), prompt_hash VARCHAR, wynik TEXT, zaakceptowana bool nullable, tokens_uzyte INT, created_at.
- [ ] **18.2.2** Commit: `feat(ai): ai_sugestie migration`.

#### Etap 18.3 — UI AI `[FE]`

- [ ] **18.3.1** `Components/Ai/AiSuggestionPanel.vue` — w widoku wątku Inbox, przycisk „✨ Zasugeruj odpowiedź" + loading spinner + render tekstu + akcje (Użyj / Odrzuć / Edytuj i użyj).
- [ ] **18.3.2** `Components/Ai/AiSpecSuggestion.vue` — w kreatorze zamówienia, input z emailem klienta → AI → propozycja.
- [ ] **18.3.3** Disclaimer banner: „AI może popełniać błędy. Zweryfikuj przed wysłaniem.".
- [ ] **18.3.4** Commit: `feat(ai): ui suggestion panels`.

#### Etap 18.4 — Web Push (VAPID / Firebase FCM) `[FE] [BE]`

- [ ] **18.4.1** `composer require laravel-notification-channels/webpush`.
- [ ] **18.4.2** Service Worker `public/sw.js` — obsługa push events.
- [ ] **18.4.3** `resources/js/composables/usePushNotification.ts` — subskrypcja + token registration.
- [ ] **18.4.4** Integracja z `NotificationDispatcher::send()` — dispatch na kanały in-app + web push.
- [ ] **18.4.5** Permissions: prompt user raz, zapisz decyzję.
- [ ] **18.4.6** Commit: `feat(comm): web push notifications`.

### Tydzień 20 — E2E Faza 2 + deploy

#### Etap 20.1 — E2E scenariusz Faza 2 `[TEST]`

- [ ] **20.1.1** Pest Feature:
  1. Klient rejestruje się w portalu → `Klient` link.
  2. Klient wypełnia formularz kontaktowy → email do drukarni.
  3. Menedżer generuje wycenę (z `QuoteGenerator`) → publiczny link → klient akceptuje.
  4. `convertToOrder` → `PriceCalculated` (pełny UI flow) → Proforma → P24 webhook → `PaymentReceived`.
  5. Klient uploaduje plik → `WAITING_APPROVAL`.
  6. Menedżer wysyła link akceptacji → klient zostawia 2 komentarze markup → `REVISION`.
  7. Designer update + v2 → klient akceptuje v2 → `APPROVED`.
  8. Produkcja → `SHIPMENT_DISPATCHED` → `createFakturaVat` → KSeF.
  9. `DeliveryConfirmed` → `COMPLETED`.
- [ ] **20.1.2** Commit: `test(e2e): phase 2 scenario`.

#### Etap 20.2 — Deploy Faza 2 `[DEVOPS]`

- [ ] **20.2.1** Migracje prod (dep deploy prod).
- [ ] **20.2.2** Smoke test — wszystkie nowe feature'y.
- [ ] **20.2.3** `git tag v2.0.0-portal`.
- [ ] **20.2.4** Commit: `docs: release v2.0.0-portal`.

**Rezultat Fazy 2:** Klient działa samodzielnie w portalu (bez emaila). Markup przyspiesza akceptację. Prawdziwy Subiekt + KSeF obsługują fakturowanie B2B/B2C. AI wspiera menedżera.

---

<a id="faza-3"></a>
## FAZA 3 — SHOP + DESIGN EDITOR + ANALYTICS + COMPLAINTS + PRODUCTION FULL (10–12 TYGODNI)

**Cel:** Sklep e-commerce otwarty (B2C/B2B), Design Editor Konva.js umożliwia klientom tworzenie projektów online, Reports z materialized views, Complaints full z SLA, Production harmonogramowanie.

### Tydzień 21–23 — Shop (moduł 17)

#### Etap 21.1 — Migracje Shop `[DB]`

- [ ] **21.1.1** `koszyki` — id, klient_id FK nullable (guest session_token), session_token VARCHAR UNIQUE nullable, created_at, updated_at.
- [ ] **21.1.2** `pozycje_koszykow` — id, koszyk_id FK, product_id FK, pricing_config JSONB, quantity, pricing_snapshot JSONB, created_at.
- [ ] **21.1.3** `kody_promocyjne` — id, kod VARCHAR UNIQUE, typ ENUM(procent/kwota/darmowa_dostawa), wartosc DECIMAL, max_uzyc INT nullable, uzyc_licznik INT default 0, aktywny_od TIMESTAMP nullable, aktywny_do TIMESTAMP nullable, min_kwota_koszyka DECIMAL nullable, created_at.
- [ ] **21.1.4** `produkty_historia_cen` — **Omnibus compliance** — id, product_id FK, cena_brutto DECIMAL 12,2, data DATE, historical_min_price_last_30d DECIMAL 12,2 (generated), created_at.
- [ ] **21.1.5** Commit: `feat(shop): 4 migrations`.

#### Etap 21.2 — Services Shop `[BE]`

- [ ] **21.2.1** `App\Services\Shop\CartService::addItem/removeItem/updateQuantity/applyPromoCode` — guest session_token lub klient_id.
- [ ] **21.2.2** `CartService::recalculatePrices` — wywołanie `PricingPipeline` per pozycja (debouncable).
- [ ] **21.2.3** `App\Services\Shop\CheckoutService::checkout(Koszyk $k, array $payment): Zamowienie` — tworzy `Klient` guest → po zapłacie `convertGuestToUser` (magic link).
- [ ] **21.2.4** `App\Services\Shop\PromoCodeValidator::validate(string $code, Koszyk $k): ?RabatWynik`.
- [ ] **21.2.5** `App\Services\Shop\OmnibusPriceTracker` — scheduled daily job, snapshot cen produktów + min z 30d.
- [ ] **21.2.6** Eventy: `CartUpdated`, `CheckoutStarted`, `OrderCreatedFromShop`, `GuestConvertedToUser`.
- [ ] **21.2.7** Commit: `feat(shop): services + events`.

#### Etap 21.3 — UI Shop `[FE] [UI]`

- [ ] **21.3.1** `Pages/Shop/Home.vue` — kategorie, promo banner, najczęściej zamawiane.
- [ ] **21.3.2** `Pages/Shop/Product/Show.vue` — strona produktu z `ProductConfigurator.vue` (live re-pricing debounce 300ms).
- [ ] **21.3.3** `Pages/Shop/Cart.vue` — lista pozycji, rabat kod, summary netto/brutto/**najniższa cena 30d**.
- [ ] **21.3.4** `Pages/Shop/Checkout.vue` — wizard wieloetapowy (Dane → Wysyłka → Płatność → Podsumowanie).
- [ ] **21.3.5** `Pages/Shop/Confirm.vue` — `/confirm/{hash}` magic-link post-payment → „Dokończ rejestrację".
- [ ] **21.3.6** UOKiK compliance banner: info o personalizacji (brak 14 dni odstąpienia) w flow zamówienia personalizowanego.
- [ ] **21.3.7** Layout `ShopLayout.vue` — e-commerce frontend (koszyk icon z licznikiem, search, kategorie).
- [ ] **21.3.8** Commit: `feat(shop): ui pages + shop layout`.

#### Etap 21.4 — SEO + meta tags `[FE] [DOC]`

- [ ] **21.4.1** Structured data schema.org Product.
- [ ] **21.4.2** OpenGraph / Twitter cards.
- [ ] **21.4.3** Sitemap.xml generator (cron).
- [ ] **21.4.4** `robots.txt`.
- [ ] **21.4.5** Commit: `feat(shop): seo meta`.

#### Etap 21.5 — Testy `[TEST]`

- [ ] **21.5.1** Guest checkout → zamówienie + konwersja klient po płatności.
- [ ] **21.5.2** Promo code (różne typy).
- [ ] **21.5.3** Omnibus: snapshot historyczny poprawny.
- [ ] **21.5.4** Commit: `test(shop): e2e`.

### Tydzień 24–26 — Design Editor (moduł 18)

#### Etap 24.1 — Migracje Design `[DB]`

- [ ] **24.1.1** `projekty_graficzne` — id, klient_id FK, product_id FK, nazwa, status ENUM(draft/submitted/approved), created_at, updated_at.
- [ ] **24.1.2** `wersje_projektow` — id, projekt_id FK, numer_wersji INT, scene_state JSONB (kompresja gzip), exported_pdf_plik_id FK nullable, created_at.
- [ ] **24.1.3** `komentarze_projektow` — analogicznie do `komentarze_akceptacji`.
- [ ] **24.1.4** `szablony_projektow` — id, product_id FK, nazwa, kategoria, scene_template JSONB, thumbnail_plik_id FK, publiczny bool, created_at.
- [ ] **24.1.5** Commit: `feat(design): 4 migrations`.

#### Etap 24.2 — Canvas Konva.js `[FE] [UI]`

- [ ] **24.2.1** `npm install vue-konva konva`.
- [ ] **24.2.2** `Pages/Designer/Editor.vue` — `<v-stage>` canvas + layers + transformer.
- [ ] **24.2.3** Sidebar: warstwy, elementy (tekst, obraz, shape), szablony.
- [ ] **24.2.4** Undo/redo stack w Vue state (immer-like).
- [ ] **24.2.5** Keyboard shortcuts (Ctrl+Z, Ctrl+Shift+Z, Delete, Duplicate).
- [ ] **24.2.6** Upload obrazów → DAMS → draggable do canvas.
- [ ] **24.2.7** Commit: `feat(design): konva editor ui`.

#### Etap 24.3 — SceneAutoSaver `[BE] [FE]`

- [ ] **24.3.1** `App\Services\Design\SceneAutoSaver::save(Projekt $p, array $sceneState): WersjaProjektu`.
- [ ] **24.3.2** Kompresja gzip JSON → `storage/app/private/designs/{project_id}/scenes/{timestamp}.json.gz`.
- [ ] **24.3.3** Frontend: autosave co 5 s z debounce, request `POST /api/designs/{id}/autosave`.
- [ ] **24.3.4** Keep last 20 wersji per projekt (cleanup cron).
- [ ] **24.3.5** Commit: `feat(design): scene autosave`.

#### Etap 24.4 — PdfExporter (Puppeteer) `[BE]`

- [ ] **24.4.1** `App\Jobs\ExportDesignPdfJob` (queue `default`, timeout 300s).
- [ ] **24.4.2** Subproces headless Chrome (Puppeteer) — renderuje scene_state do PDF.
- [ ] **24.4.3** `jspdf` + `html2canvas` alternatywa.
- [ ] **24.4.4** Output: **PDF 300 DPI CMYK + 3 mm bleed** (poligraficznie-ready).
- [ ] **24.4.5** Import do DAMS → `FileUploader::ingest` → powiązanie z `Zamowienie`.
- [ ] **24.4.6** Event `DesignExported(Projekt, Plik)`.
- [ ] **24.4.7** Commit: `feat(design): pdf export job`.

#### Etap 24.5 — Template Library `[BE]`

- [ ] **24.5.1** `App\Services\Design\TemplateLibraryService::list/get`.
- [ ] **24.5.2** Seeder `TemplateSeeder` — wizytówki (10+), ulotki (5+), plakaty (3+).
- [ ] **24.5.3** UI: `Components/Designer/TemplatePicker.vue` — grid z miniaturami.
- [ ] **24.5.4** Commit: `feat(design): template library`.

### Tydzień 27 — Complaints full (moduł 12)

#### Etap 27.1 — Migracje Complaints `[DB]`

- [ ] **27.1.1** `reklamacje` — id, zamowienie_id FK, klient_id FK, numer UNIQUE (`RKL-YYYY-XXXXX`), powod ENUM(wada_druku/brakujace_elementy/uszkodzenie/opoznienie/inne), opis TEXT, status VARCHAR(32) (FSM), decyzja ENUM(dodruk/zwrot_czesciowy/zwrot_calkowity/odmowa) nullable, rozpatrujacy_id FK nullable, zamowienie_dodruku_id FK nullable, zgloszono_at TIMESTAMP, odpowiedz_at nullable, zamknieto_at nullable, sla_response_at DATE (zgloszono_at + 48h), sla_resolution_at DATE (zgloszono_at + 14 days), created_at.
- [ ] **27.1.2** `dowody_reklamacji` — id, reklamacja_id FK, plik_id FK, typ ENUM(zdjecie/skan/inne), opis nullable.
- [ ] **27.1.3** Commit: `feat(complaints): 2 migrations`.

#### Etap 27.2 — FSM Complaints + Services `[BE]`

- [ ] **27.2.1** `App\States\Complaint\{Zgloszona, WRozpatrywaniu, Uznana, Odrzucona, Zamknieta}` + transitions.
- [ ] **27.2.2** `App\Services\Complaints\ComplaintCreator::create(Zamowienie $z, array $data): Reklamacja`.
- [ ] **27.2.3** `App\Services\Complaints\ReprintOrderCreator::reprint(Reklamacja $r): Zamowienie` — duplikuje zamówienie, `parent_order_id` link, `zrodlo=reklamacja`.
- [ ] **27.2.4** `App\Services\Complaints\CorrectionInvoiceTrigger` — listener na `ReprintOrderCreated` / `RefundIssued` → Finance.
- [ ] **27.2.5** `App\Services\Complaints\SlaTimer` — scheduled hourly:
  - `sla_response_at < now()` AND nie ma odpowiedzi → alert menedżer.
  - `sla_resolution_at < now()` → escalation Admin.
- [ ] **27.2.6** Eventy: `ComplaintSubmitted`, `ComplaintApproved`, `ComplaintRejected`, `ReprintOrderCreated`, `RefundIssued`.
- [ ] **27.2.7** Commit: `feat(complaints): fsm + services`.

#### Etap 27.3 — UI Complaints `[FE] [UI]`

- [ ] **27.3.1** `Pages/Complaints/Index.vue` (Menedżer) — lista z filtrami (status, termin SLA, odpowiedzialny).
- [ ] **27.3.2** `Pages/Complaints/Show.vue` — karta: zamówienie, klient, powód, opis, zdjęcia (DAMS viewer), timeline statusów, sekcja „Decyzja" (dropdown), button „Utwórz dodruk".
- [ ] **27.3.3** `Components/Complaints/SlaTimer.vue` — countdown do SLA deadline.
- [ ] **27.3.4** Portal klient: `Pages/Portal/Complaints/Create.vue` — formularz + upload zdjęć dowodu.
- [ ] **27.3.5** Commit: `feat(complaints): ui`.

### Tydzień 28 — Reports / BI + Materialized Views

#### Etap 28.1 — Materialized Views PostgreSQL `[DB]`

- [ ] **28.1.1** `mv_dashboard_daily` — zamówienia dziś/wczoraj/tydzień, przychód, marża (z KosztWlasny snapshotów).
- [ ] **28.1.2** `mv_orders_by_status` — snapshot per godzina.
- [ ] **28.1.3** `mv_machine_utilization` — % zajętości per maszyna per dzień.
- [ ] **28.1.4** `mv_operator_efficiency` — norma vs rzeczywistość per operator per miesiąc.
- [ ] **28.1.5** `mv_client_loyalty` — top klienci wg obrotów, poziomy.
- [ ] **28.1.6** `mv_complaint_rate` — % per operator, maszyna, produkt.
- [ ] **28.1.7** `mv_revenue_monthly` — przychód brutto/netto/marża per miesiąc (12 mies.).
- [ ] **28.1.8** `RefreshAnalyticsViews` job — cron co 1h `REFRESH MATERIALIZED VIEW CONCURRENTLY mv_*`.
- [ ] **28.1.9** Commit: `feat(reports): 7 materialized views`.

#### Etap 28.2 — Analytics API + Services `[BE]`

- [ ] **28.2.1** `App\Services\Reports\DashboardDataAggregator::getForRole(User $u): array`.
- [ ] **28.2.2** `App\Services\Reports\CsvExporter`, `XlsxExporter` (biblioteka `openspout/openspout`).
- [ ] **28.2.3** Endpoints `/api/reports/{type}` — cache Redis 1h.
- [ ] **28.2.4** Policy: Admin/Menedżer/Księgowość.
- [ ] **28.2.5** Commit: `feat(reports): api + exporters`.

#### Etap 28.3 — UI Analytics `[FE] [UI]`

- [ ] **28.3.1** `Pages/Analytics/Index.vue` — 7 sekcji w tabs/cards:
  - Przychód (line chart 12 mies.).
  - Statusy zamówień (pie chart).
  - Obciążenie maszyn (heatmap per dzień).
  - Efektywność operatorów (tabela norma/rzeczywisty/%).
  - Top klienci (ranking).
  - Reklamacje (pie powód + bar per maszyna).
  - Trend miesięczny przychodu.
- [ ] **28.3.2** Filtry: date range, kategoria.
- [ ] **28.3.3** Eksport per sekcja (CSV/XLSX).
- [ ] **28.3.4** Chart.js + vue-chartjs.
- [ ] **28.3.5** Commit: `feat(reports): ui analytics`.

### Tydzień 29 — Production Engine full (moduł 8)

#### Etap 29.1 — Harmonogramowanie tygodniowe `[FE] [BE]`

- [ ] **29.1.1** `Pages/Production/Schedule.vue` — widok tygodniowy per maszyna (custom SVG Gantt).
- [ ] **29.1.2** Drag & drop zleceń między slotami czasu.
- [ ] **29.1.3** Detekcja kolizji (2 zlecenia na tej samej maszynie w tym samym czasie).
- [ ] **29.1.4** Commit: `feat(production): weekly schedule`.

#### Etap 29.2 — Konfiguracja szablonów etapów `[BE] [UI]`

- [ ] **29.2.1** JSONB pole `production_template` per typ produktu (Filament admin).
- [ ] **29.2.2** Edytor Vue JSON w Filamencie (form field).
- [ ] **29.2.3** `JobCreator` używa szablonu przy tworzeniu etapów.
- [ ] **29.2.4** Commit: `feat(production): templates per product`.

#### Etap 29.3 — Alerty opóźnienia + raport RKW `[BE] [FE]`

- [ ] **29.3.1** Cron co 15 min — scan etapów `czas_rzeczywisty_min > czas_normatywny_min + 30` → alert menedżer.
- [ ] **29.3.2** Raport: `czas_rzeczywisty` vs `czas_normatywny` per zamówienie → feed do `KosztWlasny` (update snapshotu `PricingResult`).
- [ ] **29.3.3** Commit: `feat(production): delay alerts + rkw`.

### Tydzień 30 — Loyalty full + Inventory full

#### Etap 30.1 — Loyalty per klient `[BE] [FE]`

- [ ] **30.1.1** Filament CRUD `/admin/loyalty/tiers`.
- [ ] **30.1.2** `LoyaltyTierRecalculator` — listener na `PaymentReceived` (nie po `OrderCompleted` — precyzja!).
- [ ] **30.1.3** Karta klienta — zakładka „Lojalność" (poziom, obroty YTD, do następnego progu).
- [ ] **30.1.4** Email „Awans poziomu" po zmianie (szablon `awans_lojalnosci`).
- [ ] **30.1.5** Commit: `feat(loyalty): full integration`.

#### Etap 30.2 — Inventory full (moduł 9) `[DB] [BE] [FE]`

- [ ] **30.2.1** Migracje: `pozycje_magazynu` (id, nazwa, sku UNIQUE, kategoria, jednostka, stan_aktualny DECIMAL, stan_min DECIMAL, cena_zakupu DECIMAL, dostawca, created_at).
- [ ] **30.2.2** `transakcje_magazynowe` (id, pozycja_id FK, typ ENUM(przyjecie/wydanie/korekta/inwentaryzacja), ilosc DECIMAL, cena_jednostkowa DECIMAL nullable, etap_produkcji_id FK nullable, user_id FK, komentarz, created_at).
- [ ] **30.2.3** `App\Services\Inventory\StockManager::in/out(PozycjaMagazynu $p, float $qty, ...)`.
- [ ] **30.2.4** Listener `AutoConsumeFromProduction` — na `ProductionStageUpdated` (typ=druk) → automatyczne odpisy zużycia z BOM (Bill Of Materials per produkt).
- [ ] **30.2.5** `LowStockAlerter` cron — codziennie 09:00, sprawdza `stan_aktualny < stan_min` → email.
- [ ] **30.2.6** UI: `/inventory` lista + wykresy zużycia (Chart.js).
- [ ] **30.2.7** Commit: `feat(inventory): full module`.

### Tydzień 31 — Security Audit + Load testing

#### Etap 31.1 — OWASP Top 10 audit `[SEC] [TEST]`

- [ ] **31.1.1** CSRF: Inertia + SameSite cookies → verify all POST/PUT/DELETE.
- [ ] **31.1.2** XSS: Vue auto-escape; review all `v-html` usage.
- [ ] **31.1.3** SQL injection: wyłącznie Eloquent/Query Builder z bindingami.
- [ ] **31.1.4** Rate limiting: login (5/min), webhooks (100/min), public approval (30/min), API (60/min).
- [ ] **31.1.5** Signed URLs dla wszystkich downloadów plików (X-Accel-Redirect + policy).
- [ ] **31.1.6** Webhook signatures: P24 CRC, InPost HMAC, DPD/DHL/GLS — 100% coverage.
- [ ] **31.1.7** Session timeout 2h, absolute 24h.
- [ ] **31.1.8** Password policy: min 12 znaków, zxcvbn strength.
- [ ] **31.1.9** Commit: `chore(sec): owasp audit pass`.

#### Etap 31.2 — RBAC coverage test `[TEST]`

- [ ] **31.2.1** Pest: każda z 6 ról próbuje dostać do zabronionych endpointów → 403.
- [ ] **31.2.2** Operator nie widzi `cost_*`/`margin_*` w żadnym widoku.
- [ ] **31.2.3** Klient nie widzi cudzych zamówień/faktur.
- [ ] **31.2.4** Commit: `test(sec): rbac 100% coverage`.

#### Etap 31.3 — Load testing (k6) `[TEST]`

- [ ] **31.3.1** Scenario: 50 concurrent users, 10 min, mixed (login, browse orders, dashboard, upload).
- [ ] **31.3.2** Upload 2GB PDF concurrent 5x — verify resumable + AV nie zablokuje.
- [ ] **31.3.3** Target: p99 < 2s dla strony, < 500ms dla `/api/pricing/calculate`.
- [ ] **31.3.4** Raport k6 w PR.
- [ ] **31.3.5** Commit: `test(perf): k6 load report`.

#### Etap 31.4 — Tag v3.0 `[DOC]`

- [ ] **31.4.1** `git tag v3.0.0-shop`.
- [ ] **31.4.2** Release notes.

### Tydzień 32 — Bug bash + dokumentacja użytkownika

#### Etap 32.1 — User docs `[DOC]`

- [ ] **32.1.1** Notion / MkDocs dla pracowników (Admin, Menedżer, Projektant, Operator, Księgowość).
- [ ] **32.1.2** Help Center dla klientów (FAQ, przewodniki, screencasty).
- [ ] **32.1.3** Changelog publiczny.
- [ ] **32.1.4** Commit: `docs: user documentation`.

#### Etap 32.2 — UX polish `[FE]`

- [ ] **32.2.1** A/B test kluczowych flow (checkout, approval).
- [ ] **32.2.2** Bug bash z właścicielem drukarni (2h session).
- [ ] **32.2.3** Commit: `chore: ux polish`.

**Rezultat Fazy 3:** Pełny ERP + e-commerce + Design Editor. Drukarnia może sprzedawać online, klienci projektują online, zarząd ma dashboardy.

---

<a id="faza-4"></a>
## FAZA 4 — ENTERPRISE (OTWARTE, 6+ MIESIĘCY)

**Nieaktywna w fazach 1–3. Zakres do zaplanowania osobno po ustabilizowaniu Fazy 3.**

### Zakres ramowy

- **Preflight Engine** — automatyczna walidacja PDF (rozdzielczość, spady, CMYK, fonty, trim box). Tooling: **callas pdfToolbox CLI** (lic. ~1500€/rok) lub open-source `pdfcpu`. Nowe statusy FSM: `PREFLIGHT_PENDING` / `PREFLIGHT_PASSED` / `PREFLIGHT_FAILED`. Integracja z DAMS przez queue `preflight`.
- **Multi-tenant** — `tenant_id` już w migracjach (MVP), aktywacja middleware `SetCurrentTenant`, subdomeny per drukarnia, global scopes. Fundusze wspólne (templaty, media formaty) vs tenant-specific.
- **Integracja JDF/JMF** — komunikacja z maszynami drukarskimi (job definition format). Automatyczny upload zleceń na maszynę, pobieranie statusów.
- **Native mobile app** — React Native lub Flutter. Funkcje: Kanban dla operatora, notifications push, inbox.
- **Własna integracja KSeF** — zamiast passthrough przez Subiekt.
- **Forecasting ML** — prognoza zamówień, sugestie poziomu lojalności, alert outlier marży.
- **AI Layer full** — agent Claude API (Anthropic SDK) lub GPT-4 Turbo do:
  - Automatycznego pre-fillu zamówień z emaili.
  - Voice input / dyktowanie notatek produkcji.
  - Automatycznej kategoryzacji reklamacji.

### Decision gate

Faza 4 zostanie zaplanowana osobno po:
- 3 miesiącach produkcyjnego działania Fazy 3.
- Analizie metryk użycia (co najbardziej spowalnia pracę? jakie feature requesty od użytkowników?).
- Walidacji ekonomicznej (ROI każdego kierunku).

---

<a id="cross-cutting"></a>
## CROSS-CUTTING CONCERNS (OBOWIĄZUJĄ W KAŻDEJ FAZIE)

### Testy

- **Framework:** Pest 4 (wrapper na PHPUnit 11).
- **Coverage gates:**
  - `app/Services/Pricing/*` — **≥80%** (CI blocker). 49 testów portowanych 1:1 z `_nowy_SILNIK_CENOWY/05-06`.
  - `app/Services/Finance/*` — ≥70%.
  - `app/Integrations/*` — ≥60% (Http::fake).
  - Reszta `app/` — ≥50%.
- **Kategorie:**
  - Unit: value objects, calculators, validators.
  - Feature: HTTP endpoints, policies.
  - Integration: adapters with Http::fake.
  - E2E: Pest features (per faza 1 scenario + per faza 2 scenario + per faza 3 scenario).
  - Performance: `--coverage-text` + custom `Pest::it('runs under X ms')`.
- **CI blokuje merge** przy:
  - `pint --test` fail.
  - Coverage threshold nie spełniony.
  - Jakikolwiek failed test.

### Pint i code style

- `vendor/bin/pint --dirty --format agent` PRZED każdym commitem (lokalnie husky/pre-commit hook).
- CI: `pint --test` blokuje.

### Konwencje UI (bezwzględne)

Z [02_STACK_TECHNOLOGICZNY.md](./nowa%20dokumentacja/02_STACK_TECHNOLOGICZNY.md) sekcja 2:

- Komponenty wyłącznie z `resources/js/Components/ui/` (shadcn-vue). Brakujące: `npx shadcn-vue@latest add <name>`.
- Ikony wyłącznie `lucide-vue-next`.
- Kolory wyłącznie semantyczne (`bg-primary`, `text-muted-foreground`, `border-input`). **Zakaz** `bg-blue-500`, `text-gray-400`.
- Odstępy: `flex gap-*` / `grid gap-*`. **Zakaz** `space-x-*`, `space-y-*`.
- Kwadraty/ikony: `size-*`. **Zakaz** `w-10 h-10`.
- Formy: `<FormField>` + `<FormItem>` + `<FormLabel>` + `<FormControl>` + `<FormMessage>`.
- Modale: `<Dialog>` + `<DialogTrigger asChild>` + `<DialogContent>`.
- `v-model`, nie `:value` + `@update:*`.
- Dynamiczne klasy: `cn(...)` z `@/lib/utils`.
- **Filament** (admin `/admin`) używa Livewire + Tailwind — oddzielne UI, nie narusza polityki shadcn-vue.

### Pieniądze i arytmetyka

- **Wyłącznie `brick/math` `BigDecimal`** (scale 10) lub `bcmath` z `bcscale(10)`.
- **Nigdy `float`.**
- Casting Eloquent: `'amount_netto' => MoneyCast::class` (custom cast wracający BigDecimal).

### Konwencje nazw

- **Tabele domeny biznesowej:** polskie `snake_case` (`zamowienia`, `kalkulacje_ceny`, `wiadomosci`).
- **Tabele pricingu:** angielskie 1:1 z kodem silnika (`pricing_rules`, `zadruk_option`, `material_size`, `exclusion_rule`, `product_parameter_option`).
- **FQCN:** `App\Models\{Domena}\{CamelCase}`, `App\Services\{Domena}\*`, `App\Events\{Czas przeszły}`, `App\Jobs\{Nazwa}Job`, `App\Listeners\{NazwaAkcji}`, `App\Integrations\{NazwaZewn}\*`.

### DI

- Kontrakty (interfejsy) zawsze wstrzykiwane, nie konkretne klasy.
- Binding w `AppServiceProvider::register()`.
- W testach: swap na fake przez `$this->app->bind(FooContract::class, FakeFoo::class)`.

### Idempotency

UNIQUE indexes na kolumnach identyfikujących zewnętrzne zdarzenia:

- `wiadomosci.external_message_id` — Message-ID z IMAP.
- `platnosci.transaction_id_provider` — ID transakcji P24.
- `webhook_events.provider_event_id` — dowolny webhook.
- `przesylki.tracking_number` — nr listu kuriera.
- `pliki.checksum_sha256` — dedup plików.
- `dokumenty_finansowe.numer_subiekt` — nr Subiekt (nullable UNIQUE).

### Retry policy (Jobs, Adapters)

```php
public int $tries = 3;
public array $backoff = [0, 30, 300];  // 0s, 30s, 5min
public function retryUntil(): \DateTime { return now()->addHours(2); }
```

Po 3 próbach → `failed_jobs` table → event `IntegrationFailure` → alert Admin (email + in-app).

### Webhook security (3-krokowa weryfikacja)

Każdy webhook inbound:
1. **IP whitelist** (konfiguracja w `.env`).
2. **Signature** (HMAC / CRC) PRZED jakąkolwiek logiką → 401 jeśli nie zgadza.
3. **Idempotency** przez `webhook_events.provider_event_id` UNIQUE → duplikat = skip.

### Backup

- **RPO:** 24h.
- **RTO:** 4h.
- Cron 03:00: `pg_dump drukarnia_erp | gzip > /mnt/backup/pg/{date}.sql.gz`.
- Cron 03:30: `rsync -a --delete storage/app/private/ /mnt/backup/files/`.
- Retencja 30 dni lokalnie.
- Opcjonalnie encrypted upload do Backblaze B2 (tylko backup, nie storage).

---

<a id="struktura"></a>
## STRUKTURA KATALOGÓW (REFERENCJA)

```
app/
├── Enums/                       # ProductType, EngineType, PartRole, UnitType, OrderStatus
├── Events/                      # OrderCreated, PriceCalculated, FileUploaded, ...
├── Http/
│   ├── Controllers/
│   │   ├── Auth/
│   │   ├── Admin/               # UserController, RoleController
│   │   ├── Portal/              # PortalOrderController, PortalInvoiceController
│   │   ├── Shop/                # ShopController, CartController, CheckoutController
│   │   └── Webhooks/            # Przelewy24, InPost, Dpd, Dhl, Gls, Subiekt
│   ├── Middleware/              # CheckPermission, HandleInertiaRequests, EnsurePortalClient, SetCurrentTenant
│   └── Requests/
├── Integrations/
│   ├── Subiekt/{Connector, SubiektAdapter, Requests/, Responses/, Exceptions/, Contracts/}
│   ├── Gus/
│   ├── Przelewy24/
│   ├── InPost/
│   ├── Dpd/
│   ├── Dhl/
│   ├── Gls/
│   └── Smsapi/
├── Jobs/                        # ProcessUploadedFileJob, GenerateThumbnailsJob, ExportDesignPdfJob, ...
├── Listeners/                   # 40+ listenery (1 plik = 1 listener)
├── Models/
│   ├── Core/                    # GlobalSetting, PatternSetting, ProjectSetting, CustomFormatSetting
│   ├── Parameters/              # Material, ZadrukOption, TimeOption, PageConfig, Parameter, ParameterOption
│   ├── Products/                # Product, ProductOverride, ProductParameterOption, SimpleProductDiscount
│   ├── Exclusions/              # ExclusionRule
│   ├── Orders/                  # Zamowienie, PozycjaZamowienia, HistoriaStatusow
│   ├── Clients/                 # Klient, OsobaKontaktowa, Tag, PoziomLojalnosci
│   ├── Finance/                 # DokumentFinansowy, Platnosc, WebhookEvent
│   ├── Production/              # ZadanieProdukcyjne, EtapProdukcji, Maszyna, Operator
│   ├── Shipping/                # Przesylka, AdresWysylki
│   ├── Complaints/              # Reklamacja, DowodReklamacji
│   ├── Comm/                    # WatekKomunikacji, Wiadomosc, Szablon, Powiadomienie
│   ├── DAMS/                    # Plik, WersjaPliku, PowiazaniePliku
│   ├── Shop/                    # Koszyk, PozycjaKoszyka, KodPromocyjny, ProduktHistoriaCen
│   ├── Design/                  # Projekt, WersjaProjektu, KomentarzProjektu
│   └── Quotes/                  # Wycena, PozycjaWyceny
├── Policies/                    # 1 policy per model wrażliwy
├── Services/
│   ├── Pricing/
│   │   ├── PricingPipeline.php
│   │   ├── EngineResolver.php
│   │   ├── Engines/{SheetEngine, LinearMeterEngine, MultipageEngine}.php
│   │   ├── Resolvers/{SettingResolver, ProjectPageResolver}.php
│   │   └── ValueObjects/{CalculationConfig, PricingResult, EngineResult}.php
│   ├── Orders/                  # OrderCreator, OrderDuplicator, StatusTransitioner, OrderNumberGenerator
│   ├── CRM/                     # ClientRegistrar, GusValidator, NipValidator, LoyaltyTierRecalculator, ClientAnonymizationService
│   ├── DAMS/                    # FileUploader, ThumbnailGenerator, FileAccessService, CleanupService
│   ├── Production/              # JobCreator, KanbanService, TimeLogger
│   ├── Finance/                 # ProformaGenerator, InvoiceOrchestrator, MppEvaluator, KsefDispatcher, CorrectionInvoiceTrigger
│   ├── Logistics/               # ShippingLabelService, TrackingService, CourierSelector
│   ├── Complaints/              # ComplaintCreator, ReprintOrderCreator, SlaTimer
│   ├── Comm/                    # ImapPoller, InboundMessageHandler, TemplateRenderer, NotificationDispatcher, SmsSender, MailSender
│   ├── Shop/                    # CartService, CheckoutService, PromoCodeValidator, OmnibusPriceTracker
│   ├── Design/                  # SceneAutoSaver, PdfExporter, TemplateLibraryService
│   ├── Quotes/                  # QuoteGenerator, QuoteConverter
│   ├── Reports/                 # DashboardDataAggregator, CsvExporter, XlsxExporter
│   ├── Ai/                      # AiAssistant
│   └── Auth/                    # PortalRegistrar, PermissionSeeder, AuditObserver
├── States/
│   ├── Order/{New, Pricing, WaitingFiles, ... 19 klas}.php
│   │   └── Transitions/         # klasa per transition z handle() side-effectów
│   └── Complaint/{Zgloszona, WRozpatrywaniu, Uznana, Odrzucona, Zamknieta}.php
└── Traits/                      # CostPriceTrait, Auditable, SingletonTrait

resources/js/
├── Pages/
│   ├── Auth/
│   ├── Dashboard.vue
│   ├── Orders/                  # Index, Create, Show, Approvals/History
│   ├── Crm/Clients/             # Index, Create, Show
│   ├── Production/              # Kanban, Schedule
│   ├── Shipping/                # (w Orders/Show tab)
│   ├── Complaints/              # Index, Show
│   ├── Analytics/Index.vue
│   ├── Admin/                   # (Filament pod /admin)
│   ├── Portal/                  # Auth/, Orders/, Complaints/
│   ├── Shop/                    # Home, Product/Show, Cart, Checkout, Confirm
│   ├── Designer/Editor.vue
│   ├── Quotes/                  # Index, Create
│   ├── Inbox/                   # Index, Show
│   └── Public/                  # Approval.vue, Quote.vue (guest routes)
├── Components/
│   ├── ui/                      # shadcn-vue (generated)
│   ├── Orders/                  # OrderStatusBadge, StatusTimeline, ChangeStatusModal
│   ├── Files/                   # FileUploader, FileList, FilePreview, FileCard
│   ├── Approval/                # ApprovalStatus, PdfMarkupViewer
│   ├── Production/              # ProductionCard, KanbanColumn
│   ├── Finance/                 # DocumentCard, PaymentStatusBadge
│   ├── Pricing/                 # ProductConfigurator, PriceBreakdown, PriceGrid
│   ├── Shop/                    # ProductCard, CartSummary, PromoCodeInput
│   ├── Designer/                # TemplatePicker, LayerPanel
│   ├── Comm/                    # MessageThread, BellIcon, AiSuggestionPanel
│   ├── Complaints/              # SlaTimer
│   ├── Crm/                     # ClientStatusBadge, LoyaltyTierBadge
│   ├── Search/                  # SearchModal
│   └── Shared/                  # EmptyState, Pagination, LoadingSpinner
├── Layouts/
│   ├── AppLayout.vue            # pracownik (sidebar + navbar + bell)
│   ├── PortalLayout.vue         # klient portal
│   ├── ShopLayout.vue           # sklep e-commerce
│   └── GuestLayout.vue          # Breeze unauthenticated
├── composables/                 # useAuth, useNotifications, usePushNotification, usePricing, useCart
├── echo.ts
└── app.ts

database/
├── migrations/                  # numerowane per tydzień roadmapy (format 2026_MM_DD_HHMMSS_*)
├── seeders/
│   ├── DatabaseSeeder.php
│   ├── RoleSeeder.php
│   ├── AdminSeeder.php
│   ├── StatusDefinitionSeeder.php
│   ├── MaszynaSeeder.php
│   ├── PricingDemoSeeder.php
│   ├── SzablonyWiadomosciSeeder.php
│   └── TemplateSeeder.php       # Faza 3 (Design Editor)
└── factories/                   # per model: KlientFactory, ZamowienieFactory, ProductFactory

tests/
├── Feature/
│   ├── Pricing/                 # CalculatorTest, SimplePricingTest, PipelineTest, MultipageEngineTest
│   ├── Orders/                  # FsmTest, OrderNumberGeneratorTest
│   ├── Dams/
│   ├── Finance/
│   ├── Approvals/
│   ├── Logistics/
│   ├── Complaints/
│   ├── Shop/
│   ├── E2eMvpTest.php
│   ├── E2ePhase2Test.php
│   └── E2ePhase3Test.php
├── Unit/
└── Pest.php
```

---

<a id="tracking"></a>
## TRACKING POSTĘPU

| Faza | Etap | Tydzień | Opis | Status |
|------|------|---------|------|--------|
| 0 | 0.1–0.10 | Dni 1–3 | Scaffolding (Breeze + shadcn-vue + Filament + Docker + CI) | ⬜ |
| 1 | 1 | T1 | Pricing scaffold + Filament + seedery | ⬜ |
| 1 | 2 | T2 | PricingPipeline engines + SettingResolver + 25/49 testów | ⬜ |
| 1 | 3 | T3 | Pipeline 12-kroków + MultipageEngine + 24/49 testów | ⬜ |
| 1 | 4 | T4 | CRM + Orders + FSM 19 stanów | ⬜ |
| 1 | 5 | T5 | DAMS lokalny + tus + thumbnails | ⬜ |
| 1 | 6 | T6 | Production Kanban + Approvals basic | ⬜ |
| 1 | 7 | T7 | Finance Proforma + Subiekt stub + Przelewy24 | ⬜ |
| 1 | 8 | T8 | Logistics InPost + etykiety + tracking | ⬜ |
| 1 | 9 | T9 | CommHub IMAP + outbound + inbox + notifications | ⬜ |
| 1 | 10 | T10 | E2E MVP + dashboard + deploy + `v1.0.0-mvp` | ⬜ |
| 2 | 11.1–4 | T11–12 | Client Portal + 2FA | ⬜ |
| 2 | 13 | T13 | Approvals markup PDF | ⬜ |
| 2 | 14 | T14 | Pełny Pricing Configurator UI | ⬜ |
| 2 | 15 | T15 | Full Finance + KSeF via Subiekt | ⬜ |
| 2 | 16 | T16 | Logistics DPD+DHL+GLS + Express | ⬜ |
| 2 | 17 | T17 | SMS + Quotes | ⬜ |
| 2 | 18–19 | T18–19 | AI Layer basic + Web Push | ⬜ |
| 2 | 20 | T20 | E2E Faza 2 + `v2.0.0-portal` | ⬜ |
| 3 | 21 | T21–23 | Shop (moduł 17) | ⬜ |
| 3 | 24 | T24–26 | Design Editor (moduł 18) | ⬜ |
| 3 | 27 | T27 | Complaints full (moduł 12) | ⬜ |
| 3 | 28 | T28 | Reports + Materialized Views | ⬜ |
| 3 | 29 | T29 | Production Engine full | ⬜ |
| 3 | 30 | T30 | Loyalty full + Inventory full | ⬜ |
| 3 | 31 | T31 | Security Audit + Load testing + `v3.0.0-shop` | ⬜ |
| 3 | 32 | T32 | Bug bash + user docs | ⬜ |
| 4 | — | — | Preflight + multi-tenant + JDF/JMF + AI full | 🔒 |

**Legenda:** ⬜ pending, 🟡 in progress, ✅ done, 🔒 odłożone.

---

<a id="appendiksy"></a>
## APPENDIKSY

### Appendix A — Niezbędne komendy setup

```bash
# 1. Inicjalizacja
composer create-project laravel/laravel drukarnia-erp "11.*"
cd drukarnia-erp
composer require laravel/breeze --dev
php artisan breeze:install vue --inertia --ssr --typescript --pest --dark

# 2. shadcn-vue
npx shadcn-vue@latest init  # neutral, CSS vars yes
npx shadcn-vue@latest add button card dialog form input label select sheet table tabs toast \
  dropdown-menu avatar badge skeleton command separator alert tooltip scroll-area popover \
  checkbox radio-group switch textarea calendar date-picker breadcrumb navigation-menu progress hover-card

# 3. Pakiety core
composer require \
  inertiajs/inertia-laravel:"^2.0" tightenco/ziggy:"^2.0" \
  laravel/horizon:"^5.0" laravel/reverb:"^1.0" laravel/fortify:"^1.0" laravel/sanctum:"^4.0" \
  laravel/scout:"^11.1" meilisearch/meilisearch-php:"^1.0" \
  spatie/laravel-permission:"^6.0" spatie/laravel-model-states:"^2.0" \
  spatie/laravel-activitylog:"^4.0" spatie/laravel-medialibrary:"^11.0" spatie/pdf-to-image:"^3.0" \
  intervention/image:"^3.0" ankitpokhrel/tus-php:"^2.0" saloonphp/saloon:"^4.0" \
  brick/math:"^0.12" league/csv:"^9.0"

composer require --dev laravel/pint laravel/pail laravel/telescope nunomaduro/collision \
  pestphp/pest-plugin-laravel deployer/deployer

# 4. Publish + install
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan vendor:publish --provider="Spatie\ActivityLog\ActivityLogServiceProvider"
php artisan vendor:publish --provider="Spatie\MediaLibrary\MediaLibraryServiceProvider" --tag="medialibrary-migrations"
php artisan horizon:install
php artisan reverb:install
php artisan fortify:install
php artisan sanctum:install
php artisan scout:install
php artisan telescope:install

# 5. Filament
composer require filament/filament:"^3.0" -W
php artisan filament:install --panels
php artisan make:filament-user

# 6. Migracje + seed
php artisan migrate --seed
php artisan db:seed --class=PricingDemoSeeder

# 7. Search index
php artisan scout:import "App\Models\Orders\Zamowienie"
php artisan scout:import "App\Models\Clients\Klient"
php artisan scout:import "App\Models\Comm\Wiadomosc"

# 8. Dev server (wszystko na raz)
composer run dev  # → serve + queue:listen + reverb:start + vite

# 9. Docker alternatywa
docker compose up -d

# 10. Testy + formatter
vendor/bin/pint --dirty --format agent
./vendor/bin/pest --compact --coverage

# 11. Deploy
dep deploy prod
```

### Appendix B — Zmienne środowiskowe `.env`

```env
# App
APP_NAME="Drukarnia ERP"
APP_ENV=production
APP_KEY=base64:...
APP_DEBUG=false
APP_URL=https://erp.drukarnia.pl
APP_TIMEZONE=Europe/Warsaw

# Database
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=drukarnia_erp
DB_USERNAME=drukarnia
DB_PASSWORD=

# Redis / Cache / Queue
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
BROADCAST_DRIVER=reverb

# Filesystem
FILESYSTEM_DISK=local_private
BACKUP_PATH=/mnt/backup/drukarnia-erp

# Mail (Postfix relay)
MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=erp@drukarnia.pl
MAIL_FROM_NAME="Drukarnia XYZ"

# Reverb
REVERB_APP_ID=app-id
REVERB_APP_KEY=app-key
REVERB_APP_SECRET=app-secret
REVERB_HOST=erp.drukarnia.pl
REVERB_PORT=443
REVERB_SCHEME=https
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"

# Meilisearch
SCOUT_DRIVER=meilisearch
MEILISEARCH_HOST=http://127.0.0.1:7700
MEILISEARCH_KEY=

# Integracje
# GUS
GUS_API_URL=https://wyszukiwarkaregon.stat.gov.pl/wsBIR/UslugaBIRzewnPubl.svc
GUS_API_KEY=
GUS_API_ENV=prod

# Subiekt nexo
SUBIEKT_API_URL=http://subiekt-local:5000
SUBIEKT_API_KEY=
SUBIEKT_SFERA_USER=
SUBIEKT_SFERA_PASS=

# Przelewy24
P24_MERCHANT_ID=
P24_POS_ID=
P24_CRC_KEY=
P24_API_KEY=
P24_SANDBOX=false
P24_IP_WHITELIST=91.216.191.181,91.216.191.182

# InPost ShipX
INPOST_API_KEY=
INPOST_ORGANIZATION_ID=
INPOST_SANDBOX=false

# DPD / DHL / GLS
DPD_API_LOGIN=
DPD_API_PASSWORD=
DHL_API_USER=
DHL_API_PASSWORD=
GLS_API_USER=
GLS_API_PASSWORD=

# SMSAPI
SMSAPI_TOKEN=
SMSAPI_FROM=DRUKARNIA

# IMAP
IMAP_HOST=imap.gmail.com
IMAP_PORT=993
IMAP_USERNAME=erp@drukarnia.pl
IMAP_PASSWORD=
IMAP_ENCRYPTION=ssl
IMAP_VALIDATE_CERT=true

# OpenAI
OPENAI_API_KEY=
OPENAI_MODEL=gpt-4o
OPENAI_MAX_TOKENS=500

# Sentry
SENTRY_LARAVEL_DSN=
SENTRY_TRACES_SAMPLE_RATE=0.2

# Telescope (prod: disable)
TELESCOPE_ENABLED=false
```

### Appendix C — Zależności modułów

Z [05_MODULY_BIZNESOWE.md](./nowa%20dokumentacja/05_MODULY_BIZNESOWE.md) sekcja końcowa:

```
  CommHub ←────────── Orders ─────────→ Pricing
     │                 │                   │
     │          ┌──────┴──────┐           │
     │          ▼             ▼           │
     │      Production    DAMS ←──── Design Editor
     │          │          │
     │          └──┬───────┘
     │             ▼
     │          Finance ──→ Integrations (Subiekt/P24/KSeF)
     │             │
     │             ▼
     │          Logistics ──→ Integrations (kurierzy)
     │
     ▼
  Notifications → wszystkie moduły

  Shop ──→ Pricing, Orders, Finance, CommHub
  Approvals ──→ Orders, DAMS, CommHub
  Complaints ──→ Orders, Finance (korekty), CommHub
  Reports ──→ wszystkie (tylko odczyt)
  RBAC ──→ wszystkie (policies)
  Quotes ──→ Pricing, Orders, CommHub
```

**Żelazna zasada:** moduły komunikują się wyłącznie przez **eventy** lub **interfejsy serwisów** (DI). Brak bezpośrednich zapytań Eloquent między domenami w Controllers — tylko w Services danego modułu.

### Appendix D — Checklist przed merge do `main`

- [ ] `vendor/bin/pint --test` zielone.
- [ ] `./vendor/bin/pest` zielone (wszystkie testy).
- [ ] Coverage `app/Services/Pricing` ≥80%.
- [ ] Nowa migracja ma `down()` rollback method.
- [ ] Nowy endpoint ma rate limit middleware.
- [ ] Nowy model ma `Policy` jeśli jest dostępny z frontendu.
- [ ] Nowy komponent Vue używa shadcn-vue (nie custom Tailwind).
- [ ] Nowy listener ma `ShouldQueue` jeśli robi I/O.
- [ ] Nowy job ma `$tries`, `$backoff`, `retryUntil`.
- [ ] `npm run build` przechodzi bez warnings.
- [ ] RBAC: testy 403 dla roli bez uprawnień.
- [ ] `composer run dev` działa.
- [ ] Brak wzmianek o `MinIO`, `S3`, `aws-s3-v3` w kodzie.
- [ ] Nowe tabele domenowe — polskie `snake_case`; pricing — angielskie.
- [ ] Brak `float` dla pieniędzy — używać `BigDecimal`.

### Appendix E — Różnice z poprzednim `roadmap.md` (v1 → v2)

| Obszar | v1 (zastąpione) | v2 (aktualne) |
|--------|-----------------|---------------|
| Storage | MinIO / S3 | **Lokalny dysk** (`storage/app/private/`) + `spatie/laravel-medialibrary` + `tus-php` |
| Pricing | Ad-hoc `RegulaCenowa` / `RabatDefinicja` / `KosztWlasny` | **12-krokowy `PricingPipeline`** + 3 silniki (Sheet/LinearMeter/Multipage) + 49 testów 1:1 |
| Liczba modułów | 16 | **18** (dodane: Quotes, Shop, Design Editor; rozdzielone Products od Pricing) |
| FSM | 16 stanów | **19 stanów** (+PROJEKTOWANIE, +OCZEKUJE_NA_AKCEPTACJE, +REWIZJA, +ODRZUCONE, +PRODUCTION_HOLD, +ODBIOR_OSOBISTY, +DOSTARCZONE) |
| Fazy | 3 (MVP / Portal / Analytics) | **4** (0 Scaffolding 3d / 1 MVP 10t / 2 Portal 8–10t / 3 Shop 10–12t / 4 Enterprise otwarte) |
| Scaffolding | Ręczny setup ~2 tyg. | **Breeze + shadcn-vue + Filament** — 3 dni |
| UI components | Custom + surowy Tailwind | **shadcn-vue only** + `lucide-vue-next` + kolory semantyczne |
| Runtime PHP | php-fpm | php-fpm lub **Octane + FrankenPHP** opcjonalnie |
| Adaptery HTTP | Custom `BaseAdapter` | **`saloonphp/saloon` v3** |
| Pieniądze | float / casts | **`brick/math` `BigDecimal`** scale 10 (zakaz float) |
| Upload | Custom chunked | **tus.io** (`ankitpokhrel/tus-php`) resumable |
| Deploy | Docker Compose prod | **1× VPS Ubuntu 24.04 + Deployer v7 + supervisor + Nginx X-Accel-Redirect** |
| Preflight | Faza 2 (callas) | **Faza 4** (odłożone — MVP nie potrzebuje) |
| AI | Faza 3 (full) | Faza 2 (basic, OpenAI GPT-4o + PII anonimizacja) / Faza 4 (full agent) |
| Shop | Nieobecny | **Faza 3** (moduł 17 — e-commerce z Omnibus) |
| Design Editor | Nieobecny | **Faza 3** (moduł 18 — Konva.js + PDF export CMYK) |

---

**Koniec dokumentu roadmap.md v2.**

**Data utworzenia:** 2026-04-24.
**Autor:** Chief Software Architect (AI-assisted).
**Źródło prawdy:** [`nowa dokumentacja/00_INDEX.md`](./nowa%20dokumentacja/00_INDEX.md).

