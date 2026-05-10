# 09 — SZYBKI START MVP: SCAFFOLDING

> **Cel:** skrócić setup MVP z ~2 tygodni do **~3 dni**, żeby Day 4 już pisać logikę biznesową.
> **Zasada:** używać *wszystkich* gotowych starter-kitów Laravela, które nie konfliktują z naszą polityką UI (shadcn-vue-only) i naszymi ograniczeniami (brak S3, brak Jetstream teams).

---

## 1. Hierarchia wyborów (w kolejności uruchamiania)

### Krok 1 (Dzień 1): Laravel + Breeze

```bash
composer create-project laravel/laravel drukarnia-erp "11.*"
cd drukarnia-erp
composer require laravel/breeze --dev
php artisan breeze:install vue --inertia --ssr --typescript --pest --dark
npm install
npm run build
```

**Co dostajemy:**
- Login / Register / Forgot Password / Reset / Email Verification / Profile Update — gotowe strony Inertia+Vue.
- Tailwind 3 + `cn()` helper.
- Layouts `AuthenticatedLayout.vue` + `GuestLayout.vue`.
- Pest zamiast PHPUnit (szybsze testy).
- Dark mode toggle (bez konfiguracji).
- TypeScript ready.

**Dlaczego Breeze, nie Jetstream:**
- Jetstream zawiera **Teams** (niepotrzebne — 1 drukarnia = 1 tenant) i **Livewire components** (konflikt z naszym „Inertia+Vue+shadcn-vue only").
- Breeze ma tylko to, co naprawdę musimy: auth + layout. Komponenty usuwamy i zastępujemy shadcn-vue.

### Krok 2 (Dzień 1, ~1 h): shadcn-vue init

```bash
npx shadcn-vue@latest init
```

Wybór opcji podczas init:
- Style: `default`
- Base color: `neutral` (lub `zinc`)
- CSS variables: `yes`

Następnie dodaj „zestaw startowy" od razu:
```bash
npx shadcn-vue@latest add button card dialog form input label select sheet table tabs toast dropdown-menu avatar badge skeleton command separator alert tooltip scroll-area command popover checkbox radio-group switch textarea calendar date-picker
```

Rezultat: komplet komponentów w `resources/js/Components/ui/`. **Od tego momentu każda forma, modal, tabela używa tego — zero ręcznego HTML/Tailwind.**

Usuń komponenty z Breeze (`resources/js/Components/InputError.vue`, `PrimaryButton.vue`, etc.) — zastąp odpowiednikami shadcn-vue.

### Krok 3 (Dzień 1): Pakiety core

```bash
composer require \
  inertiajs/inertia-laravel \
  tightenco/ziggy \
  laravel/horizon \
  laravel/reverb \
  laravel/fortify \
  laravel/sanctum \
  spatie/laravel-permission \
  spatie/laravel-model-states \
  spatie/laravel-activitylog \
  spatie/laravel-medialibrary \
  spatie/pdf-to-image \
  intervention/image \
  ankitpokhrel/tus-php \
  saloonphp/saloon \
  laravel/scout \
  meilisearch/meilisearch-php \
  brick/math \
  league/csv
```

```bash
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan vendor:publish --provider="Spatie\ActivityLog\ActivityLogServiceProvider"
php artisan vendor:publish --provider="Spatie\MediaLibrary\MediaLibraryServiceProvider" --tag="medialibrary-migrations"
php artisan horizon:install
php artisan reverb:install
php artisan fortify:install
php artisan sanctum:install
php artisan migrate
```

### Krok 4 (Dzień 1): Docker Compose dev

`docker-compose.yml`:
```yaml
services:
  app:
    build: ./docker/app
    volumes: [".:/var/www/html"]
    depends_on: [db, redis, meilisearch]
    environment:
      - APP_URL=http://localhost
      - DB_HOST=db
      - REDIS_HOST=redis
      - MEILISEARCH_HOST=http://meilisearch:7700
    ports: ["9000:9000"]

  nginx:
    image: nginx:1.25-alpine
    ports: ["80:80"]
    volumes:
      - ".:/var/www/html"
      - "./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf"
    depends_on: [app]

  db:
    image: postgres:16
    environment:
      POSTGRES_DB: drukarnia_erp
      POSTGRES_USER: drukarnia
      POSTGRES_PASSWORD: secret
    volumes: ["pgdata:/var/lib/postgresql/data"]
    ports: ["5432:5432"]

  redis:
    image: redis:7-alpine
    ports: ["6379:6379"]
    volumes: ["redisdata:/data"]

  meilisearch:
    image: getmeili/meilisearch:v1.11
    environment:
      MEILI_MASTER_KEY: devkey
    ports: ["7700:7700"]
    volumes: ["meilidata:/meili_data"]

  mailpit:
    image: axllent/mailpit
    ports: ["1025:1025", "8025:8025"]

  horizon:
    build: ./docker/app
    command: php artisan horizon
    volumes: [".:/var/www/html"]
    depends_on: [app, redis]

  reverb:
    build: ./docker/app
    command: php artisan reverb:start --host=0.0.0.0 --port=8080
    volumes: [".:/var/www/html"]
    ports: ["8080:8080"]
    depends_on: [app, redis]

volumes:
  pgdata:
  redisdata:
  meilidata:
```

**Nie ma MinIO.** Pliki w lokalnym volume `storage/app/private/` (mapowanym z `.`).

```bash
docker compose up -d
php artisan migrate --seed
npm run dev
```

`docker compose up` w **<3 min** od zero.

### Krok 5 (Dzień 2): Filament dla panelu admin

```bash
composer require filament/filament:"^3.0" -W
php artisan filament:install --panels
php artisan make:filament-user
```

Filament tworzy panel w `/admin` (osobny od Inertia+Vue frontendu menedżera/klienta).

**Kluczowa decyzja:** Filament używa **Livewire** + Tailwind — **nie koliduje** z naszą polityką „shadcn-vue only":
- Polityka UI dotyczy frontendu klienta/menedżera (Inertia+Vue).
- Filament to oddzielna aplikacja pod `/admin` — własny layout, własne komponenty.
- Nikt inny niż Admin nie wchodzi do `/admin` — spójność wizualna zapewniona przez rolę.

**Zasoby do wygenerowania Dzień 2:**
```bash
# Pricing config (moduł 4)
php artisan make:filament-resource GlobalSetting --no-interaction
php artisan make:filament-resource PatternSetting --no-interaction
php artisan make:filament-resource ProjectSetting --no-interaction
php artisan make:filament-resource CustomFormatSetting --no-interaction
php artisan make:filament-resource MediaFormat --no-interaction
php artisan make:filament-resource Format --no-interaction
php artisan make:filament-resource Material --no-interaction
php artisan make:filament-resource ZadrukOption --no-interaction
php artisan make:filament-resource TimeOption --no-interaction
php artisan make:filament-resource PageConfig --no-interaction
php artisan make:filament-resource Parameter --no-interaction
php artisan make:filament-resource SimpleParameter --no-interaction
php artisan make:filament-resource Product --no-interaction

# Domena
php artisan make:filament-resource Klient --no-interaction
php artisan make:filament-resource Zamowienie --no-interaction
php artisan make:filament-resource DokumentFinansowy --no-interaction
php artisan make:filament-resource Maszyna --no-interaction
```

Każdy resource: ~5 min customizacji (kolumny, filtry) — w 1 dzień mamy gotowy panel admina dla całego pricingu + domeny. **Bez niego napisalibyśmy ~2 tygodnie ręcznie CRUD-y.**

### Krok 6 (Dzień 2): Seed domenowy

Przepisać `seed_demo.py` z `iwp_calc_generator-main` → `database/seeders/PricingDemoSeeder.php`:
```bash
php artisan make:seeder PricingDemoSeeder
```

Daje od razu:
- GlobalSetting, PatternSetting, ProjectSetting, CustomFormatSetting z wartościami domyślnymi.
- 3 MediaFormat (SRA3, A4, Rolka 610mm).
- 3 Material (130g Kreda, 350g Kreda, Folia Monomeryczna).
- 3 ZadrukOption (4/0, 4/4, sublimacja).
- 2 TimeOption (Standard, Express).
- 4 PageConfig (4+8, 4+12, 4+20, 4+32).
- 3 Parameter (Uszlachetnianie, Składanie, Narożniki) + opcje.
- 3 produkty kalkulowane (Ulotki A4, Wizytówki, Banner vinylowy) + 1 prosty (Kubki firmowe).

**Po seed:** pipeline testowalny przy uruchomieniu `POST /api/pricing/calculate` już w Dniu 2.

### Krok 7 (Dzień 3): CI i deploy pipeline

`.github/workflows/ci.yml`:
```yaml
name: CI
on: [push, pull_request]
jobs:
  test:
    runs-on: ubuntu-latest
    services:
      postgres:
        image: postgres:16
        env: { POSTGRES_DB: testing, POSTGRES_USER: test, POSTGRES_PASSWORD: test }
        ports: ["5432:5432"]
      redis: { image: redis:7, ports: ["6379:6379"] }
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.3', extensions: bcmath, pdo, pgsql, redis, intl, gd, imagick }
      - run: composer install --prefer-dist --no-progress
      - run: cp .env.ci .env && php artisan key:generate && php artisan migrate
      - run: vendor/bin/pint --test
      - run: ./vendor/bin/pest --compact
      - uses: actions/setup-node@v4
        with: { node-version: '20' }
      - run: npm ci && npm run build
```

Blokuje merge do `main` bez zielonego buildu.

### Krok 8 (Dzień 3): `composer dev` dev server jednym przyciskiem

```json
// composer.json
"scripts": {
  "dev": [
    "Composer\\Config::disableProcessTimeout",
    "npx concurrently -c '#93c5fd,#c4b5fd,#fb7185,#fcd34d' \"php artisan serve\" \"php artisan queue:listen --tries=1\" \"php artisan reverb:start\" \"npm run dev\" --names=serve,queue,reverb,vite"
  ]
}
```

`composer run dev` → 4 procesy równolegle z kolorowym logiem. **Nigdy** nie biegać osobno w 4 terminalach.

### Krok 9 (Dzień 3): Reverb + Echo frontend

```bash
npm install laravel-echo pusher-js
```

`resources/js/echo.ts`:
```ts
import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
window.Pusher = Pusher
window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT,
    forceTLS: false,
    enabledTransports: ['ws', 'wss'],
})
```

### Krok 10 (Dzień 3): AppLayout z sidebar

Oparty na shadcn `Sheet` + `Command`:

```vue
<!-- resources/js/Layouts/AppLayout.vue -->
<template>
    <div class="flex h-screen">
        <aside class="hidden md:flex w-64 border-r flex-col gap-2 p-4">
            <Button variant="ghost" as-child>
                <Link href="/dashboard"><LayoutDashboard /> Dashboard</Link>
            </Button>
            <Button variant="ghost" as-child v-if="can('orders.view')">
                <Link href="/orders"><Package /> Zamówienia</Link>
            </Button>
            <!-- kolejne linki ze Ziggy route() + can() helper z useAuth -->
        </aside>

        <div class="flex-1 flex flex-col">
            <header class="flex items-center justify-between border-b px-4 py-2">
                <Sheet>
                    <SheetTrigger as-child>
                        <Button variant="ghost" size="icon" class="md:hidden"><Menu /></Button>
                    </SheetTrigger>
                    <SheetContent side="left">...sidebar mobilny...</SheetContent>
                </Sheet>

                <Button variant="ghost" size="icon">
                    <Bell /> <Badge v-if="unread > 0">{{ unread }}</Badge>
                </Button>

                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Avatar>
                            <AvatarFallback>{{ initials }}</AvatarFallback>
                        </Avatar>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent>
                        <DropdownMenuItem as-child><Link href="/profile">Profil</Link></DropdownMenuItem>
                        <DropdownMenuItem as-child><Link method="post" href="/logout">Wyloguj</Link></DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </header>
            <main class="flex-1 overflow-auto p-4">
                <slot />
            </main>
        </div>
    </div>
</template>
```

---

## 2. Co świadomie pomijamy w MVP (Faza 1)

| Odłożenie | Do fazy | Uzasadnienie |
|-----------|---------|--------------|
| Konfigurator cenowy w UI klienta | 2/3 | MVP: menedżer liczy cenę w Filamencie przez akcję „Przelicz cenę" |
| Portal klienta | 2 | MVP: komunikacja przez email z CommHub wystarcza |
| Design Editor (Konva) | 3 | Złożoność frontendu — 6+ tygodni pracy |
| Sklep e-commerce | 3 | Przychody od istniejących klientów B2B wystarczą na ~6 mies. |
| Pełne Reklamacje (RMA) | 2 | MVP: menedżer tworzy zamówienie-przedruk ręcznie |
| VAT/Paragon/Korekta | 2 | MVP: tylko Proforma; VAT pozostaje w Subiekt nexo |
| Materialized views | 3 | MVP: prosty dashboard on-the-fly |
| SMS notyfikacje | 2 | MVP: tylko email |
| Własna integracja KSeF | 4 | Subiekt nexo załatwia w MVP |
| 2FA | 2 | MVP: silne hasła + email verification |

---

## 3. Konkretny timeline Fazy 1 (10 tygodni, 1 full-time dev)

| Tydzień | Zadania | Status |
|---------|---------|--------|
| 1 | Scaffolding (Dni 1–3) + modele Pricing + Filament basic + PricingDemoSeeder | |
| 2 | `PricingPipeline` + 3 silniki + kalkulatory + SettingResolver; 49 testów — część 1 (CalculatorTest + SimplePricingTest) | |
| 3 | `PricingPipeline` integracja + MultipageEngine + pozostałe testy (PipelineTest + MultipageEngineTest) | |
| 4 | CRM (klienci, osoby, GUS adapter), Orders (zamówienia, pozycje, FSM states) | |
| 5 | DAMS (tus-php, Media Library, FilePolicy, thumbnails, nginx X-Accel) | |
| 6 | Production (Kanban podstawowy), Approvals (simple — bez markup; Faza 2: markup) | |
| 7 | Finance — Proforma + adapter Subiekt (stub) + Przelewy24 adapter + webhooks | |
| 8 | Logistics — InPost adapter + label generation + tracking webhook | |
| 9 | CommHub — IMAP poller + outbound email + templates + inbox UI | |
| 10 | E2E test scenariusz (email → zamówienie → wycena → pliki → produkcja → wysyłka) + bug fixing + deploy | |

---

## 4. Co NIE wybrać (antyrekomendacje)

| Narzędzie | Dlaczego nie |
|-----------|-------------|
| **Laravel Jetstream** | Teams niepotrzebne, Livewire + Jetstream UI łamie shadcn-vue only |
| **Laravel Nova** | Płatne ($199/site) — Filament v3 darmowy i lepszy |
| **Tailwind UI** | Płatne + łamie politykę shadcn-vue only |
| **Vuetify / PrimeVue / Quasar** | Łamie politykę shadcn-vue only |
| **MinIO / AWS S3 / DO Spaces** | Jawny zakaz (patrz 08) — wszystko lokalnie |
| **Pusher** | Płatne — Reverb (Laravel-native) darmowe i bez limitów |
| **Beanstalkd / Supervisor standalone** | Horizon załatwia wszystko |
| **Laravel Breeze API-only** | Nam potrzebny Inertia+Vue, nie plain API |
| **React / Alpine.js dla UI** | Konflikt z Vue — jeden framework UI w całym projekcie |
| **Axios** | Inertia v3 ma wbudowany XHR client z interceptorami |

---

## 5. Metryki „gotowości" po 3 dniach

Po wykonaniu kroków 1–10:

- [ ] `php artisan serve` odpowiada na `/` (Welcome).
- [ ] `php artisan migrate` przechodzi (struktura pricingu gotowa).
- [ ] `php artisan db:seed --class=PricingDemoSeeder` ustawia dane demo.
- [ ] `/admin` → login Admin → widać panele CRUD dla wszystkich zasobów Pricing + Klient + Zamowienie.
- [ ] `npm run dev` serwuje HMR.
- [ ] `docker compose up -d` w <3 min.
- [ ] `vendor/bin/pest` → zielone (nawet bez napisanych biznesowych testów — domyślny test sanity Breeze).
- [ ] CI pipeline na GitHub przechodzi na świeżym pushu.
- [ ] Login/Register/ProfileUpdate działają (z Breeze).
- [ ] Sidebar + shadcn-vue komponenty renderują się na dashboardzie.

**Day 4:** commitujesz i zaczynasz pisać logikę biznesową, nie boilerplate.

---

## 6. Repozytorium referencyjne

Jeżeli ktoś chce odtworzyć te kroki: przygotuj **publiczny starter template** `github.com/firma/drukarnia-erp-starter` po Kroku 10 (przed logiką biznesową). Następny projekt startuje od `composer create-project firma/drukarnia-erp-starter nazwa-projektu` — sekundy zamiast dni.
