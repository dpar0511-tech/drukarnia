# 03 — ARCHITEKTURA SYSTEMU

## 1. Wzorzec: modularny monolit

18 modułów domenowych (bounded contexts) w **jednej bazie kodu**, **jednej bazie danych**, **jednym procesie Laravela**. Komunikacja między modułami wyłącznie przez **eventy Laravel** lub **publiczne API serwisów** (interfejsy). Żadne bezpośrednie odwołanie do modelu Eloquent z innej domeny.

**Dlaczego nie mikrousługi:**
- 1 programista buduje MVP — overhead mikrousług jest paraliżujący.
- 1 drukarnia = 1 instancja; skala nie uzasadnia rozproszonej architektury.
- Model danych jest silnie powiązany (zamówienie ↔ klient ↔ produkcja ↔ finanse).

**Kiedy refaktoryzować na mikrousługi:** gdy (a) baza kodu > 200k LOC, (b) zespół > 5 programistów, (c) jeden moduł (np. Pricing lub DAMS) ma własny cykl wdrożeń.

---

## 2. Struktura katalogów Laravel

```
app/
├── Enums/                          # PHP 8.1 native enums
├── Traits/                         # CostPriceTrait, SingletonTrait, Auditable
├── Exceptions/                     # Custom exceptions (PriceCalculationException, ...)
│
├── Models/                         # Eloquent — pogrupowane per domena
│   ├── Core/                       # GlobalSetting
│   ├── Parameters/                 # MediaFormat, Material, ZadrukOption, TimeOption, PageConfig, PatternSetting, ProjectSetting, CustomFormatSetting, Parameter, ParameterOption, SimpleParameter, SimpleParameterOption, MaterialSize
│   ├── Products/                   # Product, ProductOverride, Product*Option pivots, SimpleProductDiscount
│   ├── Exclusions/                 # ExclusionRule, ExclusionCondition, ExclusionAction
│   ├── CRM/                        # Klient, OsobaKontaktowa, Tag, ProgLojalnosciowy
│   ├── Orders/                     # Zamowienie, PozycjaZamowienia, HistoriaStatusu
│   ├── DAMS/                       # Plik, WersjaPliku, PowiazaniePliku (polimorficzne)
│   ├── Production/                 # ZadanieProdukcyjne, EtapProdukcji, Maszyna, Operator
│   ├── Finance/                    # DokumentFinansowy, Platnosc, MetodaPlatnosci
│   ├── Logistics/                  # Przesylka, AdresWysylki
│   ├── Complaints/                 # Reklamacja, DowodReklamacji
│   ├── Comm/                       # WatekKomunikacji, Wiadomosc, ZalacznikWiadomosci, SzablonWiadomosci, Powiadomienie
│   ├── Shop/                       # Koszyk, PozycjaKoszyka, KodPromocyjny
│   ├── Design/                     # ProjektGraficzny, WersjaProjektu, KomentarzProjektu
│   └── RBAC/                       # User, Rola, AuditLog (lub bezpośrednio w app/Models/User.php)
│
├── Services/                       # Logika biznesowa — jedyne miejsce z regułami
│   ├── Pricing/                    # 🔥 Silnik cenowy (patrz 07)
│   │   ├── PricingPipeline.php
│   │   ├── SimplePricingService.php
│   │   ├── SettingResolver.php
│   │   ├── ProjectPageResolver.php
│   │   ├── ExclusionResolver.php
│   │   ├── LoyaltyOverlay.php
│   │   ├── RkwShadowCalculator.php
│   │   ├── Engines/                # SheetEngine, LinearMeterEngine, MultipageEngine, EngineResolver
│   │   ├── Calculators/            # ImpositionCalculator, MarginCalculator, WasteCalculator, RollSelectorCalculator
│   │   └── ValueObjects/           # CalculationConfig, EngineResult, PricingResult, SimplePricingResult, UserSelection, ExclusionResult
│   ├── Orders/                     # OrderCreator, OrderDuplicator, StatusTransitioner
│   ├── CRM/                        # GusValidator, LoyaltyTierRecalculator, ClientAnonymizationService
│   ├── DAMS/                       # FileUploader (tus), ThumbnailGenerator, FileAccessPolicy
│   ├── Production/                 # KanbanService, TimeLogger
│   ├── Finance/                    # ProformaGenerator, InvoiceOrchestrator, MppEvaluator
│   ├── Logistics/                  # ShippingLabelService, TrackingService
│   ├── Complaints/                 # RmaWorkflow, SlaTimer
│   ├── Comm/                       # ImapPoller, TemplateRenderer, NotificationDispatcher
│   ├── Shop/                       # CartService, CheckoutService, PromoCodeValidator
│   ├── Design/                     # KonvaSceneSerializer, PdfExporter
│   └── Auth/                       # PermissionSeeder, AuditObserver
│
├── Integrations/                   # Adaptery zewnętrzne (patrz 10) — Saloon connectors
│   ├── Subiekt/
│   ├── Gus/
│   ├── Przelewy24/
│   ├── InPost/
│   ├── Dpd/ Dhl/ Gls/
│   ├── SmsApi/
│   └── Imap/
│
├── Events/                         # OrderCreated, PriceCalculated, FileUploaded, ApprovalApproved, ...
├── Listeners/                      # Reakcje na eventy (często wywołują Services)
├── Jobs/                           # GenerateThumbnailsJob, SendInvoiceToKsefJob, ...
├── Policies/                       # FilePolicy, OrderPolicy, PricingPolicy (view cost)
├── Http/
│   ├── Controllers/                # Inertia controllers (cienkie — delegują do Services)
│   ├── Middleware/                 # HandleInertiaRequests, CheckPermission, ...
│   ├── Requests/                   # FormRequest z walidacją
│   └── Resources/                  # Inertia Resources (jeśli trzeba explicite)
│
├── Console/Commands/               # Artisan commands (MVP: ImapPollCommand, PurgeOldFilesCommand, RefreshMaterializedViewsCommand, ...)
├── Filament/                       # Admin panel (zasoby, strony) — patrz 09
└── Providers/                      # AppServiceProvider, EventServiceProvider, RouteServiceProvider, HorizonServiceProvider, BroadcastServiceProvider

resources/
├── js/
│   ├── Components/
│   │   └── ui/                     # shadcn-vue — jedyne źródło UI
│   ├── Pages/                      # Inertia pages: Orders/Index.vue, Orders/Show.vue, ...
│   ├── Layouts/                    # AppLayout.vue, GuestLayout.vue, PortalLayout.vue
│   ├── Composables/                # useAuth, useForm helpers, usePricingGrid
│   ├── lib/
│   │   └── utils.ts                # cn() utility
│   └── app.ts
└── views/
    └── app.blade.php               # Inertia shell

database/
├── migrations/
├── seeders/
└── factories/

tests/
├── Feature/
│   ├── Pricing/                    # 49 testów z pricingu (patrz 12)
│   ├── Orders/, CRM/, DAMS/, ...
└── Unit/
    └── Pricing/                    # Calculators, Resolvers
```

Alternatywa: **`app/Modules/{Nazwa}`** (Domain-Driven-Design package) — każdy moduł ma własne `Models/`, `Services/`, `Events/`. Dla zespołu 1–2 osobowego standard Laravel (powyżej) wystarcza i jest prostszy w onboardingu.

---

## 3. Warstwy

```
┌──────────────────────────────────────────────────────┐
│  HTTP / Inertia Controllers / Filament Resources     │  ← Cienkie (walidacja + delegacja)
└──────────────────────────────┬───────────────────────┘
                               ▼
┌──────────────────────────────────────────────────────┐
│  Services (logika biznesowa, Value Objects)          │  ← Serce systemu
└──────────────────────────────┬───────────────────────┘
                               ▼
┌──────────────────────────────────────────────────────┐
│  Eloquent Models (ORM) + Traits                      │  ← Trwałość
└──────────────────────────────┬───────────────────────┘
                               ▼
┌──────────────────────────────────────────────────────┐
│  PostgreSQL + Redis + Lokalne dysk + Meilisearch    │  ← Infrastruktura
└──────────────────────────────────────────────────────┘
```

### Zasady warstw

1. **Kontroler Inertia zawiera 0 logiki biznesowej.** Tylko: walidacja (FormRequest), wywołanie serwisu, return `Inertia::render(...)` z propsami.
2. **Modele Eloquent trzymają tylko:** relacje, casty, scopes, accessors (proste). Brak metod typu `createOrder()` — to rola serwisu.
3. **Serwisy operują na Value Objects** gdy to sensowne (pricing — tak, duplikacja zamówienia — tak). CRUD prostych encji — wprost na modelach.
4. **Eventy są asynchroniczne** (queued listeners) z wyjątkiem triggerów w ramach tej samej transakcji DB, gdzie potrzeba synchroniczności.

### Przykład: OrderController@store

```php
// app/Http/Controllers/OrderController.php
public function store(StoreOrderRequest $request, OrderCreator $creator)
{
    $order = $creator->create($request->validated(), auth()->user());
    return Inertia::render('Orders/Show', ['order' => new OrderResource($order)]);
}

// app/Services/Orders/OrderCreator.php
public function create(array $data, User $user): Order
{
    return DB::transaction(function () use ($data, $user) {
        $order = Order::create([...]);
        foreach ($data['items'] as $item) {
            $order->items()->create([...]);
        }
        OrderCreated::dispatch($order);  // async listenery: notyfikacja, wpis do CommHub, Kanban
        return $order;
    });
}
```

---

## 4. Arytmetyka pieniędzy — `brick/math`

**Zakaz `float` w całej warstwie pricingu i finansów.** Używamy `brick/math\BigDecimal`:

```php
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

$total = BigDecimal::of('0');
foreach ($items as $item) {
    $total = $total->plus(BigDecimal::of($item->cost_total ?? '0'));
}
$margin = $total->multipliedBy('100')->dividedBy('100', 2, RoundingMode::HALF_UP);
```

Alternatywnie `bcmath`:
```php
bcscale(10);
$total = bcadd($total, $item->cost_total ?? '0');
```

Laravel cast `decimal:2` na kolumnach DECIMAL(x,2) — pola zawsze jako string w PHP, nigdy float.

---

## 5. Event-driven communication

### Eventy wewnątrzaplikacyjne

Wszystkie istotne zmiany stanu domenowego publikują **event** (w `app/Events/`). Inne moduły reagują przez **listenery** (w `app/Listeners/`, najczęściej `ShouldQueue`).

Przykład: `PriceCalculated` → listenery:
- `CreateProformaIfRequired` (moduł Finance)
- `NotifyClientAboutQuote` (moduł CommHub)
- `LogPriceCalculationForAudit` (RBAC/audit)
- `UpdateOrderStatusToWaitingFiles` (moduł Orders)

Każdy listener = osobna kolejka Redis (supervisor `horizon`), izolacja błędów.

### Broadcast events (Reverb)

Niektóre eventy implementują `ShouldBroadcast`:
- `ProductionStageUpdated` → kanał `production.kanban` → aktualizacja Kanbana w UI bez refreshu
- `NotificationCreated` → kanał `user.{id}` → bell icon
- `ApprovalResponseReceived` → kanał `order.{id}` → manager widzi odpowiedź klienta real-time

Pełny katalog eventów: [06_WORKFLOW_I_EVENTY.md](./06_WORKFLOW_I_EVENTY.md).

---

## 6. Kolejki (Redis + Horizon)

`config/horizon.php`:

```php
'environments' => [
    'production' => [
        'supervisor-default' => [
            'connection' => 'redis',
            'queue' => ['default'],
            'balance' => 'auto',
            'minProcesses' => 1,
            'maxProcesses' => 10,
            'tries' => 3,
            'timeout' => 60,
        ],
        'supervisor-pricing' => [
            'queue' => ['pricing'],
            'maxProcesses' => 5,
            'timeout' => 30,
        ],
        'supervisor-imap' => [
            'queue' => ['imap'],
            'maxProcesses' => 1,    // pojedynczy worker — uniknięcie race condition
            'timeout' => 300,
        ],
        'supervisor-files' => [
            'queue' => ['preflight', 'thumbnails'],
            'maxProcesses' => 3,
            'timeout' => 600,       // duże pliki
        ],
        'supervisor-webhooks' => [
            'queue' => ['webhooks'],
            'maxProcesses' => 5,
            'timeout' => 30,
        ],
        'supervisor-emails' => [
            'queue' => ['emails', 'sms'],
            'maxProcesses' => 3,
            'tries' => 5,
        ],
    ],
],
```

**Dead Letter Queue (DLQ):** po 3 nieudanych próbach job przenoszony do `failed_jobs` (tabela Laravela) + powiadomienie Admina (moduł Notifications). Tabela `failed_jobs_dlq` — tylko jeśli potrzebujemy niestandardowej retencji (inaczej wbudowany Laravel).

---

## 7. Real-time (Reverb)

`config/reverb.php` + `BroadcastServiceProvider`. Kanały:

| Kanał | Typ | Eventy |
|-------|-----|--------|
| `user.{id}` | Private | `NotificationCreated`, `MessageReceived` |
| `order.{id}` | Private | `OrderStatusChanged`, `ApprovalResponseReceived`, `PriceRecalculated` |
| `production.kanban` | Presence | `ProductionStageUpdated`, `ProductionJobAdded`, `OperatorJoined` |
| `chat.{thread_id}` | Private | `MessageSent`, `TypingStarted` |
| `filament.admin` | Private | Live updates adminowe (opcjonalne) |

Autoryzacja kanałów: `routes/channels.php` + policy. Np. `order.{id}` dostępny tylko menedżerowi przypisanemu do zamówienia + klientowi (portal).

Frontend: `laravel-echo` + `pusher-js` (używa Reverb jako backend zgodny z Pusher protocol):
```ts
Echo.private(`order.${orderId}`)
    .listen('ApprovalResponseReceived', (e) => { /* update UI */ });
```

---

## 8. Cache (Redis)

Strategia:

| Klucz | TTL | Inwalidacja |
|-------|-----|-------------|
| `pricing:settings:global` | 1 h | Zmiana `GlobalSetting` → `Cache::tags(['pricing'])->flush()` |
| `pricing:settings:pattern` | 1 h | Analogicznie |
| `pricing:settings:project` | 1 h | Analogicznie |
| `pricing:settings:custom_format` | 1 h | Analogicznie |
| `pricing:product:{id}` | 10 min | Zmiana produktu → `Cache::forget("pricing:product:$id")` |
| `pricing:grid:{hash}` | 5 min | Hash: `product_id + params + qty_brackets` |
| `gus:nip:{nip}` | 24 h | Walidacja NIP — GUS rate-limit |
| `meilisearch:sync:{model}` | brak | Scout broadcasts zmian |

**Ważne:** Inwalidacja cache w `Observer` modelu (np. `GlobalSettingObserver::saved()`).

---

## 9. Search (Meilisearch)

`laravel/scout` + `meilisearch/meilisearch-php`. Indeksy:

| Indeks | Model | Pola searchable |
|--------|-------|-----------------|
| `products` | `Product` | `name`, `description`, `slug` |
| `clients` | `Klient` | `name`, `nip`, `regon`, `email_main`, contact persons |
| `orders` | `Zamowienie` | `number`, `client.name`, `items.product.name` |
| `files` | `Plik` | `original_name`, `comment` |

Polski tokenizer: w `config/scout.php` → `meilisearch.index-settings.*.stopWords` (polskie), `synonyms` (drukarskie).

---

## 10. Diagramy

### C4 — Context

```
          ┌─────────────────┐
          │    Klient B2C   │
          │    Klient B2B   │
          └────────┬────────┘
                   │ HTTPS
          ┌────────▼────────┐          ┌──────────────────┐
          │   ERP System    │◄────────►│  Subiekt nexo    │ (Sfera REST)
          │  (Laravel 11)   │          └──────────────────┘
          └────────┬────────┘
                   │
      ┌────────────┼──────────────┐
      │            │              │
┌─────▼───┐ ┌──────▼──────┐ ┌────▼────┐
│ GUS API │ │ Przelewy24  │ │ InPost  │
└─────────┘ └─────────────┘ └─────────┘
                   │
            ┌──────▼──────┐
            │   Pracownik │
            │  (Kanban,   │
            │   Admin)    │
            └─────────────┘
```

### C4 — Container

```
┌──────────────────────────────────────────────────────────┐
│                     1× VPS Ubuntu 24.04                  │
│                                                          │
│  ┌────────┐   ┌─────────────┐  ┌─────────────────────┐  │
│  │ Nginx  │──►│ Laravel 11  │──│ PostgreSQL 16       │  │
│  │        │   │ (php-fpm /  │  └─────────────────────┘  │
│  │        │   │  Octane)    │  ┌─────────────────────┐  │
│  └────────┘   │             │──│ Redis 7             │  │
│               └──────┬──────┘  └─────────────────────┘  │
│                      │                                  │
│          ┌───────────┼──────────┐                       │
│          ▼           ▼          ▼                       │
│     ┌────────┐ ┌──────────┐ ┌──────────────┐           │
│     │Horizon │ │ Reverb   │ │ Meilisearch  │           │
│     │workers │ │ WebSocket│ │              │           │
│     └────────┘ └──────────┘ └──────────────┘           │
│                                                          │
│  ┌──────────────────────────────────────────────────┐   │
│  │ storage/app/private/  (DAMS — lokalne)            │   │
│  │  ├─ orders/YYYY/MM/{id}/...                       │   │
│  │  ├─ designs/{project_id}/...                      │   │
│  │  ├─ invoices/YYYY/MM/                             │   │
│  │  └─ temp/ (tus chunks)                            │   │
│  └──────────────────────────────────────────────────┘   │
└──────────────────────────────────────────────────────────┘
```

### Sekwencja: Email → Zamówienie → Wycena → Proforma

```
IMAP Poller    CommHub      OrderCreator    PricingPipeline   Finance      Subiekt    Klient
    │              │              │                 │              │            │           │
    │ poll(2min)   │              │                 │              │            │           │
    │─────────────►│              │                 │              │            │           │
    │              │ match klient │                 │              │            │           │
    │              │  create      │                 │              │            │           │
    │              │  Zamowienie──►create           │              │            │           │
    │              │              │ OrderCreated    │              │            │           │
    │              │              │────────────────►│ calculate()  │            │           │
    │              │              │                 │──────────────│            │           │
    │              │              │                 │ PriceCalc ev │            │           │
    │              │              │                 │─────────────►│            │           │
    │              │              │                 │              │ proforma   │           │
    │              │              │                 │              │───────────►│           │
    │              │              │                 │              │            │ invoice # │
    │              │              │                 │              │◄───────────│           │
    │              │              │                 │              │ P24 link                │
    │              │              │                 │              │────────────────────────►│
    │              │◄─────────────┼─────────────────┼──────────────│ mail proforma + link    │
```

---

## 11. Security defence-in-depth

Warstwa po warstwie (szczegóły w 11):
1. Transport: HTTPS only, HSTS, TLS 1.3.
2. Auth: Fortify + opcjonalne TOTP 2FA, Sanctum dla portalu/API.
3. RBAC: `spatie/laravel-permission` + policies.
4. Walidacja: FormRequest + Eloquent casts + DB constraints.
5. Anty-XSS: Vue auto-escape (`{{ }}`), `v-html` tylko po `DOMPurify`.
6. CSRF: wbudowany middleware Laravela.
7. Rate limit: `throttle:api` + `throttle:login`.
8. SQL injection: wyłącznie Eloquent / query builder; brak raw SQL na input klienta.
9. File upload: magic-number + MIME whitelist (patrz 08).
10. Signed URLs: `URL::temporarySignedRoute()` dla plików.
11. Secrets: `.env` nie w Git; rotacja przy opuszczeniu pracownika.
