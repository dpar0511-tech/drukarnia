# PLAN ARCHITEKTONICZNY ERP — Drukarnia (Web2Print + Sklep + Design Editor)

> **Wersja:** 2.0  
> **Data:** 2026-04-19  
> **Autor:** Claude (Opus 4.7) na zlecenie właściciela drukarni  
> **Zakres:** Pełna architektura ERP dla drukarni z silnikiem Web2Print, sklepem internetowym zintegrowanym z portalem klienta oraz edytorem projektów online  
> **Źródło prawdy dla cen:** `plan_implementacji_modul_web2print_v2.md` (nie podlega modyfikacji)

---

## Spis treści

- [CZĘŚĆ I — Kontekst, cele i zakres](#część-i--kontekst-cele-i-zakres)
- [CZĘŚĆ II — Architektura wysokiego poziomu](#część-ii--architektura-wysokiego-poziomu)
- [CZĘŚĆ III — MODUŁ 4: Silnik cenowy Web2Print](#część-iii--moduł-4-silnik-cenowy-web2print)
- [CZĘŚĆ IV — MODUŁ 17: Sklep internetowy](#część-iv--moduł-17-sklep-internetowy)
- [CZĘŚĆ V — MODUŁ 18: Design Editor (Konva.js)](#część-v--moduł-18-design-editor-konvajs)
- [CZĘŚĆ VI — Portal klienta zintegrowany ze sklepem](#część-vi--portal-klienta-zintegrowany-ze-sklepem)
- [CZĘŚĆ VII — Zmiany w pozostałych modułach](#część-vii--zmiany-w-pozostałych-modułach)
- [CZĘŚĆ VIII — Pełny workflow (scenariusze A→Z)](#część-viii--pełny-workflow-scenariusze-az)
- [CZĘŚĆ IX — Skonsolidowany schemat bazy danych](#część-ix--skonsolidowany-schemat-bazy-danych)
- [CZĘŚĆ X — Mapa API i routingu](#część-x--mapa-api-i-routingu)
- [CZĘŚĆ XI — Architektura frontendu (Vue 3 + Inertia + shadcn-vue)](#część-xi--architektura-frontendu)
- [CZĘŚĆ XII — Bezpieczeństwo i zgodność z prawem](#część-xii--bezpieczeństwo-i-zgodność-z-prawem)
- [CZĘŚĆ XIII — Strategia testowania](#część-xiii--strategia-testowania)
- [CZĘŚĆ XIV — Roadmap fazowy](#część-xiv--roadmap-fazowy)
- [CZĘŚĆ XV — Weryfikacja i kryteria akceptacji](#część-xv--weryfikacja-i-kryteria-akceptacji)

---

## CZĘŚĆ I — Kontekst, cele i zakres

### 1.1. Kontekst biznesowy

System ERP budowany jest dla polskiej drukarni cyfrowej i offsetowej realizującej zlecenia zarówno dla klientów B2B (firmy, agencje reklamowe, korporacje) jak i B2C (osoby fizyczne, mikro-firmy). Drukarnia oferuje szeroki katalog produktów:

- **Wizytówki** (standardowe i premium — lakier UV, folia, wytłoczenia)
- **Ulotki** (A4, A5, A6, DL) — składane i niezłożone
- **Plakaty** (A0–A4) — papier powlekany, kreda, offset
- **Katalogi i broszury** (zszywane, klejone, szyte)
- **Roll-upy i banery** (PCV, siatka, folia blockout — **linear_meter engine**)
- **Naklejki** (cięte, wykrawane, rolkowe)
- **Torby papierowe** (z nadrukiem i bez)
- **Opakowania indywidualne** (kartoniki — projekt na wymiar, **piece engine**)
- **Wielkoformat** (plotter laserowy, ksero A3+)

Obecny przepływ pracy (ręczny, mailowy) generuje wąskie gardła:

1. Wycenianie trwa 15–60 min na zapytanie; 40 % klientów rezygnuje w międzyczasie
2. Zamówienia krążą w skrzynce mailowej — brak centralnego backlogu dla maszyn
3. Klient nie wie, na jakim etapie jest jego zlecenie — dzwoni kilka razy dziennie
4. Reklamacje giną; brak śladu akceptacji pliku do druku przez klienta
5. Ręczne wystawianie faktur w Subiekcie (podwójna praca)
6. Stany magazynowe papieru znane tylko „z głowy" kierownika produkcji

### 1.2. Cele systemu (SMART)

| # | Cel | Metryka | Termin |
|---|-----|---------|--------|
| C1 | Redukcja czasu wyceny o 90 % | Z 15 min → < 90 s (via Web2Print) | ETAP 2 |
| C2 | Samoobsługa cenowa dla klientów | ≥ 70 % zamówień B2C wyceniane w sklepie bez udziału operatora | ETAP 3 |
| C3 | Centralny backlog produkcyjny | 100 % zleceń widocznych na Kanbanie z maszynami | ETAP 2 |
| C4 | Akceptacja pliku do druku z logiem | Każde zlecenie PRODUCTION ma podpisane „Approval" z IP i timestamp | ETAP 2 |
| C5 | Automatyczne faktury VAT | Proforma → Faktura Zaliczkowa → VAT generowane bez udziału księgowej | ETAP 3 |
| C6 | Portal klienta | 50 % powracających klientów loguje się do portalu | ETAP 3 |
| C7 | Design Editor online | 20 % wizytówek projektowanych w edytorze (zamiast PDF z zewnątrz) | ETAP 3 |
| C8 | Zgodność z KSeF i MPP | 100 % faktur B2B >15 k PLN wystawiane z MPP i wysyłane do KSeF | ETAP 3 |

### 1.3. Zakres tej zmiany architektonicznej

Ten plan wprowadza **trzy fundamentalne zmiany** do wcześniejszego `MASTER_PLAN.md`:

#### Zmiana 1 — Zastąpienie MODUŁU 4 (Pricing & Loyalty) silnikiem Web2Print

Poprzedni model (`RegulaCenowa`, `IndywidualyCennik`, `RabatDefinicja`, `KalkulacjaCeny`) był zbyt prymitywny — nie obsługiwał:
- Kalkulacji impozycji (ile użytków na arkuszu)
- Odpadu technologicznego (waste) zależnego od materiału i rozmiaru nakładu
- Dwupoziomowych marż z progiem przełączenia
- Mnożników wzorca (pattern) i czasu (przyspieszone terminy)
- Reguł wykluczeń (np. „lakier UV niedostępny na papierze 80 g/m²")
- Różnych silników dla różnych typów produktów (arkuszowy vs. metrowy vs. sztukowy)

Nowy silnik oparty o `plan_implementacji_modul_web2print_v2.md` implementuje **11-krokowy PricingPipeline** z trzema strategiami silnika (`SheetEngine`, `LinearMeterEngine`, `PieceEngine`). Źródło prawdy spec jest **nienaruszalne** — porty i adaptacje dotyczą wyłącznie warstwy prezentacji (Filament/Livewire → Inertia + Vue 3 + shadcn-vue).

Elementy z poprzedniego MODUŁU 4 (lojalność, rabaty dla stałych klientów, RKW) **pozostają**, ale jako **nakładki** (overlays) na wynik pipeline'u:
- **Loyalty Overlay** — aplikowany po kroku 11 (mnożnik `1 − % rabatu stałego klienta`)
- **RKW Overlay** — osobny „shadow calculator" uruchamiany równolegle, nie wpływa na cenę sprzedaży, liczy koszt rzeczywisty dla księgowości

#### Zmiana 2 — Dodanie MODUŁU 17 (Sklep internetowy) zintegrowanego z Portalem klienta

Sklep jest **jedyną bramą wejścia** do portalu klienta:

- Niezalogowany użytkownik widzi katalog i konfigurator produktu
- Może dodać do koszyka i zapłacić jako **guest** (z e-mailem) — zamówienie pojawia się w ERP z flagą `guest_order = true`
- Może zarejestrować się przed checkout — otrzymuje konto portalowe
- Po opłaceniu guest otrzymuje mail „dokończ rejestrację jednym klikiem" (magic link); kliknięcie łączy zamówienie z nowym kontem
- Zalogowany klient (B2B lub B2C) widzi te same strony produktowe, ale z cenami po swoim cenniku indywidualnym (jeśli istnieje) i rabatach lojalnościowych

**Sklep używa tego samego silnika cenowego co backend** — nie ma dublowania logiki. Kalkulator w sklepie jest UI-nakładką na to samo API, które używają operatorzy w module Order Management.

#### Zmiana 3 — Dodanie MODUŁU 18 (Design Editor, Konva.js + vue-konva)

Edytor projektów online pozwala klientowi:
- Wybrać szablon z biblioteki (np. „wizytówka lekarza", „ulotka restauracji")
- Wgrać własne logo i zdjęcia
- Edytować tekst, zmieniać fonty (z licencjonowaniem Adobe Fonts / własnej biblioteki)
- Dodawać kształty, obramowania, efekty
- Podgląd front/back dla wizytówek dwustronnych
- Eksport do PDF zgodnego z preflightem (CMYK 300 dpi, spad 3 mm, linie tnące)

Edytor jest zbudowany na Konva.js jako engine canvas i vue-konva jako warstwa reaktywna. Projekty zapisywane są jako JSON (stan sceny) w DAMS z wersjonowaniem — klient może wrócić i edytować. Ostateczny PDF generowany jest serwerowo przez headless Chromium + jsPDF lub przez osobny microservice oparty na `puppeteer-core` + `canvas` (do wyboru w ETAP 3.5).

### 1.4. Nie-cele (poza zakresem)

- **Multi-tenant** — docelowo jedna instancja na drukarnię, nie SaaS
- **Obsługa walut innych niż PLN** w ETAPach 1–3 (euro/USD dopiero w Phase 4)
- **AI auto-preflight** — Phase 4 (ETAP 4.2) według ERP_WORKFLOW.md
- **Aplikacja mobilna natywna** — PWA wystarczy
- **System CAD dla opakowań** — na razie projekty opakowań przyjmowane jako zewnętrzne PDF-y z dielines

### 1.5. Założenia i ograniczenia

- Budżet developerski: 1 pełnoetatowy dev + okazjonalna pomoc AI (właściciel + Claude/Gemini)
- Hosting: własny serwer VPS (Ubuntu 24.04, 16 GB RAM, 8 vCPU, 500 GB SSD) + MinIO na drugim dysku
- Backup: codzienny pg_dump + rsync do S3-compatible (Backblaze B2)
- Język UI: polski (PL); przygotowanie i18n dla EN/UK na przyszłość
- Zgodność z RODO/GDPR i ustawą o rachunkowości (księgi 5 lat)

### 1.6. Deliverables tego planu

1. Pełny opis architektury, modułów i integracji (niniejszy dokument)
2. Zaktualizowany `MASTER_PLAN.md` — wersja po wdrożeniu niniejszego planu
3. Zaktualizowany `roadmap.md` z rozbiciem ETAPów i konkretnymi commitami/tickety
4. Skonsolidowany schemat bazy danych z migracjami
5. Lista routingu API / Inertia
6. Dokument onboardingu dla nowego developera (oparty na tym planie)

---

## CZĘŚĆ II — Architektura wysokiego poziomu

### 2.1. Stos technologiczny (bez zmian względem MASTER_PLAN)

#### 2.1.1. Backend

| Warstwa | Technologia | Wersja | Uzasadnienie |
|---------|-------------|--------|--------------|
| Runtime | PHP | 8.3 | JIT, typed properties, readonly classes, fibers |
| Framework | Laravel | 11 | Slim structure, bootstrap/app.php middleware config |
| DB | PostgreSQL | 16 | JSONB, Generated Columns, partycjonowanie, GiST |
| Cache / Queue | Redis | 7 | Horizon, cache:lock, queue priority |
| Queue Supervisor | Laravel Horizon | 5 | UI, metrics, retry policies |
| WebSocket | Laravel Reverb | 1 | Natywny Laravel, WebSocket + events |
| Search | Meilisearch | 1.x | Typo-tolerant, Polish tokenizer |
| Obiekty | MinIO (S3) | latest | On-prem S3, pliki źródłowe, projekty, PDF-y |
| HTTP Client | Guzzle | 7 | Integracje REST (Subiekt, GUS, kurierzy) |
| Auth | Sanctum + Fortify | 4 + 1 | API tokens + SPA, 2FA-ready |
| Permissions | spatie/laravel-permission | 6 | Roles & permissions |
| State Machines | spatie/laravel-model-states | 2 | 16 stanów zamówienia, FSM |
| Audit Log | spatie/laravel-activitylog | 4 | GDPR, zmiany modeli |
| Upload resumable | ankitpokhrel/tus-php | 2 | Wznawialny upload dużych plików |
| PDF → raster | Ghostscript + ImageMagick | 10 + 7 | Miniatury, preview |
| PDF inspekcja | Imagick / mPDF | latest | Validatory preflight (Phase 4) |
| AI | openai-php/laravel | 0.x | GPT-4o routing, chatbot, auto-brief |
| Routes in JS | tightenco/ziggy | 2 | route() w Vue |
| Formatowanie | Laravel Pint | 1 | PSR-12 + custom |
| MCP | laravel/mcp | 0.x | Artisan MCP server dla AI |
| Debugging | Laravel Pail | 1 | tail logs in real-time |

#### 2.1.2. Frontend

| Warstwa | Technologia | Wersja | Uzasadnienie |
|---------|-------------|--------|--------------|
| UI Layer | Vue 3 (composition API, `<script setup>`) | 3.4+ | Reactivity, typed props, defineProps/defineEmits |
| Bridge | Inertia.js | v3 | SPA bez REST API dla CRUD; useForm, useHttp |
| Layout | Persistent Layouts + useLayoutProps | v3 | Jeden layout, różne strony |
| CSS | Tailwind CSS | 3.x | Utility-first, dark mode |
| UI Kit | shadcn-vue | latest | Jedyne źródło komponentów UI (STRICT) |
| Headless | radix-vue / reka-ui | latest | Primitives pod shadcn |
| Ikony | lucide-vue-next | latest | Jedyna biblioteka ikon |
| Form/Validation | vee-validate + zod | 4 + 3 | Dla formularzy niestandardowych (np. konfigurator) |
| Charts | Chart.js + vue-chartjs | 4 + 5 | Dashboardy, KPI |
| Canvas | **Konva.js + vue-konva** | 9 + 3 | Design Editor (MODUŁ 18) |
| PDF viewer | PDF.js | 4 | Podgląd plików klienta |
| WebSocket client | laravel-echo + pusher-js | 2 + 8 | Reverb |
| Builder | Vite | 5 | HMR, SSR dev |

#### 2.1.3. DevOps i infrastruktura

- Deployment: **Laravel Octane + FrankenPHP** (lub Swoole) dla hot-reload produkcyjnego
- CI/CD: GitHub Actions → PHPUnit + Pint + NPM build + deploy via SSH + `php artisan migrate --force` + `php artisan horizon:terminate`
- Monitoring: Laravel Horizon + Pulse (Laravel native) + Uptime Kuma (self-hosted)
- Logs: Laravel Log → file daily + ship do Loki (opcjonalnie w Phase 4)
- Backup: cron codziennie `pg_dump --format=custom` + `mc mirror` MinIO → B2

### 2.2. Mapa 18 modułów (po zmianach)

```
┌──────────────────────────────────────────────────────────────────────┐
│                          MODUŁY RDZENIOWE                             │
├──────────────┬──────────────┬──────────────┬────────────────────────┤
│ 1. CRM       │ 2. Orders    │ 3. Products  │ 4. PRICING ENGINE      │
│              │              │              │    Web2Print           │
│              │              │              │    (NEW)               │
├──────────────┼──────────────┼──────────────┼────────────────────────┤
│ 5. Quotes    │ 6. Designs   │ 7. Files     │ 8. Production          │
│              │ (approval)   │ (DAMS)       │ (Kanban)               │
├──────────────┼──────────────┼──────────────┼────────────────────────┤
│ 9. Inventory │ 10. Finance  │ 11. Logistics│ 12. Complaints         │
│              │ (Subiekt)    │ (Kurierzy)   │ (RMA)                  │
├──────────────┼──────────────┼──────────────┼────────────────────────┤
│ 13. Comms    │ 14. Reports  │ 15. Settings │ 16. Integrations       │
│    Hub       │ & BI         │ & Users      │ (Subiekt/GUS/…)        │
├──────────────┼──────────────┼──────────────┼────────────────────────┤
│              │              │              │                        │
│ 17. SKLEP    │ 18. DESIGN   │  Portal      │                        │
│    (NEW)     │    EDITOR    │  klienta     │                        │
│              │    (NEW)     │  (w 17)      │                        │
├──────────────┴──────────────┴──────────────┴────────────────────────┤
│                      WARSTWA PRZEKROJOWA                             │
│   Events (domain) · Jobs (queue) · Listeners · Webhooks · MCP        │
└──────────────────────────────────────────────────────────────────────┘
```

### 2.3. Kanały wejścia zleceń (Order Channels)

| Kanał | Opis | Moduł źródłowy | `order.channel` |
|-------|------|----------------|-----------------|
| Operator (ręcznie) | Wprowadzenie przez operatora w panelu ERP | Orders | `manual` |
| E-mail parser | Ingest via IMAP + OpenAI brief extractor | CommHub | `email` |
| Telefon | Ręcznie wprowadzone po rozmowie (z linkiem do nagrania opcjonalnie) | CommHub | `phone` |
| **Sklep — guest** | Zamówienie z checkoutu bez konta | **Sklep (17)** | `shop_guest` |
| **Sklep — logged** | Zamówienie zalogowanego użytkownika | **Sklep (17)** | `shop_user` |
| **Portal klienta** | Ponowienie zamówienia („zamów jeszcze raz") | Portal (w 17) | `portal_repeat` |
| API B2B | Integracja zewnętrzna (np. API dla agencji) | API | `api` |
| Quote accepted | Wycena zaakceptowana → generuje zlecenie | Quotes (5) | `quote` |

### 2.4. Diagram przepływu zdarzeń (Event Bus)

```
[Sklep checkout]  [Operator new order]  [Email parser]  [Portal repeat]
        │                   │                  │                │
        └───────────────────┴──────────────────┴────────────────┘
                                   │
                                   ▼
                          ┌────────────────┐
                          │ OrderCreated   │ ← zdarzenie domenowe
                          └────────────────┘
                                   │
          ┌────────────┬───────────┼───────────┬────────────┐
          ▼            ▼           ▼           ▼            ▼
     PricingCalc   CommHub     Kanban      Finance     Integrations
     (listener)   (email+SMS) (tworzy    (proforma?)  (webhook do
                              zadanie)                 Subiekt?)
```

Zdarzenia domenowe (pełna lista) — patrz CZĘŚĆ VII § 7.6.

### 2.5. Kluczowe decyzje architektoniczne (ADR-style)

#### ADR-001: Użycie Inertia.js zamiast REST API + SPA

**Decyzja:** Inertia v3 dla całej warstwy ERP (backend operator + portal klienta)  
**Uzasadnienie:** Brak dublowania walidacji, natywne Vue komponenty, useForm Inertii = mniej boilerplate  
**Wyjątek:** Publiczny sklep (strony produktowe) — także Inertia, ale z SSR dla SEO; webhooki i integracje B2B — REST via Sanctum  
**Konsekwencje:** Brak osobnego OpenAPI dla frontend; trzeba trzymać Ziggy zsynchronizowany z routes

#### ADR-002: PostgreSQL 16 zamiast MySQL 8

**Decyzja:** PostgreSQL dla wszystkich środowisk  
**Uzasadnienie:** JSONB dla `price_breakdown`, `product_parameters.rules`, `design_editor_state`; Generated Columns dla `net_price`; partycjonowanie `activity_log` po dacie  
**Konsekwencje:** `phpredis` + `pgsql` PHP extensions; dev używa SQLite w testach, PG w staging/prod

#### ADR-003: shadcn-vue jako jedyne źródło UI

**Decyzja:** Wszystkie elementy UI używają komponentów z `resources/js/Components/ui/`; żadnego custom HTML/CSS dla elementów, które istnieją w shadcn-vue  
**Uzasadnienie:** Spójność, dark mode, a11y, dokumentacja  
**Konsekwencje:** Jeśli czegoś brakuje — dodajemy via `npx shadcn-vue@latest add <component>`; nie piszemy od zera

#### ADR-004: Separacja silnika cenowego od UI

**Decyzja:** Silnik cenowy to pure-PHP serwisy bez zależności od Eloquent query builder w logice cenowej (oprócz ładowania konfigu). Wszystkie wejścia są DTO (readonly classes)  
**Uzasadnienie:** Testowalność, możliwość użycia w console command, reużycie w API publicznym (sklep), w kokpicie operatora i w przyszłości w microservice cenowym  
**Konsekwencje:** Więcej klas (DTO), ale ~ x10 szybsze unit testy

#### ADR-005: Design Editor jako osobna aplikacja Vue w iframe lub route

**Decyzja:** Edytor ładowany jako osobna strona Inertia `/shop/editor/{designId}` z pełnym ekranem; komunikuje się z serwerem przez `useHttp` (autosave JSON sceny co 5 s)  
**Uzasadnienie:** Konva.js + vue-konva wymagają dużego bundle'a (~300 KB) — lazy-load tylko dla tej strony  
**Konsekwencje:** Vite code-splitting, osobny entry point w `vite.config.js`

#### ADR-006: Portal klienta = podzbiór Sklepu

**Decyzja:** Nie robimy osobnego subdomenu `portal.drukarnia.pl`; portal to prefix `/moje-konto/*` w tym samym app + tym samym layoucie co sklep, tylko z guardem `auth`  
**Uzasadnienie:** Jedna session, jedna cart, gładkie przejście z koszyka do „moich zamówień"  
**Konsekwencje:** Middleware `PortalGuard` + layout `PortalLayout` z różnym sidebarem, ale wspólnym headerem

#### ADR-007: Guest checkout z konwersją do konta

**Decyzja:** Guest może kupić bez konta (tylko email). Po płatności wysyłany magic-link do aktywacji konta z przypisanym zamówieniem  
**Uzasadnienie:** Konwersja B2C; 35 % klientów nie chce się rejestrować  
**Konsekwencje:** `users.is_guest` flag; cleanup job po 90 dniach nieaktywnych guestów

#### ADR-008: Cennik indywidualny = override na poziomie klienta

**Decyzja:** `client_pricing_overrides` — tabela z `client_id`, `product_id?`, `parameter_option_id?`, `margin_override?`, `discount_percent?` — aplikowana **przed** krokiem 5 pipeline'u Web2Print (wymiana marży)  
**Uzasadnienie:** Cennik indywidualny to część cenotwórstwa, nie overlay po  
**Konsekwencje:** Pipeline musi mieć „Client Context" injected na wejściu

#### ADR-009: Loyalty jako overlay post-pipeline

**Decyzja:** Lojalność nie wpływa na marżę, tylko daje `%` rabatu na końcową cenę netto  
**Uzasadnienie:** Prostota księgowa (rabat = linia na fakturze), przejrzystość dla klienta  
**Konsekwencje:** `LoyaltyResolver` po `PricingPipeline::execute()`

#### ADR-010: RKW (Koszt Własny) = shadow calculator

**Decyzja:** Dla zlecenia uruchamiany jest równoległy kalkulator „koszt rzeczywisty" (materiał + maszyna × czas + praca + amortyzacja), niezależny od ceny sprzedaży  
**Uzasadnienie:** Marża rzeczywista vs. oczekiwana = raport dla właściciela  
**Konsekwencje:** `CostOfGoodsCalculator` + tabela `order_cogs_breakdown`

### 2.6. Warstwy aplikacji (Clean Architecture Lite)

```
app/
├── Domain/                      # Pure-PHP, brak Eloquent w logice
│   ├── Pricing/
│   │   ├── DTOs/               # PricingRequest, PricingResult, EngineResult, …
│   │   ├── Engines/            # SheetEngine, LinearMeterEngine, PieceEngine
│   │   ├── Pipeline/           # PricingPipeline, Steps
│   │   ├── Services/           # MarginCalculator, ImpositionCalculator, …
│   │   └── Resolvers/          # ExclusionResolver, LoyaltyResolver
│   ├── Orders/
│   │   ├── DTOs/
│   │   ├── States/             # spatie state machine
│   │   ├── Events/             # OrderCreated, PriceCalculated, …
│   │   └── Actions/            # CreateOrderAction, TransitionOrderAction
│   ├── DesignEditor/
│   │   ├── DTOs/
│   │   ├── Exporters/          # PDFExporter, PNGPreviewExporter
│   │   └── Validators/         # SceneValidator (dim, bleed, DPI)
│   └── Shop/
│       ├── Cart/
│       ├── Checkout/
│       └── PromoCodes/
│
├── Models/                      # Eloquent models
├── Http/
│   ├── Controllers/
│   │   ├── Admin/              # Panel operatora
│   │   ├── Shop/               # Sklep + portal
│   │   ├── Api/                # REST dla webhooków i B2B
│   │   └── DesignEditor/       # Design editor endpoints
│   ├── Requests/               # FormRequests
│   ├── Resources/              # API Resources
│   └── Middleware/
├── Jobs/                        # Kolejki: ProcessUploadedFile, GenerateInvoice, …
├── Listeners/                   # OnOrderCreated/NotifyCustomer, …
├── Mail/                        # Mailables
├── Notifications/               # Notifiable (email+sms+db+broadcast)
├── Policies/                    # Authz
├── Providers/
├── Services/                    # Integracje: SubiektClient, GusClient, P24Client, …
└── Console/Commands/
```

### 2.7. Zasady nazewnictwa

- **Tabele DB**: snake_case, angielskie, liczba mnoga: `products`, `product_parameter_options`
- **Modele**: PascalCase singular: `Product`, `ProductParameterOption`
- **Eventy**: PascalCase, czas przeszły: `OrderCreated`, `PriceCalculated`
- **Joby**: PascalCase, czas teraźniejszy imperativ: `GenerateInvoice`, `ProcessUploadedFile`
- **Akcje** (use case): PascalCase + `Action`: `CreateOrderAction`, `CalculatePriceAction`
- **DTO**: PascalCase + suffix opisowy: `PricingRequest`, `EngineResult`
- **Inertia pages** (frontend): `resources/js/Pages/<Domain>/<PageName>.vue`; Domain = `Admin`, `Shop`, `Portal`, `Editor`
- **Komponenty Vue**: `resources/js/Components/<Domain>/<ComponentName>.vue`
- **UI (shadcn-vue)**: `resources/js/Components/ui/<componentName>/...` (lowercase folder)

### 2.8. Konwencje branży (print shop)

Terminologia używana w kodzie i UI (pochodzi ze specyfikacji Web2Print):

| PL (UI) | EN (kod) | Opis |
|---------|----------|------|
| Nakład | `quantity` | Ilość egzemplarzy |
| Użytek | `yield` (pot. `no_up` / `imposition_units`) | Ile egzemplarzy mieści się na arkuszu |
| Arkusz | `sheet` | Fizyczny arkusz papieru |
| Format użytkowy | `usable_format` | Rozmiar arkusza użytkowy do druku (po odjęciu marginesów maszyny) |
| Spad | `bleed` | Margines zewnętrzny (zwyczajowo 3 mm) |
| Separator / margines między użytkami | `gutter` | Przestrzeń między wzorami na arkuszu |
| Odpad technologiczny | `waste` | Dodatkowy narzut na makulaturę |
| Wzorzec | `pattern` | Różnych wersji projektu na nakładzie (np. 4 różne wizytówki) |
| Impozycja | `imposition` | Układanie użytków na arkuszu |
| Marża | `margin` | Narzut cenowy |
| Koszt własny | `cost_price` / `cogs` | Cena przed marżą |
| RKW (Rachunek Kosztów Własnych) | `cogs_breakdown` | Szczegółowy kalkulator kosztu |
| Termin standardowy | `standard_leadtime` | Domyślny termin realizacji |
| Termin przyspieszony | `express_leadtime` | Z mnożnikiem czasowym |

---

## CZĘŚĆ III — MODUŁ 4: Silnik cenowy Web2Print

### 3.1. Przegląd (zgodnie z `plan_implementacji_modul_web2print_v2.md`)

**Cel:** Deterministyczne wyliczenie ceny netto zamówienia na podstawie konfiguracji produktu, nakładu, parametrów, wzorca i terminu.

**Wejście:** `PricingRequest` DTO zawierający:
- `product_id` — identyfikator produktu (→ `products`)
- `quantity` — nakład (int, > 0)
- `format` — wybrany `MediaFormat` (id + wymiary)
- `material` — wybrany `Material` (id + gramatura, typ)
- `parameters` — mapa `parameter_id → parameter_option_id`
- `page_config_id` — wybrany `PageConfig` (dla katalogów)
- `pattern_count` — liczba wzorców (int, default 1)
- `leadtime_multiplier_id?` — opcjonalny mnożnik czasowy
- `client_context?` — ClientContext (B2B/B2C, `client_pricing_override_id?`, lojalność %)
- `extras[]` — dodatkowe koszty (np. projekt graficzny, dostawa)

**Wyjście:** `PricingResult` DTO zawierający:
- `net_price` — cena netto końcowa (PLN)
- `gross_price` — cena brutto (netto × 1.23)
- `vat_amount` — kwota VAT
- `breakdown` — pełna ścieżka kalkulacji (JSON, dla audytu i UI)
- `engine_result` — wynik silnika (ile arkuszy, ile metrów, …)
- `applied_exclusions[]` — reguły wykluczeń które zmieniły wynik
- `applied_overrides` — cennik indywidualny, jeśli użyty
- `applied_loyalty_discount` — rabat lojalnościowy, jeśli

**Kluczowa zasada:** Silnik jest **deterministyczny** — te same wejścia → ten sam wynik. Dlatego wynik jest cachowany w `price_cache` po hash(input) na 5 minut (klient w sklepie może przeliczać wiele razy).

### 3.2. Schemat bazy danych (17 tabel rdzeniowych — spec nienaruszalny)

#### 3.2.1. `global_settings`

```sql
CREATE TABLE global_settings (
    id BIGSERIAL PRIMARY KEY,
    key VARCHAR(100) UNIQUE NOT NULL,
    value JSONB NOT NULL,
    description TEXT,
    updated_at TIMESTAMPTZ
);
```

Klucze używane przez pipeline:
- `margin.threshold_high` — próg przełączenia dwupoziomowej marży (np. 1000 PLN)
- `margin.default_low` — domyślna marża poniżej progu (np. 1.8)
- `margin.default_high` — domyślna marża powyżej progu (np. 1.4)
- `waste.default_sheets` — domyślny odpad w arkuszach (np. 20)
- `vat.rate` — stawka VAT (0.23)
- `pricing.cache_ttl` — TTL cache w sekundach (300)

#### 3.2.2. `media_formats`

```sql
CREATE TABLE media_formats (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,          -- "A4", "DL", "Wizytówka 85x55"
    width_mm DECIMAL(10,2) NOT NULL,
    height_mm DECIMAL(10,2) NOT NULL,
    type VARCHAR(20) NOT NULL,           -- 'product' | 'sheet' | 'roll'
    is_active BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0
);
```

Typy:
- `product` — format gotowego produktu (np. wizytówka 85×55)
- `sheet` — format arkusza użytkowego do impozycji (np. B2 507×720)
- `roll` — rolka materiału (tylko `width_mm`, `height_mm = 0` lub długość rolki)

#### 3.2.3. `materials`

```sql
CREATE TABLE materials (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    type VARCHAR(30) NOT NULL,           -- 'paper' | 'cardboard' | 'vinyl' | 'mesh' | ...
    subtype VARCHAR(50),                 -- 'matte' | 'glossy' | 'offset'
    weight_gsm INT,                      -- gramatura (tylko dla paper)
    thickness_mm DECIMAL(5,3),
    engine_type VARCHAR(20) NOT NULL,    -- 'sheet' | 'linear_meter' | 'piece'
    price_per_unit DECIMAL(10,4),        -- PLN / arkusz | mb | sztuka
    price_unit VARCHAR(10),              -- 'sheet' | 'm' | 'piece'
    sheet_format_id BIGINT REFERENCES media_formats(id),  -- dla sheet
    roll_width_mm DECIMAL(10,2),         -- dla linear_meter
    supplier_id BIGINT,
    is_active BOOLEAN DEFAULT TRUE
);
```

#### 3.2.4. `parameters`

```sql
CREATE TABLE parameters (
    id BIGSERIAL PRIMARY KEY,
    code VARCHAR(50) UNIQUE NOT NULL,    -- 'color', 'lamination', 'corners'
    name VARCHAR(100) NOT NULL,          -- "Kolor", "Laminacja", "Zaokrąglenia"
    type VARCHAR(20) NOT NULL,           -- 'single' | 'multi' | 'numeric'
    sort_order INT DEFAULT 0,
    is_required BOOLEAN DEFAULT FALSE
);
```

#### 3.2.5. `parameter_options`

```sql
CREATE TABLE parameter_options (
    id BIGSERIAL PRIMARY KEY,
    parameter_id BIGINT NOT NULL REFERENCES parameters(id) ON DELETE CASCADE,
    code VARCHAR(50) NOT NULL,
    name VARCHAR(100) NOT NULL,          -- "4+0 CMYK", "4+4 CMYK", "Folia mat"
    -- 8 pól cenowych (4 koszt pre-margin, 4 cena post-margin)
    cost_total DECIMAL(10,4) DEFAULT 0,
    cost_per_sheet DECIMAL(10,4) DEFAULT 0,
    cost_per_meter DECIMAL(10,4) DEFAULT 0,
    cost_per_piece DECIMAL(10,4) DEFAULT 0,
    price_total DECIMAL(10,4) DEFAULT 0,      -- jeśli wypełnione → pomija margin
    price_per_sheet DECIMAL(10,4) DEFAULT 0,
    price_per_meter DECIMAL(10,4) DEFAULT 0,
    price_per_piece DECIMAL(10,4) DEFAULT 0,
    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    UNIQUE(parameter_id, code)
);
```

**Kluczowe:** Cena może być wprowadzona w dwóch trybach:
1. **Koszt (pre-margin)** — podlega marży w kroku 5 pipeline'u
2. **Cena (post-margin)** — narzucona wprost, omija marżę (np. dla dodatków handlowych)

Jeśli którekolwiek z `price_*` > 0, pipeline używa `price_*` i pomija aplikowanie marży na ten składnik.

#### 3.2.6. `page_configs`

```sql
CREATE TABLE page_configs (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,          -- "4 strony", "8 stron", "Okładka+wkład"
    total_pages INT NOT NULL,
    cover_pages INT DEFAULT 0,           -- dla katalogów
    inner_pages INT DEFAULT 0,
    has_separate_cover BOOLEAN DEFAULT FALSE,
    sort_order INT DEFAULT 0
);
```

#### 3.2.7. `products`

```sql
CREATE TABLE products (
    id BIGSERIAL PRIMARY KEY,
    slug VARCHAR(150) UNIQUE NOT NULL,   -- do URL sklepu
    name VARCHAR(200) NOT NULL,
    category_id BIGINT,
    description TEXT,
    description_long TEXT,               -- HTML dla sklepu
    engine_type VARCHAR(20) NOT NULL,    -- 'sheet' | 'linear_meter' | 'piece'
    base_unit VARCHAR(20),               -- 'sztuka' | 'mb' | 'arkusz'
    min_quantity INT DEFAULT 1,
    max_quantity INT,
    is_shop_visible BOOLEAN DEFAULT TRUE,
    is_active BOOLEAN DEFAULT TRUE,
    seo_title VARCHAR(200),
    seo_description TEXT,
    images JSONB,                        -- ["/path/to/img1.jpg", ...]
    sort_order INT DEFAULT 0
);
```

#### 3.2.8. `product_margins`

```sql
CREATE TABLE product_margins (
    id BIGSERIAL PRIMARY KEY,
    product_id BIGINT NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    threshold_pln DECIMAL(10,2) NOT NULL,    -- próg (nadpisuje global_settings)
    margin_low DECIMAL(4,3) NOT NULL,        -- np. 1.800
    margin_high DECIMAL(4,3) NOT NULL,       -- np. 1.400
    updated_at TIMESTAMPTZ
);
```

#### 3.2.9. `product_costs`

```sql
CREATE TABLE product_costs (
    id BIGSERIAL PRIMARY KEY,
    product_id BIGINT NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    code VARCHAR(50) NOT NULL,          -- 'setup', 'kliszowanie', 'fracht'
    name VARCHAR(100) NOT NULL,
    -- 8 pól cenowych jak w parameter_options
    cost_total DECIMAL(10,4) DEFAULT 0,
    cost_per_sheet DECIMAL(10,4) DEFAULT 0,
    cost_per_meter DECIMAL(10,4) DEFAULT 0,
    cost_per_piece DECIMAL(10,4) DEFAULT 0,
    price_total DECIMAL(10,4) DEFAULT 0,
    price_per_sheet DECIMAL(10,4) DEFAULT 0,
    price_per_meter DECIMAL(10,4) DEFAULT 0,
    price_per_piece DECIMAL(10,4) DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE
);
```

#### 3.2.10. `product_formats`

```sql
CREATE TABLE product_formats (
    id BIGSERIAL PRIMARY KEY,
    product_id BIGINT NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    format_id BIGINT NOT NULL REFERENCES media_formats(id),
    is_default BOOLEAN DEFAULT FALSE,
    sort_order INT DEFAULT 0,
    UNIQUE(product_id, format_id)
);
```

#### 3.2.11. `product_materials`

```sql
CREATE TABLE product_materials (
    id BIGSERIAL PRIMARY KEY,
    product_id BIGINT NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    material_id BIGINT NOT NULL REFERENCES materials(id),
    is_default BOOLEAN DEFAULT FALSE,
    sort_order INT DEFAULT 0,
    UNIQUE(product_id, material_id)
);
```

#### 3.2.12. `product_parameters`

```sql
CREATE TABLE product_parameters (
    id BIGSERIAL PRIMARY KEY,
    product_id BIGINT NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    parameter_id BIGINT NOT NULL REFERENCES parameters(id),
    is_required BOOLEAN DEFAULT FALSE,
    sort_order INT DEFAULT 0,
    UNIQUE(product_id, parameter_id)
);
```

#### 3.2.13. `product_parameter_options`

```sql
CREATE TABLE product_parameter_options (
    id BIGSERIAL PRIMARY KEY,
    product_id BIGINT NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    parameter_option_id BIGINT NOT NULL REFERENCES parameter_options(id),
    -- nadpisanie cen dla tej kombinacji produkt × opcja (opcjonalne)
    cost_total_override DECIMAL(10,4),
    cost_per_sheet_override DECIMAL(10,4),
    -- … (wszystkie 8 pól jako override)
    is_default BOOLEAN DEFAULT FALSE,
    sort_order INT DEFAULT 0,
    UNIQUE(product_id, parameter_option_id)
);
```

#### 3.2.14. `product_page_configs`

```sql
CREATE TABLE product_page_configs (
    id BIGSERIAL PRIMARY KEY,
    product_id BIGINT NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    page_config_id BIGINT NOT NULL REFERENCES page_configs(id),
    is_default BOOLEAN DEFAULT FALSE,
    UNIQUE(product_id, page_config_id)
);
```

#### 3.2.15. `exclusion_rules`

```sql
CREATE TABLE exclusion_rules (
    id BIGSERIAL PRIMARY KEY,
    product_id BIGINT REFERENCES products(id) ON DELETE CASCADE,  -- NULL = globalna
    name VARCHAR(200) NOT NULL,
    description TEXT,
    priority INT DEFAULT 0,               -- wyższa = wcześniej aplikowana
    is_active BOOLEAN DEFAULT TRUE
);
```

#### 3.2.16. `exclusion_conditions`

```sql
CREATE TABLE exclusion_conditions (
    id BIGSERIAL PRIMARY KEY,
    rule_id BIGINT NOT NULL REFERENCES exclusion_rules(id) ON DELETE CASCADE,
    field_type VARCHAR(20) NOT NULL,    -- 'material', 'parameter', 'format', 'quantity'
    field_id BIGINT,                    -- ID materiału/parametru/formatu
    operator VARCHAR(5) NOT NULL,       -- '=', '!=', '>', '<', '>=', '<=', '*' (dowolny)
    value_type VARCHAR(20),             -- 'option_id', 'numeric', 'text'
    value TEXT,                         -- wartość porównania
    logic_gate VARCHAR(3) DEFAULT 'AND' -- AND/OR względem poprzedniego warunku
);
```

#### 3.2.17. `exclusion_actions`

```sql
CREATE TABLE exclusion_actions (
    id BIGSERIAL PRIMARY KEY,
    rule_id BIGINT NOT NULL REFERENCES exclusion_rules(id) ON DELETE CASCADE,
    action_type VARCHAR(30) NOT NULL,   -- 'disable_option' | 'set_price' | 'add_cost' | 'block_combination' | 'set_waste'
    target_type VARCHAR(20),            -- 'parameter_option' | 'material' | 'cost' | 'meta'
    target_id BIGINT,
    payload JSONB                       -- { "cost_per_sheet": 0.50, "note": "lakier UV +50gr/ark" }
);
```

### 3.3. Trzy silniki (Engines)

#### 3.3.1. `SheetEngine` (arkuszowy)

**Kiedy:** Produkty drukowane z arkusza (wizytówki, ulotki, plakaty, katalogi).

**Wejście:**
- `quantity` — nakład
- `product_format` — format gotowego produktu (np. 85×55 mm)
- `material.sheet_format` — format arkusza (np. B2 507×720 mm)
- `pattern_count` — wzorce (1 = identyczne, 2+ = każdy wzór osobno na arkuszu)
- `page_config` — stron (dla katalogów, np. 8 stron → 2 arkusze obustronnie)
- `waste_sheets` — odpad technologiczny

**Algorytm (`ImpositionCalculator::calculate()`):**

1. Oblicz impozycję: ile użytków mieści się na arkuszu
   ```
   yield_x = floor((sheet_usable_width - 2 × bleed) / (product_width + gutter))
   yield_y = floor((sheet_usable_height - 2 × bleed) / (product_height + gutter))
   yield_per_sheet = yield_x × yield_y
   ```
2. Oblicz liczbę arkuszy na 1 wzorzec:
   ```
   sheets_per_pattern = ceil(quantity / yield_per_sheet)
   ```
3. Pomnóż przez pattern_count:
   ```
   sheets_before_waste = sheets_per_pattern × pattern_count × pages_multiplier
   ```
4. Dodaj odpad:
   ```
   total_sheets = sheets_before_waste + waste_sheets
   ```

**Wynik EngineResult:**
```php
readonly class EngineResult {
    public int $sheets;           // total_sheets
    public int $yieldPerSheet;    // yield_per_sheet
    public int $sheetsPerPattern;
    public float $meters = 0;      // N/A dla sheet
    public int $pieces = 0;        // N/A
    public array $debug;          // { yield_x, yield_y, waste, pages, ... }
}
```

#### 3.3.2. `LinearMeterEngine` (metrowy)

**Kiedy:** Roll-upy, banery, naklejki rolkowe, tapety.

**Wejście:**
- `quantity` — ilość sztuk
- `product_format` — format produktu (np. 85×200 cm)
- `material.roll_width_mm` — szerokość rolki (np. 1067 mm)
- `gutter_mm` — odstęp między wydrukami

**Algorytm (`RollSelectorService::calculate()`):**

1. Ustal orientację: produkt na rolce najczęściej wysokością wzdłuż (baner 200 cm w pionie):
   ```
   if product_width <= roll_width: orientation = 'portrait'
   else: orientation = 'landscape' (obrócony)
   ```
2. Oblicz ile produktów mieści się w szerokość rolki:
   ```
   products_per_width = floor((roll_width - 2 × edge_margin) / (product_width + gutter))
   ```
   (zwykle 1 dla roll-upów; >1 dla mniejszych naklejek)
3. Oblicz długość materiału dla jednego rzędu:
   ```
   meters_per_row = (product_height + gutter) / 1000
   ```
4. Oblicz łączne metry:
   ```
   total_meters = ceil(quantity / products_per_width) × meters_per_row × pattern_count
   total_meters += waste_meters
   ```

**Wynik:**
```php
public float $meters;
public int $productsPerWidth;
public int $pieces;  // = quantity
public int $sheets = 0;  // N/A
```

#### 3.3.3. `PieceEngine` (sztukowy)

**Kiedy:** Opakowania indywidualne, kartoniki na wymiar, gadżety (gdzie cena jest za sztukę bez impozycji).

**Wejście:**
- `quantity` — ilość sztuk

**Algorytm:**
- Nie liczy arkuszy ani metrów
- `pieces = quantity + waste_pieces`

**Wynik:**
```php
public int $pieces;
public int $sheets = 0;
public float $meters = 0;
```

#### 3.3.4. `EngineResolver`

```php
namespace App\Domain\Pricing\Engines;

class EngineResolver {
    public function resolve(string $engineType): PricingEngine {
        return match($engineType) {
            'sheet' => app(SheetEngine::class),
            'linear_meter' => app(LinearMeterEngine::class),
            'piece' => app(PieceEngine::class),
            default => throw new InvalidEngineException($engineType),
        };
    }
}
```

### 3.4. PricingPipeline — 11 kroków

```php
namespace App\Domain\Pricing\Pipeline;

class PricingPipeline {
    public function execute(PricingRequest $request): PricingResult {
        $ctx = new PricingContext($request);

        // Krok 1: Uruchom silnik (sheet/linear_meter/piece)
        $ctx->engineResult = $this->resolver->resolve($request->engineType)
            ->calculate($ctx);

        // Krok 2: Extras costs pre-margin (wszystkie 4 pola cost_*)
        $ctx->extrasCostPreMargin = $this->sumCosts(
            $request->parameterOptions,
            keys: ['cost_total', 'cost_per_sheet', 'cost_per_meter', 'cost_per_piece'],
            engineResult: $ctx->engineResult,
        );

        // Krok 3: Product costs pre-margin (product_costs)
        $ctx->productCostPreMargin = $this->sumCosts(
            $request->productCosts,
            keys: ['cost_total', 'cost_per_sheet', 'cost_per_meter', 'cost_per_piece'],
            engineResult: $ctx->engineResult,
        );

        // Krok 4: Sumaryczny koszt pre-margin
        $ctx->totalCostPreMargin = $ctx->extrasCostPreMargin + $ctx->productCostPreMargin;

        // Krok 5: Aplikuj marżę (dwupoziomową)
        $marginCalc = new MarginCalculator();
        $ctx->appliedMargin = $marginCalc->resolve(
            product: $request->product,
            amount: $ctx->totalCostPreMargin,
            clientOverride: $request->clientContext?->marginOverride,
        );
        $ctx->basePriceAfterMargin = $ctx->totalCostPreMargin * $ctx->appliedMargin;

        // Krok 6: Mnożnik wzorca (pattern multiplier)
        //   np. 2 wzorce = ×1.1, 4 wzorce = ×1.2 (zdefiniowany per produkt lub globalnie)
        $ctx->patternMultiplier = $this->patternMultiplier($request->patternCount);
        $ctx->priceAfterPattern = $ctx->basePriceAfterMargin * $ctx->patternMultiplier;

        // Krok 7: Extras prices post-margin (price_* pola, omijają marżę)
        $ctx->extrasPricePostMargin = $this->sumCosts(
            $request->parameterOptions,
            keys: ['price_total', 'price_per_sheet', 'price_per_meter', 'price_per_piece'],
            engineResult: $ctx->engineResult,
        );

        // Krok 8: Product prices post-margin
        $ctx->productPricePostMargin = $this->sumCosts(
            $request->productCosts,
            keys: ['price_total', 'price_per_sheet', 'price_per_meter', 'price_per_piece'],
            engineResult: $ctx->engineResult,
        );

        // Krok 9: Suma tymczasowa
        $ctx->priceBeforeTimeMultiplier =
            $ctx->priceAfterPattern
            + $ctx->extrasPricePostMargin
            + $ctx->productPricePostMargin;

        // Krok 10: Mnożnik czasu (leadtime)
        $ctx->timeMultiplier = $request->leadtimeMultiplier?->factor ?? 1.0;
        $ctx->priceAfterTime = $ctx->priceBeforeTimeMultiplier * $ctx->timeMultiplier;

        // Krok 11: Finalna cena netto (przed overlay'ami)
        $ctx->finalNetPrice = round($ctx->priceAfterTime, 2);

        // === OVERLAY'e (poza oryginalnym pipeline'em) ===
        // Overlay 1: Loyalty (post-pipeline, nie wpływa na pozycje faktury)
        if ($request->clientContext?->loyaltyDiscount) {
            $ctx->loyaltyDiscount = $ctx->finalNetPrice * $request->clientContext->loyaltyDiscount;
            $ctx->finalNetPrice -= $ctx->loyaltyDiscount;
        }

        return PricingResult::fromContext($ctx);
    }
}
```

### 3.5. ExclusionResolver

Reguły wykluczeń aplikowane są **PRZED** pipeline'em — mogą:
- Zablokować kombinację (`block_combination`) → rzuć `InvalidConfigurationException`
- Wyłączyć opcję (`disable_option`) → w UI wyszarzyć opcję
- Zmodyfikować cenę składnika (`set_price`, `add_cost`) → podmieć wartość w DTO
- Zmienić odpad (`set_waste`) → nadpisz `$request->wasteSheets`

```php
class ExclusionResolver {
    public function resolve(PricingRequest $request): PricingRequest {
        $rules = ExclusionRule::where('product_id', $request->productId)
            ->orWhereNull('product_id')
            ->where('is_active', true)
            ->orderByDesc('priority')
            ->with(['conditions', 'actions'])
            ->get();

        foreach ($rules as $rule) {
            if ($this->evaluateConditions($rule->conditions, $request)) {
                $request = $this->applyActions($rule->actions, $request);
            }
        }
        return $request;
    }

    private function evaluateConditions(Collection $conditions, PricingRequest $request): bool {
        $result = null;
        foreach ($conditions as $cond) {
            $match = $this->matchCondition($cond, $request);
            $result = $result === null
                ? $match
                : ($cond->logic_gate === 'AND' ? $result && $match : $result || $match);
        }
        return (bool) $result;
    }
}
```

### 3.6. Architektura folderów silnika

```
app/Domain/Pricing/
├── DTOs/
│   ├── PricingRequest.php         # readonly, konstruktor z walidacją
│   ├── PricingResult.php          # readonly
│   ├── PricingContext.php         # mutable, używane tylko w pipeline
│   ├── EngineResult.php           # readonly
│   ├── ClientContext.php          # readonly
│   └── PricingBreakdownEntry.php  # dla audit trailu
├── Engines/
│   ├── PricingEngine.php          # interface
│   ├── SheetEngine.php
│   ├── LinearMeterEngine.php
│   ├── PieceEngine.php
│   └── EngineResolver.php
├── Pipeline/
│   ├── PricingPipeline.php
│   └── Steps/                     # opcjonalnie każdy krok jako osobna klasa
│       ├── RunEngineStep.php
│       ├── SumExtrasCostStep.php
│       ├── SumProductCostStep.php
│       ├── ApplyMarginStep.php
│       ├── ApplyPatternMultiplierStep.php
│       ├── SumExtrasPricesStep.php
│       ├── SumProductPricesStep.php
│       ├── ApplyTimeMultiplierStep.php
│       └── ApplyLoyaltyOverlayStep.php
├── Services/
│   ├── MarginCalculator.php
│   ├── ImpositionCalculator.php
│   ├── WasteCalculator.php
│   ├── RollSelectorService.php
│   └── PatternMultiplierService.php
├── Resolvers/
│   ├── ExclusionResolver.php
│   ├── LoyaltyResolver.php
│   └── ClientPricingOverrideResolver.php
├── Cache/
│   └── PriceCache.php             # hash(input) → result, TTL 300 s
└── Exceptions/
    ├── InvalidEngineException.php
    ├── InvalidConfigurationException.php
    └── PricingCalculationException.php
```

### 3.7. UI administracyjne — Inertia + Vue 3 + shadcn-vue

Specyfikacja Web2Print opisuje Filament/Livewire, ale ten projekt używa Inertia + Vue. Musimy **sportować** wszystkie panele:

#### 3.7.1. Struktura stron admin

```
resources/js/Pages/Admin/Pricing/
├── Products/
│   ├── Index.vue              # Lista produktów (DataTable)
│   ├── Edit.vue               # Edycja produktu (tabs: Basic, Formats, Materials, Parameters, Costs, Margins, Exclusions)
│   └── Partials/
│       ├── TabBasic.vue
│       ├── TabFormats.vue     # pivot product_formats
│       ├── TabMaterials.vue
│       ├── TabParameters.vue  # nested: parameters → options → overrides
│       ├── TabCosts.vue       # product_costs CRUD inline
│       ├── TabMargins.vue
│       ├── TabPageConfigs.vue
│       └── TabExclusions.vue  # reguły IF-THEN
├── Materials/
│   ├── Index.vue
│   └── Edit.vue
├── Parameters/
│   ├── Index.vue
│   └── Edit.vue
├── MediaFormats/
│   ├── Index.vue
│   └── Edit.vue
├── PageConfigs/
│   ├── Index.vue
│   └── Edit.vue
├── GlobalSettings.vue
└── PriceTester.vue            # sandbox: wybierz produkt, parametry, kliknij "Oblicz" → pełny breakdown
```

#### 3.7.2. Użyte komponenty shadcn-vue

- `DataTable` (ze sortowaniem, filtrowaniem, paginacją) — dla Index stron
- `Dialog` + `Sheet` — dla edycji szybkiej (np. add material)
- `Form`, `FormField`, `FormItem`, `FormLabel`, `FormControl`, `FormMessage` — wszystkie formularze
- `Tabs`, `TabsList`, `TabsTrigger`, `TabsContent` — edycja produktu (8 zakładek)
- `Accordion` — zagnieżdżone parametry / opcje
- `Command` + `Combobox` — wybór materiału/formatu z dużej listy
- `Select`, `Switch`, `Checkbox`, `RadioGroup` — pola formularzy
- `Card`, `CardHeader`, `CardContent`, `CardFooter` — grupy pól
- `Alert`, `AlertDialog` — ostrzeżenia (np. „Zmiana marży wpłynie na …")
- `Button`, `DropdownMenu` — akcje
- `Badge` — statusy, tagi
- `Table`, `TableRow`, `TableCell` — tabele inline (np. opcje parametru)
- `Input`, `Textarea`, `NumberField` — pola
- `Toaster` (Sonner) — powiadomienia sukcesu/błędu

#### 3.7.3. PriceTester — sandbox operatora

Strona `/admin/pricing/tester` — narzędzie do debugowania konfiguracji cennika:

1. Wybierz produkt (Combobox)
2. Dynamicznie renderuj pola konfiguracji:
   - Format (RadioGroup)
   - Materiał (Select)
   - Parametry (osobne pole per parametr)
   - Nakład (NumberField)
   - Wzorce (NumberField, default 1)
   - Termin (Select z mnożnikami czasu)
   - Klient (Combobox — testuj cennik indywidualny)
3. „Oblicz" → useHttp POST `/admin/pricing/calculate` → wyświetl `PricingResult` w formie:
   - Główna cena netto (duży heading)
   - Expandable sekcje: Engine result, Koszty pre-margin, Ceny post-margin, Marża, Mnożniki, Overlays
   - Tabela kroków pipeline'u (11 kroków + overlays)
   - JSON breakdown (expandable raw, do kopiowania)

### 3.8. API dla pipeline'u

#### 3.8.1. Endpoint operatorski (Inertia useHttp)

```
POST /admin/pricing/calculate
Body: {
  product_id: 42,
  quantity: 1000,
  format_id: 7,
  material_id: 15,
  parameters: { "2": 23, "5": 48 },   // parameter_id → option_id
  page_config_id: null,
  pattern_count: 1,
  leadtime_multiplier_id: 3,
  client_id: 128,                      // opcjonalnie
}
→ 200 { net_price, gross_price, vat, breakdown: {...}, applied_exclusions: [...] }
```

#### 3.8.2. Endpoint publiczny (sklep, konfigurator)

```
POST /api/shop/price
Body: to samo, ale bez client_id (session-based)
Rate limit: 20 req / min / IP
→ 200 { net, gross, currency: 'PLN' }  // uproszczony, bez audit trailu
```

### 3.9. Integracja z Order Management

Przy tworzeniu zamówienia pipeline uruchamiany jest **raz** i jego pełny wynik zapisywany w `orders.price_breakdown JSONB`. Nie jest przeliczany przy kolejnych renderach zamówienia — cena jest **frozen** w momencie akceptacji.

```sql
ALTER TABLE orders ADD COLUMN IF NOT EXISTS
    price_breakdown JSONB,              -- pełny breakdown z PricingResult
    engine_type VARCHAR(20),
    sheets_used INT,
    meters_used DECIMAL(10,3),
    pieces_count INT,
    waste_sheets INT,
    applied_margin DECIMAL(4,3),
    pattern_count INT DEFAULT 1,
    leadtime_multiplier DECIMAL(4,3) DEFAULT 1,
    loyalty_discount_percent DECIMAL(4,3) DEFAULT 0;
```

Recalculation (edycja zamówienia przed akceptacją) → wywołaj `CalculatePriceAction::recalculate(Order $order)` który nadpisuje `price_breakdown`.

### 3.10. RKW Overlay — Koszt Własny (shadow calculator)

Równolegle do pipeline'u cenowego uruchamiany jest `CostOfGoodsCalculator` który liczy **rzeczywisty koszt** zlecenia (nie wpływa na cenę klienta):

**Komponenty kosztu:**
1. **Materiał rzeczywisty:** `total_sheets × material.cost_per_sheet` (cost zakupu, nie cena sprzedaży)
2. **Maszyna:** `machine.hourly_rate × estimated_run_time_hours`
3. **Praca:** `operator.hourly_rate × estimated_man_hours`
4. **Amortyzacja:** % od czasu maszyny (konfigurowane per maszyna)
5. **Energia i media:** stawka godzinowa × czas maszyny
6. **Koszty ogólne (overhead):** % od sumy powyżej (np. 15 %)

**Tabela:**
```sql
CREATE TABLE order_cogs_breakdown (
    order_id BIGINT PRIMARY KEY REFERENCES orders(id) ON DELETE CASCADE,
    material_cost DECIMAL(10,2),
    machine_cost DECIMAL(10,2),
    labor_cost DECIMAL(10,2),
    depreciation_cost DECIMAL(10,2),
    energy_cost DECIMAL(10,2),
    overhead_cost DECIMAL(10,2),
    total_cost DECIMAL(10,2) GENERATED ALWAYS AS (
        material_cost + machine_cost + labor_cost
        + depreciation_cost + energy_cost + overhead_cost
    ) STORED,
    actual_margin_pln DECIMAL(10,2),    -- cena_sprzedaży - total_cost
    actual_margin_percent DECIMAL(5,2), -- (cena - total) / cena × 100
    calculated_at TIMESTAMPTZ,
    recalculated_after_production BOOLEAN DEFAULT FALSE  -- po zakończeniu produkcji uruchom ponownie z realnymi danymi
);
```

Raport „Marża rzeczywista vs. oczekiwana" w module Reports (14) — BI insight dla właściciela.

### 3.11. Loyalty Overlay — Program lojalnościowy

**Mechanika:**
- Każdy zalogowany klient ma `loyalty_tier` (`bronze`, `silver`, `gold`, `platinum`)
- Tier wyliczany jest z sumy zamówień w ostatnich 12 miesiącach
- Każdy tier daje `%` rabatu: 0 / 2 / 5 / 8

**Tabela:**
```sql
CREATE TABLE loyalty_tiers (
    code VARCHAR(20) PRIMARY KEY,        -- 'bronze', 'silver', 'gold', 'platinum'
    name VARCHAR(50),
    min_annual_revenue DECIMAL(10,2),    -- próg suma netto 12 mc
    discount_percent DECIMAL(4,3),       -- 0.00, 0.02, 0.05, 0.08
    color VARCHAR(20),                   -- dla UI
    sort_order INT
);

ALTER TABLE clients ADD COLUMN
    current_loyalty_tier VARCHAR(20) REFERENCES loyalty_tiers(code) DEFAULT 'bronze',
    loyalty_annual_revenue DECIMAL(10,2) DEFAULT 0,
    loyalty_recalculated_at TIMESTAMPTZ;
```

**Job:** `RecalculateLoyaltyTierJob` uruchamiany co noc dla klientów z aktywnością w ostatnich 13 miesiącach.

**W UI sklepu/portalu:** Badge tier-u, progress bar do następnego tier-u, komunikat „brakuje Ci X PLN do srebra".

### 3.12. Cennik indywidualny (Client Pricing Override)

Dla klientów B2B z kontraktem (np. agencje z rabatem „zawsze 10 %") tworzymy `client_pricing_overrides`:

```sql
CREATE TABLE client_pricing_overrides (
    id BIGSERIAL PRIMARY KEY,
    client_id BIGINT NOT NULL REFERENCES clients(id) ON DELETE CASCADE,
    product_id BIGINT REFERENCES products(id),       -- NULL = wszystkie
    parameter_option_id BIGINT REFERENCES parameter_options(id),  -- NULL = wszystkie
    margin_override DECIMAL(4,3),                    -- zastąp margin
    discount_percent DECIMAL(4,3),                   -- % rabatu
    fixed_price DECIMAL(10,2),                       -- stała cena (rzadko)
    valid_from DATE,
    valid_until DATE,
    priority INT DEFAULT 0,
    notes TEXT,
    created_by BIGINT REFERENCES users(id)
);
```

`ClientPricingOverrideResolver` uruchamiany **w kroku 5** pipeline'u — podmienia `margin` lub aplikuje % rabatu od razu po marży.

---

## CZĘŚĆ IV — MODUŁ 17: Sklep internetowy

### 4.1. Cele sklepu

1. **Samoobsługa cenowa** — klient sam konfiguruje produkt i widzi cenę w czasie rzeczywistym (<500 ms na przeliczenie)
2. **Niski próg wejścia** — guest checkout (email + telefon + dane do wysyłki/faktury wystarczą)
3. **Konwersja do konta** — zachęta do rejestracji przed płatnością (rabat 2 % na pierwsze zamówienie) lub magic link po płatności
4. **SEO** — strony produktowe indeksowane (SSR przez Inertia, meta, rich snippets JSON-LD)
5. **Jedno źródło cen** — używamy tego samego pipeline'u co panel operatora
6. **Integracja z Design Editor** — „Zaprojektuj online" jako opcja w konfiguratorze

### 4.2. Mapa stron sklepu (Inertia)

```
/                                         HomePage (hero + top produkty + opinie)
/kategoria/{slug}                         CategoryPage (grid produktów, filtry)
/produkt/{slug}                           ProductPage (konfigurator + kalkulator cen)
/szukaj?q=...                             SearchPage (Meilisearch)
/koszyk                                   CartPage
/zamowienie                               CheckoutPage (multi-step: dostawa → płatność → podsumowanie)
/zamowienie/potwierdzenie/{orderNumber}   OrderConfirmationPage
/zaloguj                                  LoginPage
/zarejestruj                              RegisterPage
/przypomnij-haslo                         PasswordResetPage
/magic-link/{token}                       MagicLinkPage (dla guest→user conversion)
/kontakt                                  ContactPage
/o-nas                                    AboutPage
/regulamin                                TermsPage (legal)
/polityka-prywatnosci                     PrivacyPage
/blog                                     BlogIndex
/blog/{slug}                              BlogPost

# Portal klienta (pod /moje-konto, dostępne po zalogowaniu)
/moje-konto                               DashboardPage (skrót: ostatnie zamówienia, faktury)
/moje-konto/zamowienia                    OrdersPage
/moje-konto/zamowienia/{orderNumber}      OrderDetailPage
/moje-konto/akceptacje                    ApprovalsPage (pliki do zatwierdzenia)
/moje-konto/akceptacje/{designId}         ApprovalDetailPage (PDF viewer + accept/reject)
/moje-konto/pliki                         FilesPage
/moje-konto/dokumenty                     DocumentsPage (faktury, proformy do pobrania)
/moje-konto/reklamacje                    ComplaintsPage
/moje-konto/reklamacje/{complaintId}      ComplaintDetailPage
/moje-konto/projekty                      DesignsPage (z edytora)
/moje-konto/projekty/{designId}           DesignDetailPage (podgląd + klonuj + edytuj)
/moje-konto/powtorz/{orderNumber}         RepeatOrderPage
/moje-konto/wiadomosci                    MessagesPage (in-app chat z operatorem)
/moje-konto/adresy                        AddressesPage
/moje-konto/ustawienia                    SettingsPage (email, hasło, 2FA, preferencje)
```

### 4.3. Home page

#### 4.3.1. Sekcje

1. **Hero** — baner z CTA „Wyceń online w 30 s", ilustracja, 3 kafelki najczęstszych produktów (wizytówki, ulotki, roll-upy)
2. **Kategorie** — grid 6–8 kafelków kategorii (duże ikony, nazwa, opis)
3. **Produkty polecane** — 4–8 bestsellerów (manager może oznaczać w admin)
4. **Nasz proces** — 4 kroki: 1) wyceń online, 2) dodaj projekt (lub zaprojektuj online), 3) akceptuj proof, 4) odbierz
5. **Opinie klientów** — carousel z opiniami (Trustpilot API lub własne)
6. **CTA Design Editor** — „Nie masz projektu? Zaprojektuj sam online za darmo" → `/edytor`
7. **FAQ** — 5–8 najczęstszych pytań (Accordion)
8. **Zaufali nam** — logotypy klientów B2B
9. **Stopka** — kategorie, o nas, kontakt, regulamin, social media, cookie banner

#### 4.3.2. Komponenty Vue

```
resources/js/Pages/Shop/Home.vue
├── uses <ShopLayout />
├── uses <Hero />
├── uses <CategoryGrid />
├── uses <FeaturedProducts />
├── uses <ProcessSteps />
├── uses <TestimonialsCarousel />
├── uses <DesignEditorCTA />
├── uses <FAQAccordion />
├── uses <BrandLogos />
```

### 4.4. Katalog (Category Page)

#### 4.4.1. Funkcjonalność

- Grid produktów (Card + obraz + nazwa + „od X PLN")
- Filtry (lewa kolumna, collapsible na mobile):
  - Typ produktu (z `product.category_id`)
  - Format / rozmiar
  - Materiał (paper, cardboard, vinyl)
  - Wykończenie (kolorystyka, laminacja)
  - Cena (slider min–max)
- Sortowanie: popularność, cena ↑↓, nazwa, termin realizacji
- Paginacja (infinite scroll + wariant „pokaż więcej")
- Ilość filtrów jako Badge przy przycisku „Filtry" na mobile

#### 4.4.2. Tech

- `Inertia::render('Shop/Category', ['products' => Inertia::defer(fn() => …)])` — lazy loading produktów
- Filtry przez URL query params, `router.reload({ preserveScroll: true, only: ['products'] })` przy zmianie filtra
- Meilisearch dla filtrów facetowych i search-as-you-type

### 4.5. Product Page + Konfigurator

#### 4.5.1. Layout (1 strona, 2 kolumny)

```
┌──────────────────────────────────┬─────────────────────────────────┐
│                                  │                                 │
│    [galeria zdjęć]               │   Wizytówki standardowe         │
│                                  │   ─────────────────             │
│    [image 1] [image 2] [image 3] │   od 39 PLN za 100 szt.         │
│                                  │                                 │
│                                  │   ┌─ KONFIGURATOR ─────────┐    │
│                                  │   │ Format:                │    │
│                                  │   │ ( ) 85 × 55 mm         │    │
│                                  │   │ ( ) 90 × 50 mm         │    │
│                                  │   │                        │    │
│                                  │   │ Materiał:              │    │
│                                  │   │ [Combobox: 350 g mat]  │    │
│                                  │   │                        │    │
│                                  │   │ Kolorystyka:           │    │
│                                  │   │ ( ) 4+0 CMYK (jedna)   │    │
│                                  │   │ ( ) 4+4 CMYK (dwie)    │    │
│                                  │   │                        │    │
│                                  │   │ Uszlachetnienia:       │    │
│                                  │   │ [ ] Folia mat          │    │
│                                  │   │ [ ] Lakier UV wybiórczy│    │
│                                  │   │                        │    │
│                                  │   │ Nakład: [ 500 ▾ ]      │    │
│                                  │   │                        │    │
│                                  │   │ Termin:                │    │
│                                  │   │ ( ) Standard (3-5 dni) │    │
│                                  │   │ ( ) Przyspieszony +30% │    │
│                                  │   │                        │    │
│                                  │   │ Wzorce: [ 1 ▾ ]        │    │
│                                  │   └────────────────────────┘    │
│                                  │                                 │
│                                  │   ┌─ CENA ──────────────────┐   │
│                                  │   │  Netto:  146.42 PLN     │   │
│                                  │   │  Brutto: 180.10 PLN     │   │
│                                  │   │  [Dodaj do koszyka]     │   │
│                                  │   │  [Zaprojektuj online]   │   │
│                                  │   │  [Wgraj własny PDF]     │   │
│                                  │   └─────────────────────────┘   │
└──────────────────────────────────┴─────────────────────────────────┘

   [Zakładki: Opis | Specyfikacja | Jak przygotować plik | Pytania]
```

#### 4.5.2. Konfigurator — stan reaktywny

```vue
<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useHttp } from '@inertiajs/vue3'
import { debounce } from 'lodash-es'

const props = defineProps<{
  product: Product
  formats: Format[]
  materials: Material[]
  parameters: Parameter[]
  pageConfigs: PageConfig[]
  leadtimeMultipliers: LeadtimeMultiplier[]
  initialConfig?: Record<string, any>   // dla „zamów ponownie"
}>()

const config = ref({
  format_id: props.initialConfig?.format_id ?? defaultFormat.id,
  material_id: props.initialConfig?.material_id ?? defaultMaterial.id,
  parameters: props.initialConfig?.parameters ?? {},
  quantity: props.initialConfig?.quantity ?? 100,
  pattern_count: 1,
  page_config_id: null,
  leadtime_multiplier_id: null,
})

const price = ref<PricingResult | null>(null)
const loading = ref(false)
const { post } = useHttp()

// Reaktywne przeliczanie z debounce 300 ms
const recalculate = debounce(async () => {
  loading.value = true
  try {
    const result = await post('/api/shop/price', {
      product_id: props.product.id,
      ...config.value,
    })
    price.value = result.data
  } catch (err) {
    // pokaz Alert z shadcn
    toast({ title: 'Błąd kalkulacji', description: err.message, variant: 'destructive' })
  } finally {
    loading.value = false
  }
}, 300)

watch(config, recalculate, { deep: true, immediate: true })

// Ukryte opcje via ExclusionResolver (server-side pre-check)
const availableOptions = computed(() => /* …*/)
</script>
```

#### 4.5.3. Komponenty konfiguratora

- `<ConfiguratorFormatPicker />` — RadioGroup (shadcn)
- `<ConfiguratorMaterialSelect />` — Combobox z search (np. „350 g mat", „250 g kreda")
- `<ConfiguratorParameters />` — dynamic rendering: pętla po `parameters[]`, każdy renderuje odpowiedni komponent (`single` → RadioGroup, `multi` → Checkbox group, `numeric` → NumberField)
- `<ConfiguratorQuantity />` — NumberField + sugerowane ilości (100, 250, 500, 1000, 2500) jako Chip buttons
- `<ConfiguratorLeadtime />` — Select z mnożnikiem czasu
- `<ConfiguratorPattern />` — NumberField (ukryte dla produktów bez wzorców)
- `<PriceDisplay />` — netto + brutto, animacja przy zmianie
- `<ActionButtons />` — „Dodaj do koszyka" / „Zaprojektuj online" / „Wgraj PDF"

### 4.6. Koszyk (Cart)

#### 4.6.1. Struktura koszyka

Koszyk to session-based (guest) lub DB-based (logged):
- Guest: `session('cart', [...])` — array itemów
- Logged: tabela `carts` + `cart_items`

```sql
CREATE TABLE carts (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT REFERENCES users(id) ON DELETE CASCADE,
    session_id VARCHAR(100),              -- fallback dla guest przed logowaniem
    created_at TIMESTAMPTZ,
    updated_at TIMESTAMPTZ,
    abandoned_at TIMESTAMPTZ              -- dla kampanii email „zostawiłeś koszyk"
);

CREATE TABLE cart_items (
    id BIGSERIAL PRIMARY KEY,
    cart_id BIGINT NOT NULL REFERENCES carts(id) ON DELETE CASCADE,
    product_id BIGINT NOT NULL REFERENCES products(id),
    quantity INT NOT NULL,
    config JSONB NOT NULL,                -- pełna konfiguracja z PricingRequest
    price_breakdown JSONB,                -- frozen z momentu dodania
    net_price DECIMAL(10,2),
    gross_price DECIMAL(10,2),
    design_id BIGINT REFERENCES designs(id),  -- NULL lub projekt z edytora / upload PDF
    design_status VARCHAR(20),            -- 'pending_upload' | 'pending_design' | 'ready'
    note TEXT,
    created_at TIMESTAMPTZ,
    updated_at TIMESTAMPTZ
);
```

#### 4.6.2. Funkcje

- Dodaj do koszyka (z konfiguracją + projektem)
- Edytuj item (otwiera konfigurator z pre-filled values)
- Usuń item
- Kod rabatowy (`promo_codes` — osobna tabela)
- Liczba szt., cena jednostkowa, suma
- Przelicz koszyk przy zmianie (wywołanie pipeline'u dla każdego itemu, wynik cachowany)

### 4.7. Checkout

#### 4.7.1. Multi-step

**Krok 1: Dane kontaktowe**
- Dla guest: email, telefon
- Dla logged: wybór zapisanych adresów z profilu

**Krok 2: Dostawa**
- Adres dostawy (ulica, miasto, kod pocztowy, kraj)
- Wybór metody dostawy (z danych z modułu 11 Logistics):
  - Paczkomat InPost
  - Kurier InPost
  - Kurier DPD
  - Kurier DHL
  - Odbiór osobisty
- Cena dostawy liczona przez `ShippingRateCalculator` (waga szacowana + gabaryty z produktu)

**Krok 3: Faktura**
- Odbiorca taki sam jak dostawa? (Switch)
- Dla B2B: pole NIP + przycisk „Pobierz z GUS" → `GusClient::lookup()` → auto-fill nazwy, adresu
- Checkbox „Faktura VAT" (default: Paragon)
- MPP dla B2B > 15 000 PLN (auto-checked, readonly alert)

**Krok 4: Płatność**
- Przelewy24 (karta, BLIK, przelew online) — default
- PayU
- Tradycyjny przelew (pro-forma + 3 dni na opłacenie)
- Za pobraniem (tylko dla niektórych kategorii)

**Krok 5: Podsumowanie + akceptacja regulaminu**
- Podsumowanie koszyka z cenami
- Checkbox „Akceptuję regulamin"
- Checkbox „Zapisz moje dane, załóż konto" (dla guest) — magic link po płatności
- Checkbox „Newsletter"
- Przycisk „Zapłać i zamów"

#### 4.7.2. Flow techniczny

```
POST /api/shop/checkout/submit
  → Validate cart + config (re-run pipeline, compare prices — detect drift)
  → Create Order (status NEW) via CreateOrderFromCartAction
  → Transition to PRICING (auto) → Create price_breakdown (copy from cart items)
  → Redirect → payment gateway (P24/PayU) with order_number
    |
    ↓ (webhook z P24)
POST /webhooks/p24/confirm
  → Verify signature, update payment status
  → Fire PaymentReceived event
  → Order transitions to WAITING_FILES (if no design) OR APPROVED (if design ready)
  → Generate proforma PDF via mPDF + send email
  → Fire OrderConfirmed event → CommHub sends SMS + email
  → Redirect user to /zamowienie/potwierdzenie/{orderNumber}
```

### 4.8. Guest → User conversion

**Scenariusz:**
1. Guest dokonał zakupu jako `user.is_guest = true`
2. Po webhooku płatności wysyłamy email „Dokończ rejestrację"
3. Mail zawiera `magic_link`: `https://drukarnia.pl/magic-link/{token}` z tokenem `Str::random(64)` zapisanym w `magic_links` (ttl 7 dni)
4. Kliknięcie → automatic login + pytanie „Ustaw hasło" + potwierdzenie emaila
5. `users.is_guest` ustawiany na `false`, zamówienie już powiązane

```sql
CREATE TABLE magic_links (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token VARCHAR(128) UNIQUE NOT NULL,
    expires_at TIMESTAMPTZ NOT NULL,
    used_at TIMESTAMPTZ,
    purpose VARCHAR(30),                 -- 'guest_activation' | 'password_reset' | 'email_verify'
    ip_address VARCHAR(45),
    created_at TIMESTAMPTZ
);
```

### 4.9. SEO i wydajność

- **SSR**: Vite + `@inertiajs/vite` SSR plugin → pre-rendering stron produktowych dla Googlebot
- **Meta tags**: `<Head>` w Inertia, per strona
- **Structured data**: JSON-LD `Product` schema z ceną minimum, `AggregateRating`, `BreadcrumbList`
- **Sitemap.xml**: `spatie/laravel-sitemap`
- **Robots.txt**: dozwolone wszystkie strony publiczne, blokujemy `/moje-konto`, `/zamowienie`, `/koszyk`
- **Core Web Vitals**: LCP < 2.5 s, CLS < 0.1, INP < 200 ms (monitorowane przez Sentry Performance lub Pulse)
- **Images**: lazy-load, AVIF/WebP z fallback, `sizes`, CDN (MinIO + Cloudflare)
- **Cache**: strony kategoryjne i produktowe cachowane 5 min (Laravel Cache `product:{slug}:{priceHash}`)

### 4.10. Promo codes

```sql
CREATE TABLE promo_codes (
    id BIGSERIAL PRIMARY KEY,
    code VARCHAR(50) UNIQUE NOT NULL,
    type VARCHAR(20) NOT NULL,              -- 'percent' | 'fixed' | 'free_shipping'
    value DECIMAL(10,2),
    min_cart_value DECIMAL(10,2),
    max_uses INT,
    max_uses_per_user INT DEFAULT 1,
    valid_from TIMESTAMPTZ,
    valid_until TIMESTAMPTZ,
    applicable_products JSONB,              -- [id, id, ...] lub NULL = wszystkie
    applicable_categories JSONB,
    first_order_only BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE
);

CREATE TABLE promo_code_usages (
    id BIGSERIAL PRIMARY KEY,
    promo_code_id BIGINT NOT NULL REFERENCES promo_codes(id),
    user_id BIGINT REFERENCES users(id),
    order_id BIGINT REFERENCES orders(id),
    used_at TIMESTAMPTZ
);
```

### 4.11. Abandoned cart recovery

Cron job co 1 h:
```php
Cart::whereNotNull('user_id')
    ->whereNull('abandoned_at')
    ->where('updated_at', '<', now()->subHours(6))
    ->whereHas('items')
    ->each(fn($cart) => {
        $cart->update(['abandoned_at' => now()]);
        SendAbandonedCartEmail::dispatch($cart);
    });
```

Email z linkiem z pre-filled koszykiem + opcjonalny rabat 5 % (token `RECOVER5`).

### 4.12. Events sklepu

| Event | Kiedy | Listener |
|-------|-------|----------|
| `ProductViewed` | Wejście na ProductPage | `LogProductView` (analytics, recommendations) |
| `AddedToCart` | Dodanie produktu | `NotifyWebhook` (GA4, FB Pixel) |
| `CartAbandoned` | Cron → 6h bez ruchu | `SendAbandonedCartEmail` |
| `CheckoutStarted` | Wejście do CheckoutPage | `LogCheckoutStart` |
| `OrderPlaced` | Po webhooku P24 | `GenerateProformaPDF`, `SendOrderEmail`, `NotifyKanban` |
| `UserRegistered` | Magic link activation | `SendWelcomeEmail`, `AddToNewsletter` |

---

## CZĘŚĆ V — MODUŁ 18: Design Editor (Konva.js)

### 5.1. Cele edytora

1. **Nisko progowy projektant** — klient bez Photoshopa może stworzyć wizytówkę / ulotkę w 5 minut
2. **Szablony z branży** — biblioteka 50+ szablonów (lekarz, restauracja, fryzjer, mechanik, IT, …)
3. **Zgodność z drukiem** — automatyczne spady, CMYK preview, DPI warning, outline fontów
4. **Wydajność** — płynność na canvas nawet z 50+ obiektami
5. **Zapisywanie** — autosave co 5 s do DAMS, wersjonowanie, clone
6. **Export PDF** — serverowy render do PDF-X/4 z profilami kolorowymi

### 5.2. Stack edytora

| Warstwa | Technologia | Rola |
|---------|-------------|------|
| Engine | **Konva.js 9** | Imperative canvas API, layering, transformer |
| Reactive bridge | **vue-konva 3** | Vue 3 wrapper (`<v-stage>`, `<v-layer>`, `<v-text>`, …) |
| State management | **Pinia** | Store `useEditorStore` (scena, selekcja, historia undo/redo) |
| Fonts | `fontsource` (self-hosted) + Google Fonts proxy | Licencje |
| Images | HTML5 `<img>` + `Konva.Image` | Upload via tus |
| Shapes | Konva built-in (`Rect`, `Circle`, `Line`, `Path`) | Geometria |
| Filters | Konva filters (brightness, contrast, blur) | Edycja zdjęć |
| Export preview | `stage.toDataURL()` + canvas | PNG preview |
| Export PDF | **Serwerowy**: Puppeteer headless Chromium lub jsPDF + Konva.SVG | PDF/X-4 |
| Color conversion | **Serwerowy**: ICC profiles + Ghostscript | CMYK dla drukarni |

### 5.3. Mapa ekranu edytora

```
┌───────────────────────────────────────────────────────────────────────┐
│  [Logo] [Produkt: Wizytówka 85×55] [Zapisano]    [Cofnij][Ponów] [?] │
├──────────┬────────────────────────────────────────────────┬───────────┤
│          │                                                │           │
│          │                                                │           │
│ PANEL    │          CANVAS (Konva Stage)                  │ PANEL     │
│ NARZĘDZI │          ┌──────────────────────┐              │ PRAWY     │
│          │          │ [spad 3 mm czerwone] │              │           │
│ [T]ekst  │          │ ┌──────────────────┐ │              │ Warstwy:  │
│ [Img]    │          │ │                  │ │              │ • Tekst 1 │
│ [Kształt]│          │ │   projekt        │ │              │ • Logo    │
│ [Linie]  │          │ │                  │ │              │ • Bg      │
│ [Ikony]  │          │ └──────────────────┘ │              │           │
│          │          │                      │              │ Właściwo: │
│ Szablony │          └──────────────────────┘              │ - kolor   │
│          │                                                │ - font    │
│ Zdjęcia  │          [ Strona 1: Przód | Strona 2: Tył ]  │ - rozmiar │
│          │                                                │           │
│ Fonty    │                                                │           │
│          │                                                │           │
├──────────┴────────────────────────────────────────────────┴───────────┤
│ [Zoom: 100%] [Siatka: ON] [Prowadnice: ON]  [Preview] [Zapisz i dalej]│
└───────────────────────────────────────────────────────────────────────┘
```

### 5.4. Struktura sceny (JSON)

```json
{
  "version": "1.0",
  "design_id": 12345,
  "product_id": 42,
  "format": { "width_mm": 85, "height_mm": 55 },
  "bleed_mm": 3,
  "color_mode": "CMYK",
  "dpi": 300,
  "pages": [
    {
      "id": "p1",
      "name": "Przód",
      "background": { "type": "solid", "color": "#ffffff" },
      "layers": [
        {
          "id": "l1",
          "type": "text",
          "x": 10,
          "y": 15,
          "width": 65,
          "height": 10,
          "text": "Jan Kowalski",
          "fontFamily": "Inter",
          "fontSize": 18,
          "fontWeight": 600,
          "color": "#000000",
          "align": "left",
          "rotation": 0,
          "locked": false,
          "visible": true
        },
        {
          "id": "l2",
          "type": "image",
          "src": "https://minio.drukarnia.pl/designs/12345/logo.svg",
          "x": 60,
          "y": 5,
          "width": 20,
          "height": 20,
          "filters": { "brightness": 1, "contrast": 1 }
        }
      ]
    },
    {
      "id": "p2",
      "name": "Tył",
      "background": { "type": "solid", "color": "#0066cc" },
      "layers": [...]
    }
  ],
  "metadata": {
    "created_at": "2026-04-19T10:00:00Z",
    "updated_at": "2026-04-19T10:05:32Z",
    "version_parent_id": null
  }
}
```

### 5.5. Schemat DB dla edytora

```sql
CREATE TABLE designs (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT REFERENCES users(id) ON DELETE SET NULL,
    product_id BIGINT NOT NULL REFERENCES products(id),
    name VARCHAR(200) NOT NULL DEFAULT 'Bez nazwy',
    source VARCHAR(20) NOT NULL,         -- 'editor' | 'upload' | 'template'
    status VARCHAR(20) NOT NULL,         -- 'draft' | 'finalized' | 'production_ready'
    format_id BIGINT REFERENCES media_formats(id),
    current_version_id BIGINT,
    pdf_path VARCHAR(500),                -- finalna PDF w MinIO
    preview_image_path VARCHAR(500),
    created_at TIMESTAMPTZ,
    updated_at TIMESTAMPTZ
);

CREATE TABLE design_versions (
    id BIGSERIAL PRIMARY KEY,
    design_id BIGINT NOT NULL REFERENCES designs(id) ON DELETE CASCADE,
    version_number INT NOT NULL,
    scene_json JSONB NOT NULL,
    parent_version_id BIGINT REFERENCES design_versions(id),
    created_by BIGINT REFERENCES users(id),
    is_autosave BOOLEAN DEFAULT TRUE,
    note TEXT,
    created_at TIMESTAMPTZ,
    UNIQUE(design_id, version_number)
);

CREATE TABLE design_templates (
    id BIGSERIAL PRIMARY KEY,
    category VARCHAR(50),                 -- 'business-card', 'flyer', 'rollup'
    industry VARCHAR(50),                 -- 'medical', 'food', 'beauty', 'it'
    name VARCHAR(200),
    description TEXT,
    scene_json JSONB NOT NULL,
    preview_image_path VARCHAR(500),
    is_premium BOOLEAN DEFAULT FALSE,     -- future: premium = platinum tier only
    uses_count INT DEFAULT 0,
    sort_order INT,
    is_active BOOLEAN DEFAULT TRUE
);

CREATE TABLE design_assets (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    type VARCHAR(20) NOT NULL,            -- 'image' | 'logo' | 'font'
    name VARCHAR(200),
    file_path VARCHAR(500) NOT NULL,
    thumbnail_path VARCHAR(500),
    size_bytes BIGINT,
    mime_type VARCHAR(50),
    metadata JSONB,                       -- dla fontów: family, weights; dla obrazków: dim, dpi
    created_at TIMESTAMPTZ
);

CREATE TABLE design_fonts (
    id BIGSERIAL PRIMARY KEY,
    family VARCHAR(100) NOT NULL,
    source VARCHAR(20),                   -- 'google' | 'fontsource' | 'custom'
    weights JSONB,                        -- [300, 400, 500, 700]
    supports_latin_ext BOOLEAN DEFAULT TRUE,
    is_active BOOLEAN DEFAULT TRUE,
    license VARCHAR(100),                 -- 'OFL' | 'Apache' | 'Adobe' | 'commercial'
    preview_text VARCHAR(200)
);
```

### 5.6. Workflow użytkownika

#### 5.6.1. Guest → Edytor → Zamówienie

```
1. /produkt/wizytowki → klik „Zaprojektuj online"
2. /edytor/nowy?product_id=42
   → backend: Create Design{ user_id: null, session_id, status: 'draft' }
   → wybór szablonu z biblioteki (category: business-card)
   → załaduj scene_json z design_templates
3. Edycja (autosave co 5 s)
   → POST /api/design/{id}/save (scene_json) → tworzy nowy design_version
4. Klik „Zapisz i dalej"
   → status: 'finalized'
   → GenerateDesignPdfJob + GeneratePreviewImageJob
5. Redirect → /produkt/wizytowki?design_id={id}
   → wybór konfiguracji (format już zablokowany z design.format_id)
   → cena
6. „Dodaj do koszyka"
7. Checkout
   → podczas tworzenia zamówienia: Order.design_id = design_id
   → Design.user_id = user_id (po conversion)
```

#### 5.6.2. Logged user → Dashboard

```
1. /moje-konto/projekty → lista designów (draft + finalized + production_ready)
2. Klik „Klonuj" → nowy design z tym samym scene_json
3. Klik „Edytuj" → /edytor/{id}
4. Klik „Zamów jeszcze raz" → dodanie do koszyka z wcześniejszą konfiguracją
```

### 5.7. Eksport do PDF

#### 5.7.1. Strategia

Eksport **serwerowy** (nie przez `stage.toDataURL` klienta), żeby:
- Gwarantowana jakość (DPI 300)
- CMYK color space (nie RGB)
- Outline fontów (embed zamiast reference)
- Linie tnące i spady
- ICC profile (np. ISO Coated v2 dla offsetu)

#### 5.7.2. Pipeline

```
Klient: scene_json + przyciśnięcie „Zapisz i dalej"
  ↓ POST /api/design/{id}/export
Backend: GenerateDesignPdfJob (queue: pdf-export)
  ↓ 1. Pobierz design.scene_json z DB
  ↓ 2. Renderuj HTML z scene_json (Blade template + SVG)
  ↓ 3. Puppeteer: html → PDF (z CSS @page + bleed)
  ↓ 4. Ghostscript post-processing:
       - Convert RGB → CMYK (z ICC profile)
       - Embed fonts (outline)
       - Add registration marks, crop marks
       - Set /TrimBox (format), /BleedBox (+3mm), /MediaBox (+3mm crop marks)
  ↓ 5. Upload do MinIO (pdf_path)
  ↓ 6. Update design.pdf_path, status='production_ready'
  ↓ 7. Fire DesignReadyEvent → websocket broadcast do klienta
```

#### 5.7.3. Walidacja sceny przed eksportem

`SceneValidator::validate($scene)` sprawdza:
- Wymiary obiektów vs. format + spad (ostrzeżenie „tekst za blisko krawędzi")
- DPI obrazków (ostrzeżenie jeśli < 300 dpi w docelowym rozmiarze)
- Czy wszystkie fonty są w bibliotece (fallback na Arial jeśli nie)
- Czy kolory są w gamucie CMYK (ostrzeżenie dla jaskrawych RGB)
- Czy są wymagane elementy (np. dla wizytówki musi być tekst lub obraz)

Wyniki walidacji zwracane jako `ValidationReport` — w UI wyświetlane przed „Zapisz i dalej":
- Błędy krytyczne (font brakuje) → blokują eksport
- Ostrzeżenia (DPI niski) → wymagają potwierdzenia checkboxem „Rozumiem ryzyko"

### 5.8. Fonts pipeline

**Problem:** Konva.js używa canvas `font-family` → wymaga załadowania czcionki w DOM.

**Rozwiązanie:**
1. Lista dostępnych fontów w `design_fonts` (50–100 popularnych: Inter, Roboto, Montserrat, Poppins, Playfair, …)
2. Lazy-load: w edytorze przy otwieraniu sceny → dla każdego użytego font `family` → `document.fonts.load('16px Inter')`
3. Fallback: jeśli font nie załadowany w 3s → użyj Arial
4. Custom fonts (upload klienta): tylko tier Platinum (przyszłość); TTF upload → konwertuj do WOFF2 → zapis w `design_assets` (type: font)

### 5.9. Integracja z DAMS (Moduł 7)

- `designs.pdf_path` wskazuje na finalną PDF w `s3://minio/designs/{design_id}/final.pdf`
- Przy dodaniu do koszyka: `cart_items.design_id = design_id`
- Przy tworzeniu zamówienia: zaplanuj kopię PDF do `orders/{order_number}/design.pdf` (żeby design mógł być usunięty, a zamówienie miało swoją kopię)
- Preflight engine (Phase 4): dodatkowa walidacja serwerowa

### 5.10. Performance

- **Canvas virtualization**: Konva automatycznie optymalizuje rysowanie poza viewportem
- **Throttle undo/redo**: max 50 snapshotów sceny w pamięci (Pinia), starsze flush'owane
- **Autosave debounce**: 5 s nieaktywności (nie every keystroke)
- **Asset CDN**: zdjęcia z MinIO przez Cloudflare
- **Lazy-load templates**: galeria szablonów paginowana, podgląd jako PNG (nie cały JSON)

### 5.11. Mobilna wersja

- Edytor **nie jest** dostępny na mobile (< 768 px) — informacja „Do projektowania użyj laptopa lub tabletu"
- Na tablet (768–1024 px) — uproszczony toolbar, touch gestures
- Mobile: tylko przeglądanie i akceptacja projektów (z `/moje-konto/akceptacje`)

### 5.12. Lista komponentów Vue

```
resources/js/Pages/Editor/
├── Index.vue                          # Główna strona edytora /edytor/{id}
└── NewDesign.vue                      # /edytor/nowy?product_id=X (wybór szablonu)

resources/js/Components/Editor/
├── EditorCanvas.vue                   # Konva Stage + Layers
├── EditorToolbar.vue                  # Top toolbar (undo/redo/zoom)
├── EditorLeftPanel.vue                # Panel narzędzi (tekst, obraz, kształt)
├── EditorRightPanel.vue               # Warstwy + właściwości
├── ToolText.vue
├── ToolImage.vue
├── ToolShape.vue
├── ToolTemplate.vue
├── PageTabs.vue                       # Zakładki stron (Przód/Tył dla wizytówki)
├── LayerList.vue
├── PropertyPanel.vue                  # Dynamic panel właściwości zaznaczonego obiektu
├── ColorPicker.vue                    # CMYK + RGB, swatches
├── FontPicker.vue
├── AssetLibrary.vue                   # Zdjęcia, ikony
├── TemplateGallery.vue
├── SceneValidatorAlert.vue            # Alerts z walidacji przed eksportem
└── PreviewModal.vue                   # Podgląd PDF przed akceptacją
```

### 5.13. Pinia store

```ts
// resources/js/stores/editor.ts
import { defineStore } from 'pinia'

export const useEditorStore = defineStore('editor', {
  state: () => ({
    designId: null as number | null,
    scene: null as Scene | null,
    currentPageId: 'p1',
    selectedLayerIds: [] as string[],
    history: [] as Scene[],              // undo/redo
    historyIndex: -1,
    isDirty: false,
    isSaving: false,
    lastSavedAt: null as Date | null,
    zoom: 1,
    showGrid: true,
    showGuides: true,
  }),
  actions: {
    loadScene(scene: Scene) { … },
    addLayer(layer: Layer) { … },
    updateLayer(id: string, patch: Partial<Layer>) { … },
    deleteLayer(id: string) { … },
    selectLayers(ids: string[]) { … },
    undo() { … },
    redo() { … },
    async save() {
      this.isSaving = true
      await axios.post(`/api/design/${this.designId}/save`, { scene: this.scene })
      this.isDirty = false
      this.lastSavedAt = new Date()
      this.isSaving = false
    },
  },
})
```

---

## CZĘŚĆ VI — Portal klienta zintegrowany ze sklepem

### 6.1. Filozofia integracji

Portal klienta **nie jest osobną aplikacją** — to zestaw stron pod prefixem `/moje-konto/*` działających w tym samym SPA co sklep, z tym samym layoutem nawigacyjnym (header, cart, search), różniący się jedynie sidebar'em w body.

**Zalety:**
- Jeden login, jedna sesja, płynne przejście z checkoutu do „moich zamówień"
- Koszyk trwa przy przełączeniu między sklepem a portalem
- Wspólne komponenty (np. `<ProductCard />` używane w sklepie i w „zamów ponownie")
- Operator nie widzi portalu — portal tylko dla klienta

### 6.2. Middleware i autoryzacja

```php
// bootstrap/app.php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'portal' => \App\Http\Middleware\PortalGuard::class,
        'operator' => \App\Http\Middleware\OperatorGuard::class,
    ]);
})

// routes/web.php
Route::middleware(['web', 'portal'])->prefix('moje-konto')->name('portal.')->group(function () {
    Route::get('/', [PortalDashboardController::class, 'index'])->name('dashboard');
    Route::get('/zamowienia', [PortalOrdersController::class, 'index'])->name('orders.index');
    Route::get('/zamowienia/{order:number}', [PortalOrdersController::class, 'show'])->name('orders.show');
    Route::get('/akceptacje', [PortalApprovalsController::class, 'index'])->name('approvals.index');
    Route::get('/akceptacje/{design}', [PortalApprovalsController::class, 'show'])->name('approvals.show');
    Route::post('/akceptacje/{design}/accept', [PortalApprovalsController::class, 'accept'])->name('approvals.accept');
    Route::post('/akceptacje/{design}/reject', [PortalApprovalsController::class, 'reject'])->name('approvals.reject');
    // ... itd.
});
```

**`PortalGuard` middleware:**
```php
class PortalGuard {
    public function handle(Request $request, Closure $next) {
        if (! $request->user()) {
            return redirect()->route('shop.login', ['redirect' => $request->fullUrl()]);
        }
        if ($request->user()->is_guest) {
            return redirect()->route('shop.magic-link.prompt');
        }
        if (! $request->user()->hasRole('client')) {
            abort(403, 'Brak dostępu do portalu klienta.');
        }
        return $next($request);
    }
}
```

### 6.3. Layout portalu

```vue
<!-- resources/js/Layouts/ShopLayout.vue -->
<template>
  <div class="min-h-screen">
    <ShopHeader />  <!-- logo, nav, cart, login/avatar -->
    <main class="container mx-auto py-6">
      <div v-if="isPortalRoute" class="grid grid-cols-[240px_1fr] gap-6">
        <PortalSidebar />
        <slot />
      </div>
      <div v-else>
        <slot />
      </div>
    </main>
    <ShopFooter />
  </div>
</template>
```

**`<PortalSidebar />`** (shadcn-vue `Sidebar` composable):

```
┌──────────────────────┐
│  👤 Jan Kowalski     │
│  [bronze] 👑         │   ← badge tier-u
│  ────────────────    │
│  📊 Panel            │
│  📦 Zamówienia  (3)  │   ← badge z liczbą otwartych
│  ✅ Akceptacje  (1)  │
│  📁 Pliki            │
│  🧾 Dokumenty        │
│  🔄 Ponów zamówienie │
│  🎨 Moje projekty    │
│  💬 Wiadomości  (2)  │
│  ⚠️  Reklamacje      │
│  ────────────────    │
│  📍 Adresy           │
│  ⚙️  Ustawienia      │
│  🚪 Wyloguj          │
└──────────────────────┘
```

### 6.4. Panel (Dashboard) portalu

**`/moje-konto`:**

1. **Powitanie**: "Witaj, Jan!" + badge tier lojalnościowy + progres do następnego tier
2. **Kafelki KPI**:
   - Aktywne zamówienia (link do listy z filtrem `active`)
   - Zamówienia w ostatnich 30 dniach
   - Wydane w tym roku (PLN netto)
   - Oszczędzone na rabatach lojalnościowych
3. **Następne akcje** (alerts):
   - „⚠️ Masz 1 projekt do akceptacji" (link)
   - „📬 Zamówienie #2026-0042 wysłane (numer paczki INP123456)"
4. **Ostatnie zamówienia** (5 ostatnich) — Card grid z statusami
5. **Polecane produkty** — na podstawie historii zakupów

### 6.5. Lista zamówień

**`/moje-konto/zamowienia`:**

- Data Table z kolumnami: Numer / Data / Status (badge) / Kwota brutto / Akcje
- Filtry: status (Select multi), zakres dat (DateRangePicker), min/max kwota
- Search po nazwie produktu lub numerze
- Paginacja 25/50/100
- Akcje w rzędzie (DropdownMenu):
  - „Podgląd" → szczegóły
  - „Faktura" → pobierz PDF
  - „Zamów ponownie" → klon konfiguracji do koszyka
  - „Zgłoś reklamację" (tylko dla COMPLETED)

### 6.6. Szczegóły zamówienia

**`/moje-konto/zamowienia/{orderNumber}`:**

Layout wielosekcyjny (Accordion):

1. **Nagłówek** — numer, data, status (+ timeline z historii stanów)
2. **Pozycje zamówienia** — Card per pozycja:
   - Nazwa + miniaturka produktu/projektu
   - Konfiguracja (lista parametrów)
   - Nakład, cena
3. **Pliki i projekty** — lista z akcjami (pobierz, podgląd, edytuj jeśli draft)
4. **Akceptacja proof** (jeśli wymagana)
5. **Dostawa** — metoda, adres, numer śledzenia (link do kuriera), ETA
6. **Płatność** — metoda, status, kwota, dokumenty (Proforma/Faktura)
7. **Koszty** — breakdown netto/VAT/brutto
8. **Wiadomości** — chat z operatorem (moduł 13 CommHub)
9. **Historia zmian** — timeline (ActivityLog)

**Przycisk „Zamów ponownie"** → `RepeatOrderAction`:
1. Bierze konfigurację z każdej pozycji
2. Przelicza cenę (może się zmienić — pokazuje diff)
3. Dodaje do koszyka
4. Redirect → `/koszyk`

### 6.7. Akceptacje (Approvals)

**`/moje-konto/akceptacje`:**

Lista projektów wymagających akceptacji (status `APPROVAL`):

```
┌─────────────────────────────────────────────────┐
│ 🔴 Oczekujące akceptacji (2)                   │
├─────────────────────────────────────────────────┤
│ [thumbnail] Wizytówka Kowalski                 │
│             Zamówienie #2026-0042              │
│             Wysłano 2026-04-19 10:30           │
│             [Zobacz i zaakceptuj]              │
├─────────────────────────────────────────────────┤
│ [thumbnail] Ulotki Restauracja                 │
│             Zamówienie #2026-0043              │
│             [Zobacz i zaakceptuj]              │
└─────────────────────────────────────────────────┘
```

**`/moje-konto/akceptacje/{designId}`** — szczegół akceptacji:

- PDF Viewer (PDF.js) z powiększaniem, stronami
- Przyciski: **„Akceptuję do druku"** (primary, zielony) / **„Potrzebuję zmian"** (secondary)
- Akceptacja:
  - Dialog potwierdzający: „Czy na pewno akceptujesz? Po akceptacji nie można edytować."
  - Checkbox „Przeczytałem projekt i biorę odpowiedzialność za treść i literówki"
  - Checkbox „Zgadzam się na parametry techniczne (format, kolory, wykończenie)"
  - Przycisk „Akceptuję" → POST `/moje-konto/akceptacje/{designId}/accept`
    - Zapis: `design_approvals` (user_id, ip, user_agent, timestamp, checksum PDF)
    - Transition order: APPROVAL → APPROVED → PRODUCTION
    - Fire `ApprovalAccepted` event → notify kanban
    - Redirect → szczegóły zamówienia z toast "Zaakceptowano"
- Odrzucenie:
  - Dialog z Textarea „Opisz co trzeba zmienić"
  - Upload załączników (screenshot)
  - POST `/moje-konto/akceptacje/{designId}/reject`
    - Transition order: APPROVAL → REVISION
    - Fire `ApprovalRejected` event → notify operator
    - Powstaje task dla designera

```sql
CREATE TABLE design_approvals (
    id BIGSERIAL PRIMARY KEY,
    design_id BIGINT NOT NULL REFERENCES designs(id),
    order_id BIGINT REFERENCES orders(id),
    user_id BIGINT NOT NULL REFERENCES users(id),
    decision VARCHAR(20) NOT NULL,        -- 'accepted' | 'rejected'
    pdf_checksum VARCHAR(64),             -- SHA256 akceptowanego pliku
    ip_address VARCHAR(45),
    user_agent TEXT,
    note TEXT,                            -- opis zmian przy reject
    attachments JSONB,                    -- [path, path]
    created_at TIMESTAMPTZ NOT NULL
);
```

### 6.8. Pliki (Files)

**`/moje-konto/pliki`:**

- Grid / lista wszystkich plików klienta:
  - Uploadowane PDF (do zamówień)
  - Designy z edytora
  - Assets (logotypy, zdjęcia) — z `design_assets`
- Filtry: typ, zamówienie, data
- Akcje: pobierz, udostępnij (signed URL), usuń (tylko drafty)

### 6.9. Dokumenty (Documents)

**`/moje-konto/dokumenty`:**

- Lista faktur, proform, paragonów:
  - Numer dokumentu
  - Data wystawienia
  - Typ (Proforma / Faktura VAT / Paragon / Korekta)
  - Kwota brutto
  - Status płatności
  - Link do PDF (pobierz)
- Wszystkie generowane przez moduł 10 Finance, linkowane z `orders`

### 6.10. Reklamacje (Complaints)

**`/moje-konto/reklamacje`:**

- Lista zgłoszeń: numer, zamówienie, data, status, akcje
- Button „Nowa reklamacja" (tylko gdy jest zamówienie COMPLETED w ostatnich 14 dniach):
  - Wybór zamówienia
  - Wybór pozycji (checkbox z listy)
  - Typ reklamacji: Select (jakość druku, błąd w zamówieniu, uszkodzenie, opóźnienie, inne)
  - Opis: Textarea
  - Upload zdjęć (minimum 3 dla jakości druku)
  - Submit → POST `/moje-konto/reklamacje` → tworzy `complaints` record, fire `ComplaintCreated`, notify operator

### 6.11. Projekty (Designs)

**`/moje-konto/projekty`:**

- Grid wszystkich designów użytkownika (draft, finalized, production_ready)
- Filtr: produkt, status
- Akcje: podgląd, edytuj, klonuj, usuń (draft), zamów
- CTA „Nowy projekt" → wybór produktu → edytor

### 6.12. Wiadomości (Messages)

**`/moje-konto/wiadomosci`:**

Integracja z modułem 13 CommHub (in-app chat):

- Lista wątków z operatorem (grupowane po zamówieniu)
- Klik → chat z historią wiadomości
- Input z załącznikami
- Real-time przez Reverb (WebSocket channel `private-user.{id}.messages`)
- Notyfikacje desktop (via Web Push API — Phase 4)

```sql
CREATE TABLE conversations (
    id BIGSERIAL PRIMARY KEY,
    order_id BIGINT REFERENCES orders(id),
    client_id BIGINT NOT NULL REFERENCES clients(id),
    subject VARCHAR(200),
    last_message_at TIMESTAMPTZ,
    unread_count_client INT DEFAULT 0,
    unread_count_operator INT DEFAULT 0,
    status VARCHAR(20) DEFAULT 'open'     -- 'open' | 'closed'
);

CREATE TABLE messages (
    id BIGSERIAL PRIMARY KEY,
    conversation_id BIGINT NOT NULL REFERENCES conversations(id) ON DELETE CASCADE,
    sender_type VARCHAR(20),              -- 'client' | 'operator' | 'system'
    sender_id BIGINT,
    body TEXT NOT NULL,
    attachments JSONB,
    read_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ
);
```

### 6.13. Adresy (Addresses)

**`/moje-konto/adresy`:**

- Lista adresów dostawy i do faktury
- Default flagi
- CRUD (Dialog + Form)
- Integracja z GUS dla adresów B2B (auto-fill po NIP)

```sql
CREATE TABLE addresses (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    type VARCHAR(20) NOT NULL,            -- 'shipping' | 'billing'
    label VARCHAR(100),                   -- "Dom", "Biuro"
    company_name VARCHAR(200),
    nip VARCHAR(15),
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    street VARCHAR(200),
    building VARCHAR(20),
    apartment VARCHAR(20),
    city VARCHAR(100),
    postal_code VARCHAR(20),
    country_code VARCHAR(2) DEFAULT 'PL',
    phone VARCHAR(30),
    is_default BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMPTZ
);
```

### 6.14. Ustawienia (Settings)

**`/moje-konto/ustawienia`:**

Tabs:
1. **Profil**: imię, nazwisko, email (z weryfikacją), telefon, avatar
2. **Hasło**: zmiana hasła (z wymogiem starego)
3. **Bezpieczeństwo**: 2FA (TOTP — Google Authenticator), aktywne sesje (widok + logout), historia logowań
4. **Preferencje**: język (PL/EN/UK w przyszłości), powiadomienia (checkbox per kanał: email/sms/push)
5. **Dane firmy** (dla B2B): nazwa, NIP, REGON, dane kontraktu
6. **Faktury** (tryb wystawiania): Paragon domyślnie vs. Faktura VAT domyślnie
7. **Lojalność**: progres do następnego tier, historia punktów (future)
8. **RODO**: przycisk „Pobierz moje dane" (eksport JSON), „Usuń konto" (z okresem 30 dni grace period)

---

## CZĘŚĆ VII — Zmiany w pozostałych modułach

Każdy moduł ERP musi zostać dostosowany do nowych kanałów wejścia (sklep, portal) i nowych elementów (design editor, nowy pricing).

### 7.1. MODUŁ 1: CRM

**Zmiany:**
- `clients.source` — nowa kolumna: `manual` | `shop_guest` | `shop_register` | `api` | `imported`
- `clients.loyalty_tier` — referencja do `loyalty_tiers`
- `clients.loyalty_annual_revenue` — rolling sum z ostatnich 12 mc
- Nowa zakładka w profilu klienta (admin): „Aktywność w sklepie" (koszyki, wizyty, opuszczone koszyki)
- Integracja GUS: `php artisan gus:refresh-client {id}` — aktualizacja danych firmowych
- Segmenty klientów (nowe): `first_time_buyer`, `repeat`, `vip` (>50k PLN/rok), `inactive_6m`, `inactive_12m`

### 7.2. MODUŁ 2: Orders

**Zmiany:**
- `orders.channel` — enum (`manual` | `shop_guest` | `shop_user` | `portal_repeat` | `api` | `email` | `phone`)
- `orders.price_breakdown JSONB` — pełny PricingResult
- `orders.engine_type`, `orders.sheets_used`, `orders.meters_used`, `orders.pieces_count` — dane z silnika
- `orders.design_id` — FK do designs (może być NULL dla uploadów bez edytora)
- `orders.guest_email`, `orders.guest_phone` — dla zamówień bez usera
- `orders.paid_at`, `orders.payment_method`, `orders.payment_provider`
- Widoki Kanban (kierownik produkcji):
  - Kolumny wg statusu (PRODUCTION, APPROVAL, …)
  - Filtry: maszyna, termin, klient, channel
  - Drag & drop między kolumnami (z guard — tylko dozwolone transition'y)
- Lista zamówień (operator):
  - DataTable + filtry: status (multi), channel (multi), klient, data, kwota, maszyna
  - Bulk actions: zmiana statusu, przypisanie do maszyny, generowanie proform

### 7.3. MODUŁ 3: Products

**Zmiany (duże, Web2Print):**
- Tabele DB z CZĘŚĆ III § 3.2 (17 tabel)
- Admin UI z CZĘŚĆ III § 3.7 (Inertia + Vue + shadcn-vue)
- `products.is_shop_visible` — flag dla sklepu
- `products.seo_*` — pola SEO
- `products.description_long` — HTML dla ProductPage
- `products.images` — galeria (JSONB array)
- Preview konfiguratora w admin — „Zobacz jak wygląda dla klienta"

### 7.4. MODUŁ 5: Quotes (Wyceny)

**Zmiany:**
- Wyceny generowane teraz przez **PricingPipeline** (nie ręcznie)
- Operator tworzy wycenę → konfigurator (taki sam jak w sklepie) → wynik → PDF wycena
- `quotes.valid_until` — termin ważności
- `quotes.status` — DRAFT | SENT | ACCEPTED | REJECTED | EXPIRED
- Akcja „Konwertuj do zamówienia" → transition na Order
- Wysłanie email z PDF wyceny + link „Akceptuj online" (public link z tokenem)
- Klient może zaakceptować wycenę nawet bez konta → generuje guest order

### 7.5. MODUŁ 6: Designs (Approval Tracking)

**Zmiany:**
- Dodano integrację z MODUŁEM 18 (Design Editor)
- `designs.source` rozróżnia editor/upload/template
- Historia wersji + historia akceptacji
- Link do podglądu w portalu

### 7.6. MODUŁ 7: Files (DAMS)

**Zmiany:**
- Tus.io upload (resumable) dla dużych PDF-ów (>100 MB)
- Konwersja PDF → PNG miniatur (Ghostscript) dla podglądu
- Miniatury sm/md/lg (150 / 600 / 1200 px)
- Signed URL do pobierania (S3 presigned, 1 h TTL)
- Asset library dla Design Editor (ikony, kształty, wzory)
- Tags i kolekcje

### 7.7. MODUŁ 8: Production (Kanban)

**Zmiany:**
- Widok Kanban z maszynami (kolumny = maszyny, karty = zlecenia)
- Pole `orders.assigned_machine_id` + `orders.machine_priority`
- Przypisanie zlecenia do maszyny (operator lub automat wg reguł)
- Czas estymowany vs. rzeczywisty — logowanie przez operatora (mobile-friendly)
- Wynik → wpada do `order_cogs_breakdown.recalculated_after_production`

### 7.8. MODUŁ 9: Inventory (Magazyn)

**Zmiany:**
- Integracja z materials: każdy materiał ma `stock_level_sheets` / `stock_level_meters`
- Przy akceptacji zamówienia: rezerwacja materiału (`inventory_reservations`)
- Przy zakończeniu produkcji: zejście ze stanu
- Alerty low-stock (email + slack do właściciela)
- Zamówienia zakupowe u dostawców (Phase 4)

### 7.9. MODUŁ 10: Finance (Faktury i płatności)

**Zmiany:**
- Integracja P24/PayU dla sklepu (webhooks)
- Generowanie Proformy po złożeniu zamówienia (status PRICING → WAITING_PAYMENT)
- Po opłaceniu: Faktura Zaliczkowa (100 % zaliczka) lub Paragon
- Po zakończeniu produkcji: Faktura VAT końcowa (rozliczenie zaliczki) lub od razu FV
- MPP handling dla B2B >15k PLN
- Integracja z Subiektem nexo via API (eksport faktur → KSeF via Subiekt)
- Kolejka płatności pro-forma (3 dni → automatyczne cancel)
- Dokumenty: `invoices`, `invoice_items`, `payments`, `refunds`

### 7.10. MODUŁ 11: Logistics (Wysyłki)

**Zmiany:**
- Integracje z kurierami: InPost ShipX, DPD, DHL
- `shipping_methods` — dostępne metody (per region, per waga)
- `ShippingRateCalculator::calculate()` — cena dostawy dla koszyka (waga szacowana z produktu × qty)
- Po zakończeniu produkcji → automat tworzy list przewozowy
- Status tracking przez webhook kurierów
- Nadanie paczkomat via API InPost
- `orders.tracking_number`, `orders.shipped_at`, `orders.delivered_at`

### 7.11. MODUŁ 12: Complaints (Reklamacje)

**Zmiany:**
- Zgłoszenia z portalu (CZĘŚĆ VI § 6.10)
- SLA: 14 dni na rozpatrzenie
- Akcje: accept (refund/reprint/discount), reject (uzasadnienie)
- `complaints` + `complaint_items` + `complaint_resolutions`
- Refund → `refunds` w Finance
- Reprint → tworzy nowy Order z flagą `is_reprint_of: order_id`

### 7.12. MODUŁ 13: Comms Hub

**Zmiany:**
- Nowy kanał: **in-app chat** (conversation + message tables z CZĘŚĆ VI § 6.12)
- Wszystkie emaile przez queue (Mail jobs)
- SMS przez SMSAPI.pl dla kluczowych eventów (order placed, design ready, shipped, delivered)
- Templates: Blade Mailables w `resources/views/emails/`
- System powiadomień: `Notification` facade z channels (mail, database, broadcast, vonage/sms)
- Email parser (IMAP → OpenAI brief extractor) — ingest zapytań

### 7.13. MODUŁ 14: Reports & BI

**Zmiany:**
- Nowe raporty:
  - **Konwersja sklepu**: view / add to cart / checkout / paid (funnel)
  - **Marża rzeczywista vs. oczekiwana** (z COGS)
  - **Top produkty** (wg kwoty i wolumenu)
  - **Segmentacja klientów** (tier, LTV)
  - **Abandoned carts** — lista z kwotą
  - **Design editor usage** (ile designów, konwersja do zamówienia)
  - **Leadtime rzeczywisty** (avg przez stan FSM)
- Eksport CSV/XLSX
- Dashboardy (Chart.js + vue-chartjs)
- Filtry zakresem dat

### 7.14. MODUŁ 15: Settings & Users

**Zmiany:**
- Role: `owner`, `manager`, `operator`, `designer`, `production_lead`, `accountant`, `client`
- Permissions (spatie/laravel-permission): fine-grained per moduł
- `users.is_guest`, `users.is_shop_user`, `users.is_operator`
- Admin panel users → tabs (lista, role, invite, audit log)
- Global settings (admin): margin defaults, VAT, konfig kurierów, API keys
- `global_settings` (z CZĘŚĆ III)

### 7.15. MODUŁ 16: Integrations

**Zmiany:**
- Adaptery (Hexagonal Architecture) w `app/Adapters/`:
  - `SubiektAdapter` (nexo API)
  - `GusAdapter` (REGON API)
  - `P24Adapter` (Przelewy24)
  - `PayUAdapter`
  - `InPostAdapter` (ShipX)
  - `DpdAdapter`, `DhlAdapter`
  - `SmsApiAdapter` (SMSAPI.pl)
  - `OpenAiAdapter`
- Wszystkie z wspólnym interfejsem i `Circuit Breaker` pattern (spatie/laravel-circuit-breaker)
- Webhooks inbound: `routes/webhooks.php` (bez CSRF, z signature verification)

### 7.16. Rozszerzona lista eventów domenowych

| Event | Źródło | Listener(s) |
|-------|--------|-------------|
| `OrderCreated` | `CreateOrderAction` | `CalculatePriceListener`, `NotifyOperatorListener`, `CreateKanbanTaskListener` |
| `PriceCalculated` | `CalculatePriceAction` | `UpdateOrderListener`, `LogPricingAuditListener` |
| `OrderPaid` | P24 webhook | `TransitionToWaitingFilesListener`, `GenerateInvoiceZaliczkowaListener`, `NotifyClientListener` |
| `DesignUploaded` | `UploadDesignAction` | `PreflightCheckListener` (Phase 4), `NotifyClientListener` |
| `DesignFinalized` | Design Editor export | `GeneratePdfListener`, `AttachToOrderListener` |
| `ApprovalSent` | Operator sends to client | `NotifyClientListener` (email + SMS) |
| `ApprovalAccepted` | Client clicks accept | `TransitionToApprovedListener`, `NotifyKanbanListener`, `LogApprovalListener` |
| `ApprovalRejected` | Client requests changes | `TransitionToRevisionListener`, `NotifyDesignerListener` |
| `ProductionStarted` | Operator marks start | `LogStartTimeListener`, `ReserveMaterialListener` |
| `ProductionFinished` | Operator marks done | `TransitionToShippingListener`, `UpdateCogsListener` |
| `OrderShipped` | Courier API webhook | `NotifyClientListener` (tracking link), `TransitionListener` |
| `OrderDelivered` | Courier API webhook | `TransitionToCompletedListener`, `RequestReviewListener` |
| `ComplaintCreated` | Client submits | `NotifyOperatorListener`, `CreateTaskListener` |
| `PaymentReceived` | P24/PayU webhook | `UpdateOrderPaymentListener`, `GenerateInvoiceFinalListener` |
| `InvoiceGenerated` | Finance | `SendToSubiektListener`, `EmailClientListener` |
| `CartAbandoned` | Cron job | `SendAbandonedCartEmailListener` |
| `UserRegistered` | Sklep | `SendWelcomeEmailListener`, `CreateClientRecordListener` |
| `LoyaltyTierChanged` | Cron recalculation | `NotifyClientListener`, `LogTierChangeListener` |

### 7.17. State Machine (16 statusów zamówienia) — niezmieniona

```
NEW → PRICING → WAITING_FILES → WAITING_PAYMENT → DESIGNING → APPROVAL
  ↑                                                          ↓ (reject)
  │                                                       REVISION → APPROVAL
  │                                                          ↓ (accept)
  │                                                       APPROVED
  │                                                          ↓
  │                                                       PRODUCTION → DONE
  │                                                                      ↓
  │                                                                   SHIPPING → SHIPPED → COMPLETED
  │                                                                                            ↓
  │                                                                                         COMPLAINT
  │
  └─── (cancel z dowolnego statusu pre-PRODUCTION) → CANCELLED
                                                     SUSPENDED (operator freeze)
```

Transition rules w `app/Domain/Orders/States/` (spatie/laravel-model-states).

---

## CZĘŚĆ VIII — Pełny workflow (scenariusze A→Z)

### 8.1. Scenariusz 1: B2C guest — wizytówki z edytora

**Aktor:** Anna, księgowa, nie ma konta w drukarni, chce 100 wizytówek.

#### Kroki

1. **Wejście** — Anna wchodzi na `https://drukarnia.pl/` (Google reklama)
2. **Katalog** — klika „Wizytówki" → `/kategoria/wizytowki` → widzi grid 8 produktów → klika „Wizytówki standardowe"
3. **Konfigurator** — `/produkt/wizytowki-standardowe`:
   - Format: 85×55 mm (default)
   - Materiał: 350 g kreda mat (default)
   - Kolorystyka: 4+0 CMYK
   - Uszlachetnienia: brak
   - Nakład: 100 (wpisuje w NumberField)
   - Termin: Standard (3–5 dni)
   - **Cena wyliczona real-time** (debounce 300 ms): 39.00 PLN netto / 47.97 PLN brutto
4. **Edytor** — Anna nie ma projektu → klika „Zaprojektuj online"
   - System: Create `Design{ session_id, status: 'draft' }` → redirect `/edytor/{id}`
   - Anna wybiera szablon „Biuro księgowe 01" → scena załadowana z `design_templates`
   - Edytuje: zmienia imię, telefon, email, dodaje swoje logo (upload via `design_assets`)
   - Autosave co 5 s
   - Klika „Zapisz i dalej" → `GenerateDesignPdfJob` → PDF w MinIO → status `finalized`
5. **Powrót do konfiguratora** — `/produkt/wizytowki-standardowe?design_id={id}`:
   - Format zablokowany (z designu)
   - Miniatura designu w sekcji „Twój projekt"
   - Klik „Dodaj do koszyka"
6. **Koszyk** — `/koszyk`:
   - 1 pozycja: Wizytówki standardowe × 100 / 39.00 PLN netto
   - Cena dostawy: Paczkomat 9.99 PLN
   - SUM: 48.99 netto / 60.25 brutto
   - „Do zamówienia"
7. **Checkout krok 1** — Kontakt:
   - Email: `anna@example.pl`
   - Telefon: `+48 600 100 200`
   - Brak loginu → guest
8. **Checkout krok 2** — Dostawa:
   - Paczkomat: wybrany z widgetu InPost (GUI map)
9. **Checkout krok 3** — Faktura:
   - Faktura VAT? Nie → Paragon
   - Dane do paragonu: nieobowiązkowe
10. **Checkout krok 4** — Płatność:
    - BLIK (via Przelewy24)
11. **Checkout krok 5** — Podsumowanie:
    - Akceptacja regulaminu ✓
    - „Zapisz moje dane" ✓ → magic link po płatności
    - Klik „Zapłać i zamów"
12. **Przekierowanie P24** — Anna wpisuje kod BLIK → płatność OK
13. **Webhook** — backend otrzymuje:
    - `PaymentReceivedEvent`
    - Create `Order{ status: NEW → PRICING → APPROVED (design już gotowy) }`
    - `Order.design_id = design.id`, `Order.channel = 'shop_guest'`
    - Generowanie Paragonu (moduł 10 Finance)
    - Wysłanie emaila potwierdzenia + SMS
    - Wysłanie magic link email „Aktywuj konto jednym klikiem, masz 7 dni"
14. **Anna** — klika magic link w mailu → `/magic-link/{token}` → auto-login + form „Ustaw hasło" → `users.is_guest = false`
15. **Kanban** — zlecenie pojawia się w kolumnie PRODUCTION dla drukarza
16. **Produkcja** — drukarz drukuje, oznacza „Gotowe" → transition DONE → SHIPPING
17. **Wysyłka** — moduł 11 Logistics tworzy nadanie InPost Paczkomat → tracking number → SMS do Anny
18. **Doręczenie** — webhook InPost „Paczka odebrana" → transition SHIPPED → COMPLETED → prośba o recenzję emailem
19. **Portal** — Anna loguje się na `/moje-konto` → widzi zamówienie, fakturę PDF, link do ponownego zamówienia

#### Metryki
- **Time to purchase**: 12 minut (od landing page)
- **Czas pracy operatora**: 0 minut (pełna automatyzacja)

### 8.2. Scenariusz 2: B2B logged — kontraktowy rabat + wielokrotne pozycje

**Aktor:** Michał, szef agencji reklamowej, konto B2B z `client_pricing_override` -10 %.

#### Kroki

1. **Login** — `/zaloguj` → `/moje-konto` (dashboard z tier GOLD, +5 % dodatkowy rabat lojalnościowy)
2. **Sklep** — Michał klika „Zamów" → `/kategoria/ulotki`
3. **Konfiguracja 1** — Ulotki A5, 4+4, 250 g kreda, nakład 5000, termin przyspieszony:
   - Cena dla nie-zalogowanego: 890.00 PLN
   - Z overrides: 890 × (1 - 0.10) = 801.00 PLN (kontraktowy)
   - Z loyalty: 801 × (1 - 0.05) = 760.95 PLN
   - Wyświetlona cena: **760.95 PLN netto** + adnotacja „Rabat kontraktowy -10%, lojalność -5%"
4. **Upload PDF** — Michał wgrywa gotowy PDF (tus resumable, 180 MB, 45 s upload) → plik w `files` + link do `cart_item.design_id`
5. **Konfiguracja 2** — Plakaty A2, 200 g kreda, nakład 500 → dodaje do tego samego koszyka
6. **Koszyk** — 2 pozycje, SUM netto 2450 PLN → MPP required (bo > 15k brutto? Nie, ale pole info)
7. **Checkout** — szybki (dane z profilu):
   - Dostawa: kurier DPD na biuro
   - Faktura VAT (default dla B2B) z NIP
   - Płatność: przelew online P24
8. **Po płatności** — Order utworzony z 2 pozycjami, status NEW → PRICING → WAITING_FILES (plik już załadowany) → pre-flight skip (Phase 4) → APPROVAL
9. **Akceptacja** — Michał dostaje email + SMS „Projekty gotowe do akceptacji" → portal → PDF Viewer → akceptuje każdy osobno
10. **Production → Shipping → Delivered** (jak wyżej)
11. **Faktura VAT** — automatycznie wygenerowana po doręczeniu → wysłana emailem + do KSeF via Subiekt

### 8.3. Scenariusz 3: B2B enterprise — custom packaging + operator-led

**Aktor:** Firma kosmetyczna „KosmoBox", zamówienie na 50 000 pudełek na wymiar (500×250×100 mm).

#### Kroki

1. **Kontakt** — email na `sprzedaz@drukarnia.pl` z zapytaniem + dielines PDF
2. **Operator** — przegląda CommHub, klika „Utwórz zapytanie" → Moduł 5 Quotes
3. **Konfiguracja custom** — produkt „Opakowanie indywidualne" (engine: `piece`), ręcznie wpisuje parametry:
   - Wymiary: 500×250×100 mm
   - Materiał: karton 350 g offset
   - Nakład: 50 000
   - Wykończenie: foliowanie mat + lakier UV wybiórczy (3 miejsca)
   - Termin: 4 tygodnie
4. **Wycena** — pipeline uruchomiony z `piece` engine → 48 500 PLN netto
5. **Quote PDF** — operator generuje PDF wyceny, dodaje informacje o projekcie, wysyła email
6. **Akceptacja klienta** — Michał (klient) klika „Akceptuję" w linku w emailu (bez konta — magic token) → auto-konwersja na Order + konto klienta
7. **MPP trigger** — kwota brutto ~60k PLN > 15k PLN → MPP zaznaczony w proformie
8. **Zaliczka** — 50 % pro-forma, generowana automatycznie
9. **Płatność zaliczki** — przelew tradycyjny, operator odznacza po zaksięgowaniu w Subiekcie
10. **Pipeline** — design approval, production, shipping jak poprzednio
11. **Faktura końcowa** — rozliczenie zaliczki + faktura VAT pozostałej kwoty, KSeF via Subiekt

---

## CZĘŚĆ IX — Skonsolidowany schemat bazy danych

### 9.1. Lista wszystkich tabel (post-implementacja)

Grupowanie tematyczne:

#### 9.1.1. Pricing Engine (17 tabel) — spec Web2Print
- `global_settings`
- `media_formats`
- `materials`
- `parameters`
- `parameter_options`
- `page_configs`
- `products`
- `product_margins`
- `product_costs`
- `product_formats`
- `product_materials`
- `product_parameters`
- `product_parameter_options`
- `product_page_configs`
- `exclusion_rules`
- `exclusion_conditions`
- `exclusion_actions`

#### 9.1.2. Pricing Overlays (4)
- `client_pricing_overrides`
- `loyalty_tiers`
- `order_cogs_breakdown`
- `price_cache`

#### 9.1.3. Users & Clients (5)
- `users` (Fortify + is_guest, is_shop_user, is_operator)
- `clients` (profile B2B/B2C, loyalty_tier, gus_data)
- `addresses`
- `user_sessions`
- `magic_links`

#### 9.1.4. Orders & Workflow (7)
- `orders`
- `order_items`
- `order_status_history` (state machine transitions)
- `order_notes`
- `order_attachments`
- `order_assignments` (maszyna, operator)
- `order_timers` (czas produkcji)

#### 9.1.5. Shop & Cart (4)
- `carts`
- `cart_items`
- `promo_codes`
- `promo_code_usages`

#### 9.1.6. Quotes (2)
- `quotes`
- `quote_items`

#### 9.1.7. Designs & Editor (5)
- `designs`
- `design_versions`
- `design_templates`
- `design_assets`
- `design_fonts`

#### 9.1.8. Approvals (1)
- `design_approvals`

#### 9.1.9. Files/DAMS (2)
- `files`
- `file_conversions` (tracking Ghostscript/ImageMagick jobs)

#### 9.1.10. Production (3)
- `machines`
- `production_tasks`
- `production_logs`

#### 9.1.11. Inventory (3)
- `inventory_items` (materialy × warehouse)
- `inventory_movements`
- `inventory_reservations`

#### 9.1.12. Finance (5)
- `invoices`
- `invoice_items`
- `payments`
- `refunds`
- `subiekt_sync_log`

#### 9.1.13. Logistics (3)
- `shipping_methods`
- `shipments`
- `shipment_events`

#### 9.1.14. Complaints (3)
- `complaints`
- `complaint_items`
- `complaint_resolutions`

#### 9.1.15. Communications (3)
- `conversations`
- `messages`
- `email_inbound_log` (IMAP parser)

#### 9.1.16. Integrations (2)
- `webhook_inbound_log`
- `integration_credentials` (encrypted API keys)

#### 9.1.17. Permissions & Audit (4)
- `roles` (spatie)
- `permissions` (spatie)
- `model_has_roles` (spatie)
- `activity_log` (spatie, partycjonowany po dacie)

**Łącznie: ~73 tabele**

### 9.2. Kluczowe indeksy i ograniczenia

```sql
-- Orders: fast lookup by number, client, status, date
CREATE INDEX idx_orders_number ON orders(number);
CREATE INDEX idx_orders_client_id ON orders(client_id);
CREATE INDEX idx_orders_status ON orders(status);
CREATE INDEX idx_orders_created_at ON orders(created_at DESC);
CREATE INDEX idx_orders_channel_created ON orders(channel, created_at DESC);

-- Products shop visibility
CREATE INDEX idx_products_shop_visible ON products(is_shop_visible, is_active);
CREATE INDEX idx_products_slug ON products(slug);

-- Pricing cache
CREATE INDEX idx_price_cache_hash ON price_cache(input_hash);
CREATE INDEX idx_price_cache_expires ON price_cache(expires_at);

-- Activity log partycjonowane po miesiącach
CREATE TABLE activity_log (
    ...
) PARTITION BY RANGE (created_at);

-- Przykład partycji na kwiecień 2026
CREATE TABLE activity_log_2026_04 PARTITION OF activity_log
    FOR VALUES FROM ('2026-04-01') TO ('2026-05-01');

-- Full-text search (produkty)
CREATE INDEX idx_products_fts ON products
    USING GIN (to_tsvector('polish', name || ' ' || description));
```

### 9.3. Migracje — kolejność

1. Foundation: `users`, `roles`, `permissions`, `global_settings`, `media_formats`, `materials`, `parameters`, `parameter_options`, `page_configs`
2. Products: `products`, `product_*` (10 tabel)
3. Exclusions: `exclusion_*` (3 tabele)
4. Clients: `clients`, `addresses`, `client_pricing_overrides`, `loyalty_tiers`
5. Orders core: `orders`, `order_*`, `order_status_history`, `order_cogs_breakdown`
6. Designs: `designs`, `design_*` (5 tabel), `design_approvals`
7. Shop: `carts`, `cart_items`, `promo_codes`, `promo_code_usages`
8. Files: `files`, `file_conversions`
9. Production: `machines`, `production_*`
10. Inventory: `inventory_*`
11. Finance: `invoices`, `invoice_items`, `payments`, `refunds`, `subiekt_sync_log`
12. Logistics: `shipping_*`, `shipments`, `shipment_events`
13. Complaints: `complaints`, `complaint_*`
14. Comms: `conversations`, `messages`, `email_inbound_log`
15. Misc: `magic_links`, `price_cache`, `webhook_inbound_log`, `quotes`, `quote_items`
16. Activity log (partycjonowany)

---

## CZĘŚĆ X — Mapa API i routingu

### 10.1. Struktura routingu

```
routes/
├── web.php               # Inertia: sklep + portal
├── admin.php             # Inertia: panel operatora (/admin/*)
├── api.php               # REST (Sanctum): B2B API + shop AJAX
├── webhooks.php          # Inbound webhooks (P24, InPost, …)
├── console.php           # Artisan schedule
└── channels.php          # Broadcasting (Reverb)
```

### 10.2. Publiczny sklep (web.php, nie wymaga auth)

```
GET  /                                 → Shop\HomeController@index
GET  /kategoria/{slug}                 → Shop\CategoryController@show
GET  /produkt/{slug}                   → Shop\ProductController@show
GET  /szukaj                           → Shop\SearchController@index
GET  /koszyk                           → Shop\CartController@show
POST /koszyk/dodaj                     → Shop\CartController@add
PATCH /koszyk/{item}                   → Shop\CartController@update
DELETE /koszyk/{item}                  → Shop\CartController@remove
POST /koszyk/kod-rabatowy              → Shop\CartController@applyPromo
GET  /zamowienie                       → Shop\CheckoutController@show
POST /zamowienie                       → Shop\CheckoutController@submit
GET  /zamowienie/potwierdzenie/{order} → Shop\CheckoutController@confirmation

# Auth
GET  /zaloguj                          → Auth\LoginController@show
POST /zaloguj                          → Auth\LoginController@store
GET  /zarejestruj                      → Auth\RegisterController@show
POST /zarejestruj                      → Auth\RegisterController@store
GET  /przypomnij-haslo                 → Auth\PasswordResetController@show
POST /przypomnij-haslo                 → Auth\PasswordResetController@send
GET  /magic-link/{token}               → Auth\MagicLinkController@handle
POST /wyloguj                          → Auth\LogoutController

# Design editor (publiczny dla guestów, session-based)
GET  /edytor/nowy                      → Editor\EditorController@new
GET  /edytor/{design}                  → Editor\EditorController@edit
```

### 10.3. Portal klienta (web.php + portal middleware)

```
GET  /moje-konto                        → Portal\DashboardController@index
GET  /moje-konto/zamowienia             → Portal\OrdersController@index
GET  /moje-konto/zamowienia/{order}     → Portal\OrdersController@show
POST /moje-konto/zamowienia/{order}/powtorz → Portal\OrdersController@repeat
GET  /moje-konto/akceptacje             → Portal\ApprovalsController@index
GET  /moje-konto/akceptacje/{design}    → Portal\ApprovalsController@show
POST /moje-konto/akceptacje/{design}/accept → Portal\ApprovalsController@accept
POST /moje-konto/akceptacje/{design}/reject → Portal\ApprovalsController@reject
GET  /moje-konto/pliki                  → Portal\FilesController@index
GET  /moje-konto/dokumenty              → Portal\DocumentsController@index
GET  /moje-konto/dokumenty/{invoice}/pobierz → Portal\DocumentsController@download
GET  /moje-konto/reklamacje             → Portal\ComplaintsController@index
POST /moje-konto/reklamacje             → Portal\ComplaintsController@store
GET  /moje-konto/reklamacje/{complaint} → Portal\ComplaintsController@show
GET  /moje-konto/projekty               → Portal\DesignsController@index
GET  /moje-konto/projekty/{design}      → Portal\DesignsController@show
POST /moje-konto/projekty/{design}/klonuj → Portal\DesignsController@clone
GET  /moje-konto/wiadomosci             → Portal\MessagesController@index
GET  /moje-konto/adresy                 → Portal\AddressesController@index
POST /moje-konto/adresy                 → Portal\AddressesController@store
PATCH /moje-konto/adresy/{addr}         → Portal\AddressesController@update
DELETE /moje-konto/adresy/{addr}        → Portal\AddressesController@destroy
GET  /moje-konto/ustawienia             → Portal\SettingsController@index
PATCH /moje-konto/ustawienia/profil     → Portal\SettingsController@updateProfile
PATCH /moje-konto/ustawienia/haslo      → Portal\SettingsController@updatePassword
POST /moje-konto/ustawienia/2fa/enable  → Portal\SettingsController@enable2fa
```

### 10.4. Panel operatora (admin.php, middleware `operator`)

```
/admin/*     → wszystkie strony zaczynające od /admin wymagają roli operator/manager/owner

GET  /admin                             → Admin\DashboardController@index
GET  /admin/zamowienia                  → Admin\OrdersController@index
GET  /admin/zamowienia/kanban           → Admin\OrdersController@kanban
GET  /admin/zamowienia/{order}          → Admin\OrdersController@show
POST /admin/zamowienia                  → Admin\OrdersController@store (ręczne zamówienie)
PATCH /admin/zamowienia/{order}/transition → Admin\OrdersController@transition
# ... CRUD dla klientów, produktów, cenników, magazynu, faktur, reklamacji, raportów

# Pricing admin
GET  /admin/pricing/produkty            → Admin\Pricing\ProductsController@index
GET  /admin/pricing/produkty/{product}  → Admin\Pricing\ProductsController@edit
POST /admin/pricing/produkty            → Admin\Pricing\ProductsController@store
PATCH /admin/pricing/produkty/{product} → Admin\Pricing\ProductsController@update
DELETE /admin/pricing/produkty/{product} → Admin\Pricing\ProductsController@destroy

GET  /admin/pricing/materialy           → Admin\Pricing\MaterialsController
GET  /admin/pricing/parametry           → Admin\Pricing\ParametersController
GET  /admin/pricing/formaty             → Admin\Pricing\MediaFormatsController
GET  /admin/pricing/uklady-stron        → Admin\Pricing\PageConfigsController
GET  /admin/pricing/wykluczenia         → Admin\Pricing\ExclusionsController
GET  /admin/pricing/ustawienia          → Admin\Pricing\GlobalSettingsController
GET  /admin/pricing/tester              → Admin\Pricing\PriceTesterController@index
POST /admin/pricing/tester/oblicz       → Admin\Pricing\PriceTesterController@calculate
```

### 10.5. API (api.php, Sanctum)

```
# AJAX sklepu (session-based, bez tokenu)
POST /api/shop/price                    → Api\Shop\PriceController@calculate
GET  /api/shop/search                   → Api\Shop\SearchController@search
POST /api/shop/cart/estimate-shipping   → Api\Shop\CartController@estimateShipping

# Design Editor (session-based lub user token)
POST /api/design/{design}/save          → Api\Design\DesignController@save
POST /api/design/{design}/export        → Api\Design\DesignController@export
GET  /api/design/{design}/versions      → Api\Design\DesignController@versions
POST /api/design/assets/upload          → Api\Design\AssetsController@upload
GET  /api/design/templates              → Api\Design\TemplatesController@index
GET  /api/design/fonts                  → Api\Design\FontsController@index

# B2B API (Sanctum token)
GET  /api/v1/orders                     → Api\V1\OrdersController@index
POST /api/v1/orders                     → Api\V1\OrdersController@store
GET  /api/v1/orders/{order}             → Api\V1\OrdersController@show
POST /api/v1/pricing/calculate          → Api\V1\PricingController@calculate
GET  /api/v1/products                   → Api\V1\ProductsController@index
GET  /api/v1/products/{product}/config  → Api\V1\ProductsController@config

# GUS (server-to-server via panel)
POST /api/gus/lookup                    → Api\GusController@lookup
```

### 10.6. Webhooks (webhooks.php, bez CSRF)

```
POST /webhooks/p24/confirm              → Webhooks\P24Controller@confirm
POST /webhooks/payu/notify              → Webhooks\PayUController@notify
POST /webhooks/inpost/tracking          → Webhooks\InPostController@tracking
POST /webhooks/dpd/tracking             → Webhooks\DpdController@tracking
POST /webhooks/dhl/tracking             → Webhooks\DhlController@tracking
POST /webhooks/smsapi/status            → Webhooks\SmsApiController@status
POST /webhooks/tus/pre-finish           → Webhooks\TusController@preFinish
POST /webhooks/tus/post-finish          → Webhooks\TusController@postFinish
```

### 10.7. Broadcasting (channels.php)

```php
Broadcast::channel('user.{id}', fn($user, $id) => $user->id === (int)$id);
Broadcast::channel('order.{id}', fn($user, $id) => $user->can('view', Order::find($id)));
Broadcast::channel('kanban', fn($user) => $user->hasPermission('view-kanban'));
Broadcast::channel('design.{id}', fn($user, $id) => $user->can('view', Design::find($id)));
```

### 10.8. Scheduled jobs (console.php)

```php
Schedule::command('horizon:snapshot')->everyFiveMinutes();
Schedule::job(new RecalculateLoyaltyTiersJob)->daily()->at('02:00');
Schedule::job(new SendAbandonedCartEmailsJob)->hourly();
Schedule::job(new CleanupExpiredPriceCacheJob)->everyTenMinutes();
Schedule::job(new CleanupGuestAccountsJob)->daily()->at('03:00');
Schedule::job(new ReconcileSubiektInvoicesJob)->hourly();
Schedule::job(new SyncInventoryFromSubiektJob)->everySixHours();
Schedule::command('backup:run')->daily()->at('01:00');
Schedule::job(new GenerateDailyReportsJob)->daily()->at('06:00');
```

---

## CZĘŚĆ XI — Architektura frontendu (Vue 3 + Inertia + shadcn-vue)

### 11.1. Struktura katalogów `resources/js/`

```
resources/js/
├── app.ts                          # Entry point (createInertiaApp)
├── ssr.ts                          # SSR entry point (pre-render na Node)
├── bootstrap.ts                    # Inicjalizacja Echo, Ziggy, globalne pluginy
├── types/                          # TypeScript definicje wspólne
│   ├── index.ts                    # Główne interfejsy (User, Order, Product, ...)
│   ├── pricing.ts                  # PricingRequest, PricingResult, EngineResult
│   ├── editor.ts                   # Scene, Layer, Page, Asset
│   └── inertia.d.ts                # Rozszerzenia typów Inertia (PageProps)
├── Pages/
│   ├── Shop/                       # Publiczny sklep
│   │   ├── Home.vue
│   │   ├── Category.vue
│   │   ├── Product.vue             # + Partials/ConfiguratorForm.vue
│   │   ├── Cart.vue
│   │   ├── Checkout.vue            # stepper
│   │   └── OrderConfirmation.vue
│   ├── Auth/                       # Login / register / magic link
│   ├── Portal/                     # Portal klienta (pod /moje-konto)
│   │   ├── Dashboard.vue
│   │   ├── Orders/{Index,Show}.vue
│   │   ├── Approvals/{Index,Show}.vue
│   │   ├── Files.vue
│   │   ├── Documents.vue
│   │   ├── Complaints/{Index,Show,Create}.vue
│   │   ├── Designs/{Index,Show}.vue
│   │   ├── Messages.vue
│   │   ├── Addresses.vue
│   │   └── Settings/{Profile,Password,Security,Preferences,Rodo}.vue
│   ├── Editor/
│   │   ├── Index.vue               # /edytor/{id}
│   │   └── NewDesign.vue           # wybór szablonu
│   └── Admin/                      # Panel operatora
│       ├── Dashboard.vue
│       ├── Orders/
│       │   ├── Index.vue           # DataTable
│       │   ├── Kanban.vue          # drag & drop
│       │   ├── Show.vue
│       │   └── Partials/...
│       ├── Clients/{Index,Show,Edit}.vue
│       ├── Pricing/
│       │   ├── Products/{Index,Edit}.vue + Partials/Tab*.vue (8 zakładek)
│       │   ├── Materials/{Index,Edit}.vue
│       │   ├── Parameters/{Index,Edit}.vue
│       │   ├── MediaFormats/{Index,Edit}.vue
│       │   ├── PageConfigs/{Index,Edit}.vue
│       │   ├── Exclusions/{Index,Edit}.vue
│       │   ├── GlobalSettings.vue
│       │   └── PriceTester.vue
│       ├── Production/{Kanban,Machines}.vue
│       ├── Inventory/{Index,Movements}.vue
│       ├── Finance/{Invoices,Payments,Refunds,SubiektLog}.vue
│       ├── Logistics/{Methods,Shipments}.vue
│       ├── Complaints/{Index,Show}.vue
│       ├── Communications/{Conversations,Emails,Templates}.vue
│       ├── Reports/{Dashboard,Conversion,Margin,LTV,...}.vue
│       ├── Settings/{Users,Roles,Integrations}.vue
│       └── Quotes/{Index,Show,Edit}.vue
├── Layouts/
│   ├── ShopLayout.vue              # Sklep + portal (header + footer)
│   ├── PortalLayout.vue            # wrapper: ShopLayout + PortalSidebar
│   ├── AuthLayout.vue              # Centered card (login, register)
│   ├── AdminLayout.vue             # Sidebar + topbar + breadcrumbs
│   ├── EditorLayout.vue            # Full-screen, no header/footer
│   └── EmptyLayout.vue             # Dla landingów specjalnych
├── Components/
│   ├── ui/                         # shadcn-vue (lowercase folders)
│   │   ├── button/
│   │   ├── card/
│   │   ├── dialog/
│   │   ├── form/
│   │   ├── input/
│   │   ├── select/
│   │   ├── table/
│   │   ├── tabs/
│   │   ├── toast/
│   │   └── ... (pełna lista w 11.3)
│   ├── Shop/                       # Komponenty sklepu
│   │   ├── Hero.vue
│   │   ├── CategoryGrid.vue
│   │   ├── FeaturedProducts.vue
│   │   ├── ProductCard.vue
│   │   ├── ProductGallery.vue
│   │   ├── Configurator/
│   │   │   ├── ConfiguratorForm.vue
│   │   │   ├── FormatPicker.vue
│   │   │   ├── MaterialSelect.vue
│   │   │   ├── ParameterField.vue
│   │   │   ├── QuantityInput.vue
│   │   │   ├── LeadtimePicker.vue
│   │   │   ├── PatternInput.vue
│   │   │   ├── PriceDisplay.vue
│   │   │   └── ActionButtons.vue
│   │   ├── Cart/
│   │   │   ├── CartItem.vue
│   │   │   ├── CartSummary.vue
│   │   │   └── PromoCodeInput.vue
│   │   ├── Checkout/
│   │   │   ├── StepContact.vue
│   │   │   ├── StepDelivery.vue
│   │   │   ├── StepInvoice.vue
│   │   │   ├── StepPayment.vue
│   │   │   └── StepSummary.vue
│   │   ├── TestimonialsCarousel.vue
│   │   ├── DesignEditorCTA.vue
│   │   ├── FAQAccordion.vue
│   │   └── BrandLogos.vue
│   ├── Portal/
│   │   ├── PortalSidebar.vue
│   │   ├── DashboardKpi.vue
│   │   ├── OrderStatusBadge.vue
│   │   ├── OrderTimeline.vue
│   │   ├── ApprovalPdfViewer.vue   # pdf.js wrapper
│   │   ├── ApprovalDecisionForm.vue
│   │   ├── RepeatOrderButton.vue
│   │   ├── LoyaltyBadge.vue
│   │   ├── LoyaltyProgressBar.vue
│   │   ├── ConversationList.vue
│   │   ├── MessageBubble.vue
│   │   └── MessageInput.vue
│   ├── Editor/                     # z CZĘŚĆ V § 5.12
│   │   ├── EditorCanvas.vue
│   │   ├── EditorToolbar.vue
│   │   ├── EditorLeftPanel.vue
│   │   ├── EditorRightPanel.vue
│   │   ├── ToolText.vue
│   │   ├── ToolImage.vue
│   │   ├── ToolShape.vue
│   │   ├── ToolTemplate.vue
│   │   ├── PageTabs.vue
│   │   ├── LayerList.vue
│   │   ├── PropertyPanel.vue
│   │   ├── ColorPicker.vue
│   │   ├── FontPicker.vue
│   │   ├── AssetLibrary.vue
│   │   ├── TemplateGallery.vue
│   │   ├── SceneValidatorAlert.vue
│   │   └── PreviewModal.vue
│   ├── Admin/
│   │   ├── AdminSidebar.vue
│   │   ├── AdminTopbar.vue
│   │   ├── DataTable/
│   │   │   ├── DataTable.vue
│   │   │   ├── DataTableColumnHeader.vue
│   │   │   ├── DataTablePagination.vue
│   │   │   ├── DataTableToolbar.vue
│   │   │   └── DataTableFacetedFilter.vue
│   │   ├── Pricing/
│   │   │   ├── ProductTabBasic.vue
│   │   │   ├── ProductTabFormats.vue
│   │   │   ├── ProductTabMaterials.vue
│   │   │   ├── ProductTabParameters.vue
│   │   │   ├── ProductTabCosts.vue
│   │   │   ├── ProductTabMargins.vue
│   │   │   ├── ProductTabPageConfigs.vue
│   │   │   ├── ProductTabExclusions.vue
│   │   │   ├── ExclusionRuleBuilder.vue
│   │   │   └── PriceBreakdownViewer.vue
│   │   ├── Kanban/
│   │   │   ├── KanbanBoard.vue
│   │   │   ├── KanbanColumn.vue
│   │   │   └── KanbanCard.vue
│   │   └── Charts/
│   │       ├── LineChart.vue
│   │       ├── BarChart.vue
│   │       ├── PieChart.vue
│   │       └── FunnelChart.vue
│   ├── Common/
│   │   ├── AppHeader.vue
│   │   ├── AppFooter.vue
│   │   ├── CookieBanner.vue
│   │   ├── LanguageSwitcher.vue
│   │   ├── ThemeToggle.vue
│   │   ├── LoadingSpinner.vue
│   │   ├── EmptyState.vue
│   │   ├── ErrorBoundary.vue
│   │   ├── ConfirmDialog.vue
│   │   ├── PageHeader.vue
│   │   ├── Breadcrumbs.vue
│   │   └── Pagination.vue
│   └── Icons/                      # (używamy lucide-vue-next; tylko wyjątkowo custom SVG)
├── composables/
│   ├── useCart.ts                  # reactive cart (session + server sync)
│   ├── usePricing.ts               # debounced price recalculation
│   ├── useAuth.ts                  # current user, helpers
│   ├── useToast.ts                 # shadcn sonner wrapper
│   ├── useConfirm.ts               # programmatic Confirm Dialog
│   ├── useWebSocket.ts             # Echo channels helpers
│   ├── usePolling.ts               # Inertia polling wrapper
│   ├── useOptimistic.ts            # optimistic updates helper
│   ├── useLocalStorage.ts
│   ├── useDebounce.ts              # re-export lodash
│   ├── useFormatter.ts             # PLN, daty, rozmiar plików
│   └── useFeatureFlags.ts          # read features z PageProps
├── stores/                         # Pinia
│   ├── editor.ts                   # store Design Editora (CZĘŚĆ V § 5.13)
│   ├── cart.ts                     # lokalny cart (przed DB sync)
│   ├── ui.ts                       # sidebar open/close, modals
│   └── notifications.ts            # toasts, browser notifications
├── lib/
│   ├── utils.ts                    # cn() helper (clsx + twMerge)
│   ├── axios.ts                    # instance dla useHttp (Inertia v3)
│   ├── api.ts                      # typed endpoints wrapper (dla /api/*)
│   ├── format.ts                   # formatMoney, formatDate, formatBytes
│   ├── validators.ts               # NIP, REGON, PESEL, kod pocztowy
│   ├── konva-setup.ts              # lazy-load vue-konva + fabric shapes
│   └── echo.ts                     # Laravel Echo init
└── css/
    ├── app.css                     # main — tailwind directives + CSS variables
    └── editor.css                  # specific for /edytor
```

### 11.2. Entry point i konfiguracja

**`resources/js/app.ts`:**

```ts
import { createInertiaApp } from '@inertiajs/vue3'
import { createApp, h, DefineComponent } from 'vue'
import { createPinia } from 'pinia'
import { ZiggyVue } from 'ziggy-js'
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers'
import { setupEcho } from './lib/echo'

createInertiaApp({
  title: (title) => title ? `${title} – Drukarnia XYZ` : 'Drukarnia XYZ',
  resolve: (name) =>
    resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob<DefineComponent>('./Pages/**/*.vue')),
  setup({ el, App, props, plugin }) {
    const pinia = createPinia()
    setupEcho(props.initialPage.props.echo as any)
    createApp({ render: () => h(App, props) })
      .use(plugin)
      .use(pinia)
      .use(ZiggyVue)
      .mount(el)
  },
  progress: { color: '#0f172a', showSpinner: true },
})
```

**`vite.config.ts`:**

```ts
import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'
import vue from '@vitejs/plugin-vue'
import path from 'path'

export default defineConfig({
  plugins: [
    laravel({
      input: ['resources/css/app.css', 'resources/js/app.ts', 'resources/css/editor.css'],
      ssr: 'resources/js/ssr.ts',
      refresh: true,
    }),
    vue({ template: { transformAssetUrls: { base: null, includeAbsolute: false } } }),
  ],
  resolve: { alias: { '@': path.resolve(__dirname, 'resources/js') } },
  build: {
    rollupOptions: {
      output: {
        manualChunks: {
          'editor': ['konva', 'vue-konva'],     // lazy chunk dla /edytor
          'charts': ['chart.js', 'vue-chartjs'],
          'pdf': ['pdfjs-dist'],
        },
      },
    },
  },
})
```

### 11.3. Komponenty shadcn-vue używane w projekcie

Pełna lista zainstalowanych komponentów (installed via `npx shadcn-vue@latest add <name>`):

| Komponent | Użycie |
|-----------|--------|
| `accordion` | FAQ, zagnieżdżone parametry w admin |
| `alert` | Ostrzeżenia walidacyjne, MPP trigger |
| `alert-dialog` | Potwierdzenie usunięcia, akceptacja proof |
| `avatar` | User menu w header |
| `badge` | Status zamówienia, tier lojalnościowy, liczniki |
| `breadcrumb` | Nawigacja w admin, kategorie sklepu |
| `button` | Wszystkie akcje |
| `calendar` | DatePicker dla filtrów raportów |
| `card` | Produkt, zamówienie, metryki KPI |
| `carousel` | Opinie, polecane produkty |
| `checkbox` | Parametry multi, wybór pozycji w reklamacjach |
| `collapsible` | Sekcje w szczegółach zamówienia |
| `combobox` | Wybór materiału, klienta (z search) |
| `command` | Global search Cmd+K |
| `context-menu` | Akcje w Data Table |
| `date-picker` | Zakresy dat w raportach |
| `dialog` | Modale (np. edycja adresu, nowa reklamacja) |
| `dropdown-menu` | User menu, akcje wierszy |
| `form` | Wszystkie formularze (z FormField, FormItem, FormMessage) |
| `hover-card` | Podgląd designu on hover |
| `input` | Tekstowe, numeryczne |
| `input-otp` | 2FA kody |
| `label` | Etykiety pól |
| `menubar` | Admin navbar top |
| `navigation-menu` | Main nav sklepu |
| `number-field` | Nakład, ilość, ceny |
| `pagination` | Listy |
| `popover` | Filtry, tooltips z akcjami |
| `progress` | Progres tier lojalnościowego, upload |
| `radio-group` | Format, typ faktury, termin |
| `resizable` | Paneliki Design Editora |
| `scroll-area` | Długie listy w sidebarach |
| `select` | Kraj, kurier, metoda płatności |
| `separator` | Poziome/pionowe dzielniki |
| `sheet` | Mobile nav drawer, filter drawer |
| `sidebar` | Portal + admin sidebar (z SidebarProvider) |
| `skeleton` | Loading states (deferred props) |
| `slider` | Zoom w edytorze, cena min-max filter |
| `sonner` | Toasts (jedyne źródło notyfikacji inline) |
| `stepper` | Checkout multi-step |
| `switch` | Dark mode, preferencje |
| `table` | DataTable, faktury, pozycje zamówienia |
| `tabs` | Edycja produktu (8 zakładek), ustawienia |
| `textarea` | Opisy, notatki |
| `toggle` + `toggle-group` | Formatowanie tekstu w edytorze |
| `tooltip` | Hints przy ikonach, skrócone opisy |

### 11.4. Wzorce Inertia v3

#### 11.4.1. Forms z `useForm` + shadcn Form

```vue
<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { Form, FormField, FormItem, FormLabel, FormControl, FormMessage } from '@/Components/ui/form'
import { Input } from '@/Components/ui/input'
import { Button } from '@/Components/ui/button'

const form = useForm({
  name: '',
  nip: '',
  email: '',
})

const submit = () => form.post(route('portal.addresses.store'))
</script>

<template>
  <Form @submit="submit">
    <FormField name="name">
      <FormItem>
        <FormLabel>Nazwa</FormLabel>
        <FormControl>
          <Input v-model="form.name" :disabled="form.processing" />
        </FormControl>
        <FormMessage>{{ form.errors.name }}</FormMessage>
      </FormItem>
    </FormField>
    <!-- inne pola -->
    <Button type="submit" :disabled="form.processing">Zapisz</Button>
  </Form>
</template>
```

#### 11.4.2. Deferred props + Skeleton

```vue
<script setup lang="ts">
import { Deferred } from '@inertiajs/vue3'
defineProps<{ orders?: Order[] }>()
</script>

<template>
  <Deferred data="orders">
    <template #fallback>
      <div class="flex flex-col gap-3">
        <Skeleton v-for="i in 5" :key="i" class="h-16 w-full" />
      </div>
    </template>
    <OrdersTable :orders="orders!" />
  </Deferred>
</template>
```

#### 11.4.3. `useHttp` dla standalone AJAX (konfigurator sklepu)

```ts
const { post, data, error, processing } = useHttp<PricingResult>()
await post('/api/shop/price', payload)   // data.value aktualizuje się automatycznie
```

#### 11.4.4. Optimistic updates + automatic rollback (v3)

```ts
router.patch(route('portal.addresses.update', addr.id), { is_default: true }, {
  optimistic: () => { addr.is_default = true },   // rollback automatyczny przy błędzie
})
```

#### 11.4.5. `setLayoutProps` / `useLayoutProps` (v3)

```vue
<!-- PortalLayout.vue -->
<script setup lang="ts">
import { useLayoutProps } from '@inertiajs/vue3'
const { unreadMessages, pendingApprovals } = useLayoutProps<{ unreadMessages: number; pendingApprovals: number }>()
</script>
```

Per-page:
```vue
<script setup>
import { setLayoutProps } from '@inertiajs/vue3'
setLayoutProps({ pageTitle: 'Zamówienia' })
</script>
```

#### 11.4.6. Polling (v2 feature w v3)

Używamy dla Kanban i ApprovalStatus w portalu:

```ts
router.reload({ only: ['orders'], preserveScroll: true })
usePolling(() => router.reload({ only: ['orders'] }), 5000)  // co 5 s
```

Dla real-time krytycznych eventów — preferujemy **Reverb (WebSocket)** przed pollingiem.

### 11.5. Laravel Echo + Reverb (broadcasting)

**`resources/js/lib/echo.ts`:**

```ts
import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

export function setupEcho(cfg: { key: string; host: string; port: number; scheme: string }) {
  window.Pusher = Pusher
  window.Echo = new Echo({
    broadcaster: 'reverb',
    key: cfg.key,
    wsHost: cfg.host,
    wsPort: cfg.port,
    forceTLS: cfg.scheme === 'https',
    enabledTransports: ['ws', 'wss'],
  })
}
```

**Użycie w Pages/Portal/Orders/Show.vue:**

```ts
onMounted(() => {
  window.Echo.private(`order.${props.order.id}`)
    .listen('.OrderStatusChanged', (e: { status: string }) => {
      props.order.status = e.status
      toast({ title: 'Zmiana statusu', description: `Zamówienie jest teraz: ${e.status}` })
    })
})
onBeforeUnmount(() => {
  window.Echo.leave(`order.${props.order.id}`)
})
```

### 11.6. Zarządzanie stanem

| Typ stanu | Narzędzie | Przykład |
|-----------|-----------|----------|
| Stan strony (server source of truth) | **Inertia PageProps** | Zamówienia, produkty |
| Stan formularza | **`useForm`** | Logowanie, tworzenie adresu |
| Efemeryczne AJAX (konfigurator cen) | **`useHttp`** | Cena w czasie rzeczywistym |
| Lokalne stany UI (sidebar, dialog) | **Pinia store `ui`** | Open/close |
| Złożony stan domenowy (edytor) | **Pinia store `editor`** | Scena, undo/redo |
| Koszyk (przed sync z DB) | **Pinia store `cart`** | Guest cart |
| Globalne preferencje (motyw) | **localStorage + VueUse** | Dark mode |

**Zasada:** Nie używamy Vuex; preferujemy ograniczenie Pinii do stanów, które **muszą** być dzielone między route'ami. Stan specyficzny dla strony trzymamy w Inertia PageProps.

### 11.7. Dark mode i motywy

- CSS variables w `app.css` — `:root` + `.dark` (zgodne z shadcn-vue)
- Pinia + localStorage: `useUiStore().theme = 'dark' | 'light' | 'system'`
- `useColorMode` z `@vueuse/core` (auto-wykrywanie preferencji OS)
- Toggle w user menu i na landing

### 11.8. i18n

Stack: `vue-i18n` (lazy-load locale files). W ETAPach 1–3 tylko PL, ale struktura gotowa:

```
resources/lang/
├── pl/
│   ├── common.json
│   ├── shop.json
│   ├── portal.json
│   └── editor.json
├── en/                      # Phase 4
└── uk/                      # Phase 4
```

**W Inertia PageProps**: `locale: 'pl'` → ustawiane przez middleware `SetLocale`. Routing PL jest domyślny (`/kategoria/wizytowki`), EN/UK z prefixem (`/en/category/business-cards`).

### 11.9. Accessibility (WCAG 2.1 AA)

Wymagania:
- **Kontrast** minimum 4.5:1 dla tekstu (shadcn-vue spełnia)
- **Keyboard navigation** — wszystkie interaktywne elementy dostępne z Tab; Escape zamyka modale (radix-vue)
- **Screen readers** — `aria-label` na ikonach-buttonach, `aria-live="polite"` dla toastów, `role="status"` dla loaderów
- **Focus visible** — outline widoczny (shadcn uses `:focus-visible:ring`)
- **Form labels** — każdy input musi mieć `FormLabel`, nigdy samo placeholder
- **Skip links** — „Przejdź do głównej treści" na pierwszym Tab
- **Color not sole indicator** — błędy walidacji też z ikoną lub tekstem (nie tylko czerwony)

### 11.10. Performance budget

| Metryka | Cel | Pomiar |
|---------|-----|--------|
| LCP (Largest Contentful Paint) | < 2.5 s | Lighthouse, Web Vitals lib |
| INP (Interaction to Next Paint) | < 200 ms | tamze |
| CLS (Cumulative Layout Shift) | < 0.1 | tamze |
| Bundle main `app.js` | < 250 KB (gz) | `vite-bundle-analyzer` |
| Bundle `editor.js` (lazy) | < 350 KB (gz) | tamze |
| TTFB produktowych stron (SSR) | < 400 ms | Pulse, server timing |
| Liczba req. na stronę | < 30 | Network tab |

**Strategie:**
- Manual chunks (editor, charts, pdf)
- Preload HERO obrazu (`<link rel="preload">`)
- `loading="lazy"` dla obrazów poniżej fold
- `fetchpriority="high"` dla LCP image
- `font-display: swap` dla Inter + self-host WOFF2
- Service Worker dla offline dashboard (Phase 4)

---

## CZĘŚĆ XII — Bezpieczeństwo i zgodność z prawem

### 12.1. Model zagrożeń (STRIDE, uproszczony)

| Zagrożenie | Przykład | Mitygacja |
|------------|----------|-----------|
| **S**poofing (podszycie) | Kradzież sesji klienta | Sanctum SPA tokens, SameSite=Lax, HttpOnly cookies, 2FA dla operatorów |
| **T**ampering (modyfikacja) | Modyfikacja koszyka po stronie klienta | Re-run pipeline'u przy checkout; porównanie cen; signed URLs dla plików |
| **R**epudiation (wyparcie) | Klient twierdzi „nie zaakceptowałem pliku" | `design_approvals` z IP, user_agent, SHA256 pliku, timestamp, dwa checkboxy |
| **I**nformation disclosure | Wyciek cenników kontraktowych B2B | Policies per Order/Client; brak cross-client queries w API; rate limit |
| **D**enial of service | Flood konfiguratora sklepu | Throttle `/api/shop/price` 20/min/IP + 60/min/user; fail2ban na login |
| **E**levation of privilege | Klient → operator | spatie/laravel-permission; middleware guards per route |

### 12.2. Authentication & Authorization

#### 12.2.1. Konta

- **Hasła**: bcrypt (cost 12); minimum 10 znaków; zxcvbn estimator (client-side) + `min:10` + custom rule Password::defaults()
- **Rate limit logowania**: 5 prób / 1 min / IP + email; po 5 blokada 15 min (Fortify)
- **2FA (TOTP)**: Google Authenticator / Authy przez `pragmarx/google2fa-laravel`; obowiązkowe dla roli `owner`, `manager`, `accountant`; opcjonalne dla klientów
- **Magic links**: 7 dni TTL, jednorazowe, `Str::random(64)`
- **Session lifetime**: 120 min idle timeout dla operatora; 30 dni „remember me" dla klienta
- **Session rotation**: po zalogowaniu, po 2FA, po zmianie hasła

#### 12.2.2. Role i Permissions (spatie/laravel-permission)

| Rola | Główne permissions |
|------|--------------------|
| `owner` | `*` (wszystko) |
| `manager` | zarządzanie produktami, cenami, klientami, raportami |
| `operator` | tworzenie zamówień, zarządzanie klientami, komunikacja |
| `designer` | edycja designów, pre-flight, akceptacje |
| `production_lead` | Kanban, przypisanie maszyn, oznaczanie statusów |
| `accountant` | faktury, Subiekt, raporty finansowe |
| `client` | portal (tylko własne zasoby) |

**Permissions** (przykłady):
- `orders.view.all` / `orders.view.own`
- `orders.create`, `orders.transition.to_production`
- `pricing.edit.products`, `pricing.edit.global`
- `clients.view.all`, `clients.pricing.override`
- `reports.view.financial`, `reports.view.operational`
- `admin.access`, `integrations.manage`

#### 12.2.3. Policies

Każdy model ma odpowiadającą Policy w `app/Policies/`:
- `OrderPolicy@view($user, Order $order)` → operator wszystko, klient tylko gdy `$order->client_id === $user->client_id`
- `DesignPolicy@download` → signed URL tylko dla właściciela + operatorów
- `InvoicePolicy@download` → jw.

### 12.3. Transport + Storage

- **TLS 1.3** only; HSTS (`max-age=31536000; includeSubDomains; preload`)
- **Secure cookies**: `Secure`, `HttpOnly`, `SameSite=Lax`
- **CSP** (Content Security Policy): `default-src 'self'; script-src 'self' 'nonce-{nonce}'; img-src 'self' data: https://minio.drukarnia.pl; connect-src 'self' wss://reverb.drukarnia.pl; style-src 'self' 'unsafe-inline';`
- **CORS**: `config/cors.php` — tylko `https://drukarnia.pl`, `https://admin.drukarnia.pl`
- **Nagłówki**: `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy: strict-origin-when-cross-origin`

### 12.4. Szyfrowanie danych

- **At rest (DB)**: PostgreSQL disk encryption (LUKS); niektóre pola szyfrowane w kolumnach (`clients.pesel`, `users.two_factor_secret`) przez `encrypted` cast Eloquent
- **At rest (MinIO)**: SSE-S3 server-side encryption
- **In transit**: TLS 1.3 wszędzie (backend ↔ DB też przez SSL)
- **API keys**: `integration_credentials` z `Crypt::encrypt()` (wymaga APP_KEY w vault)

### 12.5. Bezpieczeństwo plików

- **Typy dozwolone**: PDF, PNG, JPG, SVG (dla assets), TTF/WOFF2 (dla fontów premium)
- **MIME sniffing**: `getClientMimeType()` + `finfo_file()` cross-check
- **Rozmiar**: max 200 MB (client files), 10 MB (assets), 2 MB (avatar)
- **Ścieżki**: nigdy nie ujawniane klientowi; dostęp tylko przez signed URLs (TTL 1 h)
- **Filename sanitization**: `Str::slug` + random suffix; original filename w `files.original_name`

### 12.6. Ochrona przed OWASP Top 10

| # | Zagrożenie | Mitygacja |
|---|------------|-----------|
| A01 | Broken Access Control | Policies, middleware guards, zasada least privilege |
| A02 | Cryptographic Failures | bcrypt, TLS 1.3, encrypted casts, signed URLs |
| A03 | Injection | Eloquent ORM + prepared statements; FormRequest validation; NEVER raw SQL z user input |
| A04 | Insecure Design | Threat modeling (ten dokument); secure-by-default (Laravel) |
| A05 | Security Misconfiguration | `APP_DEBUG=false` prod; error pages generic; `php artisan config:cache` |
| A06 | Vulnerable Components | `composer audit`, `npm audit`; dependabot weekly |
| A07 | Auth Failures | Fortify, 2FA dla admin, rate limit, generic "invalid creds" errors |
| A08 | Software and Data Integrity | subresource integrity dla CDN; signed artifacts dla deploy |
| A09 | Logging and Monitoring | Laravel Log + activity_log; Pulse; Uptime Kuma; Sentry (Phase 4) |
| A10 | SSRF | Walidacja URL w integracjach (whitelist); Guzzle z fixed base URL |

### 12.7. Rate limiting

```php
// bootstrap/app.php lub RouteServiceProvider
RateLimiter::for('shop-pricing', fn($req) => Limit::perMinute(20)->by($req->ip()));
RateLimiter::for('login', fn($req) => Limit::perMinute(5)->by($req->ip() . '|' . $req->input('email')));
RateLimiter::for('api-b2b', fn($req) => Limit::perMinute(120)->by($req->user()?->id ?: $req->ip()));
RateLimiter::for('webhooks', fn($req) => Limit::perMinute(600)->by($req->ip()));
RateLimiter::for('password-reset', fn($req) => Limit::perMinute(3)->by($req->input('email')));
```

### 12.8. RODO / GDPR

#### 12.8.1. Zasady przetwarzania

- **Podstawa prawna**: art. 6 ust. 1 lit. b (umowa) dla zamówień; lit. a (zgoda) dla newslettera; lit. f (uzasadniony interes) dla marketingu własnych produktów do istniejących klientów
- **Cele**: realizacja zamówień, księgowość (ustawa 5 lat), marketing (za zgodą), analityka (pseudonimizowana)
- **Retencja**:
  - Zamówienia i faktury: 5 lat od końca roku podatkowego (ustawa o rachunkowości)
  - Dane użytkowników bez zamówień (guest): 90 dni
  - Logi serwerowe: 6 miesięcy
  - ActivityLog: 24 miesiące
  - Sesje: 1 rok

#### 12.8.2. Prawa podmiotu danych

- **Dostęp** (art. 15): `/moje-konto/ustawienia → RODO → Pobierz moje dane` — eksport JSON z wszystkich powiązanych rekordów (orders, files, messages, invoices). Przygotowanie w 72 h przez job, email z linkiem (signed URL, 7 dni ważny)
- **Sprostowanie** (art. 16): edycja w profilu; audit w `activity_log`
- **Usunięcie** (art. 17): „Usuń konto" → 30 dni grace period, potem:
  - Jeśli nie ma aktywnych zamówień: hard delete
  - Jeśli są faktury/zamówienia: anonimizacja (imię, email, telefon → hash), zachowanie orders dla księgowości
- **Przenoszalność** (art. 20): eksport w JSON (jw.)
- **Sprzeciw** (art. 21): unsubscribe link w każdym marketingowym emailu

#### 12.8.3. Umowy powierzenia (DPA)

Podpisane z:
- MinIO (self-hosted — brak)
- P24, PayU, InPost, DPD, DHL, SMSAPI.pl, OpenAI — wymagane DPA w ramach integracji
- Backblaze B2 (backup) — DPA + enkrypcja client-side przed wysłaniem

#### 12.8.4. Rejestr czynności przetwarzania (RCPD)

Pliki w `docs/rodo/`:
- `rcpd.md` — rejestr czynności (art. 30 RODO)
- `polityka-prywatnosci.md` — treść publikowana na `/polityka-prywatnosci`
- `regulamin.md` — `/regulamin`
- `cookie-policy.md`

#### 12.8.5. Cookie banner

- Banner shadcn-vue z 3 opcjami: „Tylko niezbędne" / „Dostosuj" / „Akceptuj wszystkie"
- Kategorie: niezbędne (zawsze), analityka (GA4), marketing (FB Pixel, Google Ads), preferencje
- Preferencje zapisywane w cookie `cookie_consent_v1` (365 dni); serwer odczytuje przez middleware `CookieConsent`
- Bez zgody analityka/marketing NIE jest ładowany (Google Tag Manager Consent Mode v2)

### 12.9. Zgodność z ustawą o rachunkowości i VAT

- **Numeracja faktur**: ciągła, niepowtarzalna, format `FV/{YYYY}/{NNNN}`, `PF/{YYYY}/{NNNN}`, `FZ/{YYYY}/{NNNN}`, `KOR/{YYYY}/{NNNN}`
- **Przechowywanie PDF faktur**: MinIO + backup (5 lat + bieżący)
- **JPK_VAT**: eksport z Subiekta (nie generujemy sami)
- **KSeF**: od 2026-07-01 obowiązkowy dla B2B; integracja via Subiekt (Subiekt wysyła do KSeF, my trzymamy UUID odpowiedzi)
- **MPP (Mechanizm Podzielonej Płatności)**: automatycznie dla faktur B2B >15 000 PLN brutto z załącznikiem 15 ustawy VAT; oznaczenie na fakturze + w opisie przelewu
- **Biała lista VAT**: weryfikacja rachunków bankowych kontrahentów przed przelewem (przez Subiekt lub API MF)

### 12.10. PCI DSS (płatności kartą)

- **Nie przechowujemy danych kart** — full redirect/hosted fields P24 i PayU
- **P24**: tokenizacja po stronie P24, webhook z szyfrowanym payloadem; weryfikacja SHA384 signature
- **Scope reduction**: Laravel nigdy nie ma dostępu do `card_number`, `cvv`

### 12.11. Audit log (spatie/laravel-activitylog)

Rejestrowane zdarzenia:
- Modyfikacje: `orders`, `clients`, `invoices`, `products`, `parameter_options`, `client_pricing_overrides`, `users`, `roles`
- Akcje: login, logout, 2fa enabled/disabled, password changed, email changed
- Event log: wszystkie domain events z properties `subject`, `causer_id`, `ip`, `user_agent`

Partycjonowane po miesiącach; retencja 24 mc; eksport CSV dla audytu.

### 12.12. Monitoring i incident response

- **Laravel Pulse** — performance metrics, slow queries, queue health
- **Horizon Dashboard** — failed jobs, throughput
- **Uptime Kuma** — health checks (HTTPS, DB, Redis, MinIO, Reverb)
- **Log aggregation** — codziennie plik log + rotate (laravel-telescope w dev)
- **Alerts** — email + Slack dla:
  - Failed job rate > 5 %
  - Response time p99 > 2 s przez > 5 min
  - 5xx errors rate > 1 %
  - Disk usage > 85 %
  - Horizon died

**Procedura IR**:
1. Alert → on-call (właściciel lub dev)
2. Potwierdzenie incydentu (false positive / real)
3. Mitygacja (rollback, scale, restart)
4. Post-mortem w 48 h w `docs/incidents/`
5. Jeśli wyciek danych osobowych → zgłoszenie do UODO w ciągu 72 h (art. 33 RODO)

---

## CZĘŚĆ XIII — Strategia testowania

### 13.1. Piramida testów

```
         ╱╲
        ╱E2╲          ~5 %   Playwright / Dusk (top workflows)
       ╱────╲
      ╱ Feat ╲         ~25 %  PHPUnit Feature (HTTP endpoints, jobs)
     ╱────────╲
    ╱   Unit   ╲       ~70 %  PHPUnit Unit + Vitest (pure logic)
   ╱────────────╲
```

### 13.2. Backend — PHPUnit

#### 13.2.1. Unit tests (`tests/Unit/`)

**Kluczowe zasięgi (obligatoryjne 90 %+ coverage):**

- `tests/Unit/Pricing/Engines/SheetEngineTest.php`
  - `test_computes_yield_per_sheet_for_a5_on_b2_sheet`
  - `test_rounds_up_sheets_for_non_divisible_quantity`
  - `test_multiplies_by_pattern_count`
  - `test_adds_waste_sheets`
  - `test_handles_double_sided_via_page_config`
- `tests/Unit/Pricing/Engines/LinearMeterEngineTest.php`
  - `test_computes_meters_for_rollup_on_1067mm_roll`
  - `test_handles_multiple_products_per_width`
  - `test_orientation_switching`
- `tests/Unit/Pricing/Engines/PieceEngineTest.php`
  - `test_adds_waste_pieces`
- `tests/Unit/Pricing/PricingPipelineTest.php`
  - `test_all_11_steps_execute_in_order`
  - `test_pre_margin_costs_summed_correctly`
  - `test_margin_threshold_applied`
  - `test_pattern_multiplier_applied`
  - `test_post_margin_prices_bypass_margin`
  - `test_time_multiplier_applied`
  - `test_loyalty_overlay_applied_last`
  - `test_client_override_replaces_margin`
  - `test_result_is_deterministic`
- `tests/Unit/Pricing/ExclusionResolverTest.php`
  - `test_blocks_invalid_combination`
  - `test_disables_option_flag`
  - `test_modifies_cost_price`
  - `test_evaluates_and_or_logic`
  - `test_priority_order_respected`
- `tests/Unit/Pricing/MarginCalculatorTest.php`
- `tests/Unit/Orders/States/OrderStateTransitionTest.php`
- `tests/Unit/DesignEditor/SceneValidatorTest.php`
- `tests/Unit/Shop/Cart/CartTotalsTest.php`
- `tests/Unit/Shop/Checkout/MppRequiredTest.php`
- `tests/Unit/Finance/InvoiceNumberingTest.php`

#### 13.2.2. Feature tests (`tests/Feature/`)

- `tests/Feature/Admin/Pricing/ProductCrudTest.php`
- `tests/Feature/Admin/Pricing/PriceTesterTest.php`
- `tests/Feature/Shop/CategoryBrowsingTest.php`
- `tests/Feature/Shop/ProductPageTest.php`
- `tests/Feature/Shop/ConfiguratorPriceApiTest.php`
- `tests/Feature/Shop/CartCrudTest.php`
- `tests/Feature/Shop/CheckoutFlowTest.php`
  - `test_guest_can_complete_checkout`
  - `test_logged_user_has_prefilled_data`
  - `test_b2b_can_lookup_nip_from_gus`
  - `test_mpp_auto_enabled_above_threshold`
- `tests/Feature/Shop/MagicLinkActivationTest.php`
- `tests/Feature/Portal/DashboardAccessTest.php`
- `tests/Feature/Portal/OrderRepeatTest.php`
- `tests/Feature/Portal/ApprovalAcceptTest.php`
- `tests/Feature/Portal/ApprovalRejectTest.php`
- `tests/Feature/Portal/ComplaintCreateTest.php`
- `tests/Feature/Editor/DesignSaveTest.php`
- `tests/Feature/Editor/DesignExportPdfTest.php`
- `tests/Feature/Webhooks/P24ConfirmTest.php`
- `tests/Feature/Webhooks/InPostTrackingTest.php`
- `tests/Feature/Api/V1/OrdersApiTest.php`
- `tests/Feature/Jobs/GenerateInvoiceJobTest.php`
- `tests/Feature/Jobs/AbandonedCartRecoveryTest.php`

#### 13.2.3. Faktorie i seedery

```
database/factories/
├── UserFactory.php (z states: guest(), operator(), manager(), client())
├── ClientFactory.php (z states: b2b(), b2c(), gold(), platinum())
├── ProductFactory.php (z states: sheet(), linearMeter(), piece())
├── OrderFactory.php (z states: new(), production(), completed())
├── DesignFactory.php
├── MaterialFactory.php
├── ParameterFactory.php
├── ParameterOptionFactory.php
└── ...
```

**Seedery do testów E2E**:
- `database/seeders/TestingBundleSeeder.php` — komplet: 10 produktów, materiały, parametry, 5 klientów (B2B+B2C), zamówienia w różnych statusach

#### 13.2.4. Testowa konfiguracja

- **DB**: SQLite in-memory dla unit + feature (szybkie); PostgreSQL dla wolniejszego zestawu CI (`phpunit.integration.xml`)
- **Queue**: `sync` driver w testach (jobs wykonywane synchronicznie)
- **Mail**: `Mail::fake()`, `Notification::fake()`
- **HTTP**: `Http::fake()` dla Guzzle (Subiekt, GUS, P24, kurierzy)
- **Storage**: `Storage::fake('s3')`
- **Events**: `Event::fake([SpecificEvent::class])` — nie fake wszystkiego, bo listenery często są częścią logiki pipeline'u

### 13.3. Frontend — Vitest

```
resources/js/__tests__/
├── composables/
│   ├── useCart.test.ts
│   ├── usePricing.test.ts
│   └── useFormatter.test.ts
├── stores/
│   ├── editor.test.ts
│   └── cart.test.ts
├── lib/
│   ├── utils.test.ts (cn helper)
│   └── validators.test.ts (NIP, REGON)
└── components/
    ├── Shop/Configurator/ConfiguratorForm.test.ts
    ├── Shop/Cart/CartSummary.test.ts
    └── Editor/SceneValidatorAlert.test.ts
```

**Vue Test Utils + Vitest** (nie Jest).

### 13.4. E2E — Playwright (preferred) lub Dusk

**Scenariusze krytyczne (must pass przed prod deploy):**

- `e2e/shop/guest-checkout.spec.ts` — scenariusz 8.1 (guest kupuje wizytówki z edytora)
- `e2e/shop/logged-b2b-contract-discount.spec.ts` — scenariusz 8.2
- `e2e/portal/accept-approval.spec.ts`
- `e2e/portal/repeat-order.spec.ts`
- `e2e/admin/create-manual-order.spec.ts`
- `e2e/admin/configure-product-pricing.spec.ts`
- `e2e/admin/price-tester-breakdown.spec.ts`
- `e2e/editor/business-card-from-template.spec.ts`

**Konfiguracja:**
- Playwright z projektami: `chromium`, `firefox`, `webkit`
- Docker compose dla test env (PostgreSQL, Redis, MinIO, Reverb)
- GitHub Actions matrix: PHP 8.3, Node 20; run unit → feature → E2E

### 13.5. Visual regression testing (Phase 4)

- **Percy.io** lub **Chromatic** dla shadcn-vue komponentów w Storybooku
- Snapshot testy dla głównych layoutów (shop home, product page, admin dashboard)
- Diff review w PR checks

### 13.6. Load testing

**Narzędzie:** k6 (Grafana).

Scenariusze:
- 100 virtual users przez 10 min na `/produkt/*` + `/api/shop/price`
- 20 VU przez 5 min na `/moje-konto/zamowienia`
- Spike test: 500 VU przez 1 min

**SLA**:
- p95 response time < 500 ms
- error rate < 0.5 %
- Horizon wait time < 2 s

### 13.7. CI/CD pipeline

```yaml
# .github/workflows/ci.yml
name: CI

on: [push, pull_request]

jobs:
  lint:
    - php: 8.3
    - run: composer install
    - run: vendor/bin/pint --test --format=agent
    - run: npm ci
    - run: npm run lint
    - run: npm run typecheck

  unit:
    - run: php artisan test --testsuite=Unit --compact

  feature:
    services: [postgres, redis, minio]
    - run: php artisan migrate --force
    - run: php artisan test --testsuite=Feature --compact

  vitest:
    - run: npm run test

  e2e:
    services: [postgres, redis, minio, reverb]
    - run: php artisan migrate:fresh --seed --force
    - run: npm run build
    - run: npx playwright install
    - run: npm run test:e2e

  security:
    - run: composer audit
    - run: npm audit --production
```

### 13.8. Cele pokrycia

| Warstwa | Cel coverage |
|---------|-------------|
| `app/Domain/Pricing/**` | ≥ 95 % (krytyczna logika) |
| `app/Domain/Orders/**` | ≥ 90 % |
| `app/Domain/DesignEditor/**` | ≥ 80 % |
| `app/Http/Controllers/**` | ≥ 75 % |
| `app/Jobs/**` | ≥ 80 % |
| Vue components | ≥ 60 % (tylko logika composition API) |

---

## CZĘŚĆ XIV — Roadmap fazowy

### 14.1. Harmonogram wysokiego poziomu

| Faza | Zakres | Czas | Kamienie milowe |
|------|--------|------|-----------------|
| **ETAP 0** | Fundament: stack setup, auth, baza, CI | 2 tyg | App boots, login, health checks |
| **ETAP 1** | Foundation biznesowa: CRM, Orders (manual), Files, podstawowy Finance | 4 tyg | Operator może stworzyć zamówienie ręcznie, wystawić FV |
| **ETAP 2** | Pricing Engine (MODUŁ 4) + Product Management + Quotes + Kanban | 6 tyg | C1 osiągnięty: wycena < 90 s; C3 osiągnięty: Kanban |
| **ETAP 3** | Shop (MODUŁ 17) + Portal + Design Editor (MODUŁ 18) + Payments | 8 tyg | C2, C4, C5, C6, C7 osiągnięte |
| **ETAP 4** | AI preflight, KSeF, logistyka, raporty BI, 2FA mandatory, mobile PWA | 6 tyg | C8 osiągnięty |

**Łącznie:** ~26 tygodni (6 miesięcy) do pełnego wdrożenia.

### 14.2. ETAP 0 — Fundament (2 tyg)

#### 14.2.1. Cele
- Laravel 11 + Vue 3 + Inertia v3 + Tailwind + shadcn-vue boots
- PostgreSQL 16 + Redis + MinIO running (docker-compose dla dev)
- CI/CD pipeline (GitHub Actions)
- Fortify auth (login, register, password reset)
- Podstawowy layout sklepu i admina
- Horizon + Reverb + Pulse

#### 14.2.2. Tickety
- `ERP-001` Scaffold Laravel + Inertia + Vue
- `ERP-002` Zainstaluj shadcn-vue, Tailwind config, dark mode
- `ERP-003` Docker compose: pgsql, redis, minio, reverb, meili
- `ERP-004` Fortify: routes, views, 2FA prep
- `ERP-005` Role+permission z spatie; seed owner/operator/client
- `ERP-006` Layouts: ShopLayout, AdminLayout, AuthLayout, EditorLayout
- `ERP-007` Ziggy config, useHttp setup
- `ERP-008` Echo + Reverb init
- `ERP-009` GitHub Actions: lint, unit, feature
- `ERP-010` Backup script (pg_dump → B2)

#### 14.2.3. Deliverables
- `/` (landing z placeholder), `/zaloguj`, `/admin` dostępne
- Wszystkie composer require + npm install udokumentowane w `README.md`
- Health check endpoint `/health` zwraca status DB/Redis/MinIO

### 14.3. ETAP 1 — Foundation biznesowa (4 tyg)

#### 14.3.1. Tickety

**CRM (MODUŁ 1):**
- `ERP-101` Migracje: `clients`, `addresses`
- `ERP-102` Models + factories + seeders
- `ERP-103` Admin CRUD klientów (DataTable + Dialog Edit)
- `ERP-104` Integracja GUS (lookup NIP → auto-fill)

**Orders core (MODUŁ 2):**
- `ERP-110` Migracje: `orders`, `order_items`, `order_status_history`, `order_notes`
- `ERP-111` State machine (16 stanów)
- `ERP-112` Admin: lista zamówień (DataTable)
- `ERP-113` Admin: tworzenie ręcznego zamówienia (bez jeszcze Web2Print — tylko „cena ręczna")
- `ERP-114` Admin: podgląd szczegółów zamówienia, notatki, załączniki
- `ERP-115` Transitions wraz z policies (kto może w jaki stan)

**Files/DAMS (MODUŁ 7):**
- `ERP-120` Migracja: `files`, `file_conversions`
- `ERP-121` tus.io endpoint + MinIO integration
- `ERP-122` Ghostscript + ImageMagick dla miniatur
- `ERP-123` Signed URL generation

**Finance v1 (MODUŁ 10) — podstawy:**
- `ERP-130` Migracje: `invoices`, `invoice_items`, `payments`
- `ERP-131` Numeracja ciągła faktur (locked sequences)
- `ERP-132` mPDF generator Proforma, FV
- `ERP-133` Ręczne wystawianie faktury z zamówienia

#### 14.3.2. Deliverables
- Operator może: dodać klienta, stworzyć ręcznie zamówienie, dodać plik, wystawić proforma i FV
- Brak jeszcze: sklepu, Web2Print pricing, portalu, edytora

### 14.4. ETAP 2 — Pricing Engine + Product Management (6 tyg)

#### 14.4.1. Tickety

**Schema pricing (17 tabel + overlays):**
- `ERP-201` Migracje: `global_settings`, `media_formats`, `materials`, `parameters`, `parameter_options`, `page_configs`
- `ERP-202` Migracje: `products`, `product_margins`, `product_costs`, `product_formats`, `product_materials`, `product_parameters`, `product_parameter_options`, `product_page_configs`
- `ERP-203` Migracje: `exclusion_rules`, `exclusion_conditions`, `exclusion_actions`
- `ERP-204` Migracje: `client_pricing_overrides`, `loyalty_tiers`, `order_cogs_breakdown`, `price_cache`
- `ERP-205` Models + relations + factories

**Domain — Pricing:**
- `ERP-210` DTOs: `PricingRequest`, `PricingResult`, `PricingContext`, `EngineResult`, `ClientContext`
- `ERP-211` Interface `PricingEngine` + `EngineResolver`
- `ERP-212` `SheetEngine` + `ImpositionCalculator` + unit tests
- `ERP-213` `LinearMeterEngine` + `RollSelectorService` + unit tests
- `ERP-214` `PieceEngine` + unit tests
- `ERP-215` `PricingPipeline` 11 kroków + tests
- `ERP-216` `ExclusionResolver` + tests
- `ERP-217` `MarginCalculator`, `PatternMultiplierService`
- `ERP-218` Overlays: `LoyaltyResolver`, `ClientPricingOverrideResolver`
- `ERP-219` `PriceCache` + cron cleanup
- `ERP-220` `CostOfGoodsCalculator` (RKW shadow)

**Admin UI pricing:**
- `ERP-230` Pages/Admin/Pricing/Products/{Index,Edit}.vue + 8 tabów
- `ERP-231` Pages/Admin/Pricing/Materials/*
- `ERP-232` Pages/Admin/Pricing/Parameters/*
- `ERP-233` Pages/Admin/Pricing/MediaFormats/*
- `ERP-234` Pages/Admin/Pricing/PageConfigs/*
- `ERP-235` Pages/Admin/Pricing/Exclusions/* (rule builder)
- `ERP-236` Pages/Admin/Pricing/GlobalSettings.vue
- `ERP-237` Pages/Admin/Pricing/PriceTester.vue + breakdown viewer

**Orders — integracja z pricing:**
- `ERP-240` `CalculatePriceAction`
- `ERP-241` Admin order edit: zmiana parametrów → recalc
- `ERP-242` `orders.price_breakdown` + migracja ALTER

**Quotes (MODUŁ 5):**
- `ERP-250` Migracje + models
- `ERP-251` Generator wyceny PDF
- `ERP-252` Publiczny link akceptacji (magic token)
- `ERP-253` Convert quote → order

**Production Kanban (MODUŁ 8):**
- `ERP-260` `machines` migracja + CRUD admin
- `ERP-261` Kanban board UI (drag&drop z vue-draggable-plus)
- `ERP-262` Przypisanie zamówienia do maszyny
- `ERP-263` Production timer (start/stop)
- `ERP-264` RKW recalculation po zakończeniu

#### 14.4.2. Kamienie milowe
- Full pricing pipeline działa, tester w admin zwraca breakdown
- Cel **C1** osiągnięty: operator robi wycenę < 90 s
- Cel **C3** osiągnięty: Kanban z maszynami

### 14.5. ETAP 3 — Shop + Portal + Design Editor + Payments (8 tyg)

#### 14.5.1. Tickety

**Shop foundation:**
- `ERP-301` Migracje: `carts`, `cart_items`, `promo_codes`, `promo_code_usages`, `magic_links`
- `ERP-302` Shop routes + controllers (public)
- `ERP-303` Pages/Shop/Home.vue (hero, CategoryGrid, FeaturedProducts)
- `ERP-304` Pages/Shop/Category.vue z filtrami Meilisearch
- `ERP-305` Pages/Shop/Product.vue + ConfiguratorForm z real-time pricing

**Cart + Checkout:**
- `ERP-310` Cart service (session guest + DB logged)
- `ERP-311` Pages/Shop/Cart.vue
- `ERP-312` Pages/Shop/Checkout.vue (stepper, 5 kroków)
- `ERP-313` ShippingRateCalculator
- `ERP-314` CreateOrderFromCartAction
- `ERP-315` Promo codes engine

**Payments:**
- `ERP-320` P24 adapter + webhook
- `ERP-321` PayU adapter
- `ERP-322` Przelew tradycyjny (pro-forma + 3 dni cancel job)
- `ERP-323` Invoice generation on payment confirmation
- `ERP-324` Refund flow

**Portal:**
- `ERP-330` PortalGuard middleware + PortalLayout
- `ERP-331` PortalSidebar (shadcn Sidebar)
- `ERP-332` Pages/Portal/Dashboard.vue (KPI, last orders)
- `ERP-333` Pages/Portal/Orders/{Index,Show}.vue + RepeatOrderAction
- `ERP-334` Pages/Portal/Approvals/* + `design_approvals` table
- `ERP-335` PDF Viewer wrapper (pdf.js)
- `ERP-336` Pages/Portal/Documents.vue (faktury do pobrania)
- `ERP-337` Pages/Portal/Files.vue
- `ERP-338` Pages/Portal/Complaints/* (+migracje)
- `ERP-339` Pages/Portal/Addresses.vue
- `ERP-340` Pages/Portal/Settings/* (6 tabów)
- `ERP-341` Guest → User conversion (magic link)
- `ERP-342` Loyalty tier recalculation job + badge UI

**Design Editor:**
- `ERP-350` Migracje: `designs`, `design_versions`, `design_templates`, `design_assets`, `design_fonts`
- `ERP-351` Pinia store `editor`
- `ERP-352` Pages/Editor/Index.vue (Konva Stage)
- `ERP-353` Komponenty: Toolbar, LeftPanel, RightPanel, LayerList, PropertyPanel
- `ERP-354` Tools: Text, Image, Shape, Template
- `ERP-355` Asset library (upload, signed URL)
- `ERP-356` Font picker (self-hosted + Google)
- `ERP-357` Template gallery (seeder 20 templates)
- `ERP-358` Autosave API (debounce 5 s)
- `ERP-359` SceneValidator + UI alerts
- `ERP-360` Server-side PDF export (Puppeteer + Ghostscript)
- `ERP-361` Integracja z konfiguratorem sklepu

**Communications (MODUŁ 13):**
- `ERP-370` Migracje `conversations`, `messages`
- `ERP-371` In-app chat (Reverb channels)
- `ERP-372` Email templates (Blade Mailables) — order placed, shipped, approval
- `ERP-373` SMS integration (SMSAPI.pl)

**Logistics (MODUŁ 11):**
- `ERP-380` InPost ShipX adapter
- `ERP-381` DPD, DHL adapters
- `ERP-382` Automat nadania po DONE
- `ERP-383` Tracking webhooks

#### 14.5.2. Kamienie milowe
- **C2**: 70 % B2C wycenianych w sklepie bez operatora
- **C4**: każde zlecenie PRODUCTION ma akceptację pliku
- **C5**: automatyczne faktury
- **C6**: 50 % klientów logowanych w portalu
- **C7**: 20 % wizytówek projektowanych w edytorze

### 14.6. ETAP 4 — AI, KSeF, raporty BI, mobile, 2FA (6 tyg)

- `ERP-401` AI auto-preflight (Vision API) dla PDF-ów
- `ERP-402` Email ingest (IMAP + OpenAI brief extractor)
- `ERP-403` KSeF integration via Subiekt (UUID tracking)
- `ERP-404` Reports BI: Conversion funnel, Margin real vs expected, LTV, Abandoned carts
- `ERP-405` Raporty eksport CSV/XLSX + PDF
- `ERP-406` 2FA mandatory dla roli owner/manager/accountant
- `ERP-407` PWA manifest + Service Worker (offline dashboard)
- `ERP-408` Visual regression tests (Chromatic/Percy)
- `ERP-409` Load testing (k6) + tuning
- `ERP-410` i18n EN (przygotowanie — PL dalej default)

**C8** osiągnięty: 100 % FV B2B >15k PLN z MPP + KSeF.

### 14.7. Dependency install checklist

**Composer (Laravel 11 / PHP 8.3):**

```bash
composer require \
  laravel/fortify \
  laravel/horizon \
  laravel/reverb \
  laravel/sanctum \
  laravel/pulse \
  laravel/pail \
  laravel/mcp \
  inertiajs/inertia-laravel \
  tightenco/ziggy \
  spatie/laravel-permission \
  spatie/laravel-model-states \
  spatie/laravel-activitylog \
  spatie/laravel-sitemap \
  spatie/laravel-medialibrary \
  ankitpokhrel/tus-php \
  mpdf/mpdf \
  openai-php/laravel \
  pragmarx/google2fa-laravel \
  meilisearch/meilisearch-php \
  league/flysystem-aws-s3-v3 \
  propaganistas/laravel-phone

composer require --dev \
  laravel/pint \
  laravel/sail \
  phpunit/phpunit \
  mockery/mockery \
  fakerphp/faker \
  nunomaduro/collision
```

**NPM:**

```bash
npm install \
  @inertiajs/vue3 \
  vue@^3.4 \
  @vitejs/plugin-vue \
  vite \
  typescript \
  tailwindcss@^3 postcss autoprefixer \
  tailwindcss-animate \
  radix-vue reka-ui \
  lucide-vue-next \
  clsx tailwind-merge \
  pinia \
  vee-validate zod @vee-validate/zod \
  chart.js vue-chartjs \
  konva vue-konva \
  pdfjs-dist \
  laravel-echo pusher-js \
  ziggy-js \
  lodash-es \
  date-fns \
  @vueuse/core

npm install -D \
  @types/node \
  vue-tsc \
  vitest @vue/test-utils jsdom \
  @playwright/test \
  eslint @vue/eslint-config-typescript prettier \
  @storybook/vue3-vite   # opcjonalnie

# shadcn-vue CLI
npx shadcn-vue@latest init
# dodaj komponenty z listy CZĘŚĆ XI § 11.3
```

### 14.8. Ryzyka i mitygacja

| Ryzyko | Waga | Mitygacja |
|--------|------|-----------|
| Specyfikacja Web2Print niekompletna na edge case'y | Wysoka | PriceTester jako sandbox + unit testy per engine |
| Performance konfiguratora w sklepie | Średnia | Cache + debounce + partial computation |
| Design Editor — bundle size | Średnia | Lazy chunk (manual `editor`), code-splitting |
| Integracja Subiekt (brak API docs?) | Wysoka | Hexagonal adapter + mock dla testów + fallback do CSV |
| KSeF — nowa regulacja, niestabilne API | Wysoka | Pośrednictwo Subiektu (nie integrujemy bezpośrednio) |
| Migracja danych z obecnego systemu (excel/subiekt) | Wysoka | Osobny command `php artisan erp:import-legacy` z dry-run |
| Szkolenie operatorów | Średnia | Video-walkthrough + dokument „onboarding operatora" |
| Wypadek prawny (reklamacja po 14 dniach) | Niska | `design_approvals` z checksumem PDF + IP + timestamp |

---

## CZĘŚĆ XV — Weryfikacja i kryteria akceptacji

### 15.1. Acceptance Criteria per cel biznesowy

#### C1 — Redukcja czasu wyceny o 90 %
- **Definition of Done**: Pipeline od otwarcia formularza do wyniku wyceny < 90 s (dla 95 % zapytań operatora)
- **Weryfikacja**: PriceTester uruchomiony przez operatora; stoper; 50 zapytań w próbie
- **Automatyzacja**: test k6 scenariusz „operator workflow"; p95 response < 500 ms

#### C2 — Samoobsługa cenowa (≥ 70 % B2C bez operatora)
- **DoD**: Zamówienia z `orders.channel IN ('shop_guest', 'shop_user')` / wszystkie B2C ≥ 70 %
- **Weryfikacja**: raport miesięczny w `/admin/raporty/kanaly`
- **Automatyzacja**: E2E test `guest-checkout.spec.ts` zielony

#### C3 — Centralny Kanban
- **DoD**: 100 % zamówień w stanie PRODUCTION widoczne na `/admin/zamowienia/kanban`
- **Weryfikacja**: SQL check: `Order::where('status', 'PRODUCTION')->count() == KanbanTasks::count()`

#### C4 — Akceptacja pliku z logiem
- **DoD**: Każde zlecenie PRODUCTION ma rekord w `design_approvals` z IP, timestamp, checksum
- **Weryfikacja**: integralność referencyjna (FK + NOT NULL)
- **Automatyzacja**: feature test `ApprovalAcceptTest::test_creates_audit_record`

#### C5 — Automatyczne FV
- **DoD**: 100 % zamówień COMPLETED ma FV wygenerowaną bez akcji księgowej
- **Weryfikacja**: `Order::completed()->whereDoesntHave('invoice')->count() == 0`
- **Automatyzacja**: `GenerateInvoiceJobTest` zielony

#### C6 — Portal (50 % powracających loguje się)
- **DoD**: MAU (monthly active users) portalu / unikalnych klientów z zamówieniem ≥ 50 %
- **Weryfikacja**: Pulse User Activity dashboard

#### C7 — Design Editor (20 % wizytówek)
- **DoD**: `designs.source = 'editor'` / wszystkie designy wizytówek ≥ 20 %
- **Weryfikacja**: SQL query w raporcie

#### C8 — KSeF + MPP
- **DoD**: 100 % FV B2B >15k PLN brutto ma `is_mpp = true` i KSeF UUID
- **Weryfikacja**: SQL assertion
- **Automatyzacja**: test `MppRequiredTest` + integracja e2e z Subiektem mock

### 15.2. Kryteria akceptacji per moduł

**MODUŁ 4 (Pricing Engine):**
- [ ] Wszystkie 17 tabel utworzone, zgodne ze spec Web2Print
- [ ] 11 kroków pipeline'u zaimplementowanych i pokrytych unit testami ≥ 95 %
- [ ] 3 silniki (Sheet, LinearMeter, Piece) przeszły testy na rzeczywistych case'ach branżowych (10 scenariuszy per silnik)
- [ ] Admin UI dla wszystkich encji zrealizowane w Inertia+Vue+shadcn-vue (nie Filament)
- [ ] PriceTester zwraca pełny breakdown z audit trailem
- [ ] RKW overlay zwraca koszt własny dla każdego zamówienia

**MODUŁ 17 (Shop):**
- [ ] Strony Home/Category/Product/Cart/Checkout działają z SSR
- [ ] Konfigurator real-time < 500 ms response
- [ ] Guest checkout działa (scenariusz 8.1 E2E)
- [ ] B2B checkout z NIP lookup GUS
- [ ] MPP auto-trigger > 15k PLN
- [ ] Magic link conversion guest → user
- [ ] Cart persistence (session + user)
- [ ] Promo codes działają, liczniki użyć
- [ ] Abandoned cart email wysyłany po 6 h

**MODUŁ 18 (Design Editor):**
- [ ] Edytor ładuje się w < 3 s
- [ ] Autosave co 5 s (verified in network tab)
- [ ] Min 20 szablonów w bibliotece
- [ ] PDF export generuje PDF/X-4 CMYK 300 dpi z outline fontami (verified in Acrobat preflight)
- [ ] Guest może stworzyć design i zamówić (scenariusz 8.1)
- [ ] Logged user może klonować projekt
- [ ] SceneValidator wyświetla błędy/ostrzeżenia

**Portal:**
- [ ] Dashboard pokazuje poprawne KPI (real data)
- [ ] Lista zamówień z filtrami działa
- [ ] Akceptacja proof ma log (IP, timestamp, checksum)
- [ ] Repeat order tworzy koszyk z frozen config
- [ ] Chat działa real-time (Reverb)
- [ ] Reklamacje CRUD
- [ ] Eksport danych RODO działa (job 72 h)

### 15.3. Testy end-to-end — scenariusze smoke

Przed każdym deploymentem do prod:

```bash
# 1. Unit + Feature
php artisan test --compact --stop-on-failure

# 2. E2E krytyczne
npx playwright test e2e/shop/guest-checkout.spec.ts
npx playwright test e2e/portal/accept-approval.spec.ts
npx playwright test e2e/admin/price-tester-breakdown.spec.ts

# 3. Health checks
curl -f https://drukarnia.pl/health
curl -f https://admin.drukarnia.pl/health

# 4. Migracja dry-run
php artisan migrate --pretend

# 5. Pint + typecheck
vendor/bin/pint --test --format=agent
npm run typecheck

# 6. Security audit
composer audit
npm audit --production
```

### 15.4. Weryfikacja przez MCP tools

Wbudowany MCP server (patrz `CLAUDE.md`) dostarcza narzędzi do szybkiej weryfikacji:

```
mcp: project_status              → wszystko green?
mcp: migration_status            → czy ostatnie migracje zaaplikowane
mcp: horizon_status              → czy queue workers działają
mcp: database_query "SELECT COUNT(*) FROM orders WHERE status = 'PRODUCTION'"
mcp: log_tail                    → ostatnie błędy
mcp: route_list --path=shop      → wszystkie routes sklepu
mcp: model_inspect App\\Models\\Order → czy casts i relacje OK
mcp: test_run --filter=Pricing   → unit testy pricing
```

### 15.5. Go-live checklist

Przed uruchomieniem produkcyjnym:

- [ ] DNS + SSL (Let's Encrypt / ZeroSSL)
- [ ] HSTS preload submitted
- [ ] Cloudflare proxy + WAF rules
- [ ] Backup verified (restore drill na staging z ostatniego backupu)
- [ ] Monitoring: Uptime Kuma, Pulse, Horizon, Sentry (Phase 4)
- [ ] Email deliverability: SPF, DKIM, DMARC dla `@drukarnia.pl`
- [ ] Queue workers: supervisor config, auto-restart
- [ ] Redis persistence (AOF)
- [ ] MinIO: bucket policies, versioning, lifecycle rules
- [ ] PostgreSQL: tuning `shared_buffers`, `work_mem`, VACUUM schedule
- [ ] Crons: `php artisan schedule:run` co minutę (cron)
- [ ] Terms of service + Privacy policy publikowane
- [ ] Cookie banner aktywny
- [ ] GA4 + Facebook Pixel (za zgodą)
- [ ] Integration creds w vault (nie w .env w git)
- [ ] Incident response playbook gotowy
- [ ] Szkolenie 2 operatorów przeprowadzone (walkthrough workflow)
- [ ] Szkolenie właściciela (Reports, Settings)
- [ ] 24h smoke test w prod-like staging z realnymi danymi

### 15.6. Post-launch monitoring (pierwsze 30 dni)

- **Week 1**: Daily incident review (wszystkie 500 errors, failed jobs)
- **Week 2**: Retrospektywa z operatorami (ankieta + 1 rozmowa 30 min)
- **Week 3**: Performance tuning (wolne query, optymalizacja indeksów)
- **Week 4**: First monthly KPI report z celów C1–C8

---

## Podsumowanie

Ten plan wprowadza trzy fundamentalne zmiany do ERP drukarni:

1. **Silnik cenowy Web2Print** (MODUŁ 4) — deterministyczny pipeline 11-krokowy z 3 silnikami (sheet/linear_meter/piece), 17 tabelami DB zgodnie z niemodyfikowalną specyfikacją, portowany z Filament/Livewire na Inertia+Vue+shadcn-vue. Loyalty i RKW zachowane jako overlay i shadow calculator.

2. **Sklep internetowy** (MODUŁ 17) — samoobsługowy konfigurator oparty o ten sam pipeline, guest checkout z konwersją przez magic link, koszyk + 5-stepowy checkout, promo codes, integracja z P24/PayU, portal klienta jako prefix `/moje-konto/*` w tym samym SPA.

3. **Design Editor** (MODUŁ 18) — Konva.js + vue-konva + Pinia + szablony + asset library + serverowy eksport PDF/X-4 przez Puppeteer + Ghostscript z ICC profilami.

Wszystkie 16 pozostałych modułów dostosowane do nowych kanałów, nowej state machine (16 statusów), eventów domenowych, integracji KSeF/MPP.

**Całkowity czas wdrożenia:** ~26 tygodni (6 miesięcy). **Budżet tech debt:** niski (Clean Architecture Lite + testy na każdym poziomie + CI/CD).

**Źródłem prawdy pozostaje `plan_implementacji_modul_web2print_v2.md`** — wszelkie zmiany w interpretacji cenotwórstwa wymagają jego aktualizacji, nie nadpisywania ad-hoc w kodzie.


