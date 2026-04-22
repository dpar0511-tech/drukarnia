# DRUKARNIA ERP — ARCHITECTURE DECISION RECORDS (ADR)

> **Dokument:** Kluczowe decyzje architektoniczne i ich uzasadnienia  
> **Dla:** Developerów + property owners  
> **Status:** Living document — aktualizuj gdy pojawi się nowa decyzja

---

## ADR-001: Inertia.js zamiast REST API + SPA

**Status:** ✅ APPROVED  
**Data:** 2026-04-20  
**Kontekst:** Budujemy wewnętrzny backend ERP (nie SaaS), głównie desktop users.

### Problem

Czy zbudować REST API + oddzielną Vue 3 SPA, czy użyć Inertia.js (hybrid approach)?

### Decyzja

**Użyj Inertia.js v3 dla całej warstwy ERP** (backend operator + portal klienta).

### Uzasadnienie

**Pros:**
- Brak dublowania walidacji (backend = frontend validation rules)
- Vue komponenty native (nie trzeba JS frameworku dla podstawowych rzeczy)
- useForm Inertii = mniej boilerplate (auto-CSRF, auto-redirect, auto-error display)
- Mniejsze API surface (3 endpointy zamiast 50+)
- Laravel Ziggy = route() w JS (type-safe URLs)

**Cons:**
- Mniej flexible dla mobilnych native apps (ale PWA wystarczy w F1–F3)
- Server-side rendering = większy load (ale Octane + FrankenPHP to obsługują)

### Konsekwencje

- Brak OpenAPI/Swagger spec (Inertia routes są dokumentacją)
- Ziggy musi być zsynchronizowany z routes/ w repozytorium
- Frontend cache invalidation: Inertia handleuje automatycznie (except assets)

### Alternatywy rozpatrzone

1. **REST API + Vue 3 SPA** → Overengineering dla ERP
2. **Livewire** → Zbyt tailored do Laravel, mniej flexible
3. **Next.js / Nuxt** → Overkill, własny server = kosztowne

---

## ADR-002: PostgreSQL 16 zamiast MySQL 8

**Status:** ✅ APPROVED  
**Data:** 2026-04-20

### Problem

Baza danych dla systemu z dużymi JSON payloads, transakcjami ACID, potrzeba partycjonowania dla audit logs.

### Decyzja

**PostgreSQL 16** dla wszystkich środowisk (dev = SQLite testami, prod = PG 16).

### Uzasadnienie

**Pros:**
- JSONB type z indexing (price_breakdown, product_parameters, design_editor_state)
- Generated Columns: `net_price = CAST(gross_price / 1.23 AS NUMERIC)` (zawsze zsync)
- Partial indexes: `CREATE INDEX idx_orders_pending ON orders WHERE status = 'WAITING_PAYMENT'`
- Window functions: `ROW_NUMBER() OVER (PARTITION BY client_id ORDER BY created_at DESC)`
- Partycjonowanie tabel: `activity_log` po dacie (archive stare wpisy)
- `EXCLUDE` constraint: zapobiegaj duplikatom w inteligentny sposób

**Cons:**
- Wymaga `pdo_pgsql` extension (PHP)
- Migrate up trickier niż MySQL (np. SERIAL vs UUID)
- Mniej gotowych 3rd-party tools (ale phpPgAdmin ok dla nas)

### Konsekwencje

- Dev testy używają SQLite (migracje mogą się rozbić — waliduj w CI!)
- Staging/Prod = PG 16 (wymaga backup strategi dla JSONB)
- Connection pooling: PgBouncer na produkcji (Laravel Octane handleuje)

### Alternatywy

1. **MySQL 8** → Brak JSONB indexing, Generated Columns słabe
2. **MongoDB** → Nie potrzebujemy NoSQL (structured data)

---

## ADR-003: shadcn-vue jako jedyne źródło UI

**Status:** ✅ APPROVED  
**Data:** 2026-04-20

### Problem

Standardowy component library dla Vue 3 UI — spójność, dark mode, a11y.

### Decyzja

**Wszystkie komponenty UI z shadcn-vue** (`resources/js/Components/ui/`).  
**ŻADEN custom HTML/CSS dla elementów które istnieją w shadcn.**

### Reguła

```vue
<!-- ✅ GOOD: Use shadcn -->
<template>
  <Button variant="outline" @click="handleClick">
    Click me
  </Button>
</template>

<!-- ❌ BAD: Custom div styling -->
<template>
  <div class="px-4 py-2 border rounded cursor-pointer">
    Click me
  </div>
</template>
```

### Uzasadnienie

- Spójny design across entire app
- Dark mode: automatic (tailwind dark: prefix)
- a11y: ARIA labels built-in
- Maintenance: fix once, apply everywhere
- Dokumentacja: shadcn docs = nasze docs

### Konsekwencje

- Brak custom Tailwind utilities dla UI (tylko spacing, colors, typography)
- Jeśli czegoś brakuje:
  ```bash
  npx shadcn-vue@latest add <component-name>
  ```
- Code reviews: **MUST** check for custom divs masquerading as UI components

### Alternatywy

1. **Headless UI** → Less features than shadcn
2. **PrimeVue** → Too heavyweight
3. **Material UI** → Too Googley

---

## ADR-004: Pricing Engine — Separacja logiki od UI

**Status:** ✅ APPROVED  
**Data:** 2026-04-20

### Problem

Pricing logic jest złożona (11 kroków pipeline). Musi być reużywalna w:
- Panel operatora (Inertia)
- Sklep publiczny (Web2Print configurator)
- Portal klienta (repeat order suggestions)
- API publiczny (integracje B2B)
- Cron jobs (dynamic pricing updates)

### Decyzja

**Pricing Engine = pure PHP services bez Eloquent w logice cenowej.**

### Architektura

```php
// Input
class PricingInput {
    public function __construct(
        public readonly Product $product,
        public readonly int $quantity,
        public readonly string $material,
        // ...
    ) {}
}

// Engine (NO database queries inside)
class PricingEngine {
    public function calculate(PricingInput $input): PricingOutput {
        // 11 steps: validate → layout → waste → base price → costs → time → machine → multipliers → discounts → overlays → final
        // Returns IMMUTABLE output
    }
}

// Output
readonly class PricingOutput {
    public function __construct(
        public array $breakdown,    // JSONB
        public float $netPrice,
        public float $grossPrice,
        // ...
    ) {}
}
```

### Uzasadnienie

**Pros:**
- Unit tests: ~10x szybsze (no database)
- Reusable: sklep, API, cron, wszystkie używają tego samego kodu
- Deterministic: same input = same output zawsze
- Mockable: łatwo testować integracją

**Cons:**
- Więcej classes (DTO)
- Config loading nieco bardziej verbose

### Konsekwencje

- Database access: TYLKO w controller/service wrapper
- Input validation: **BEFORE** calling PricingEngine
- Caching: cache final `PricingOutput` JSONB, nie intermediate steps

### Alternatywy

1. **Eloquent queries wewnątrz** → Testowanie bolało, coupling
2. **SQL procedure** → Mniej czytelne, trudniej debugować

---

## ADR-005: Web2Print Pipeline — 11 kroków (immutable spec)

**Status:** ✅ APPROVED  
**Data:** 2026-04-20

### Problem

Pricing engine musi być **szczegółowo specificied** aby różne osoby mogły pracować nad różnymi komponentami.

### Decyzja

**Web2Print Pipeline = dokument `plan_implementacji_modul_web2print_v2.md` (IMMUTABLE SOURCE OF TRUTH).**

### Struktura

```
Step 1: Validate product & parameters
Step 2: Calculate sheet layout (imposition)
Step 3: Calculate waste (scrap %)
Step 4: Load base price rule
Step 5: Calculate COGS (material quantity × unit price)
Step 6: Estimate production time
Step 7: Calculate machine costs (machine.cost_per_hour × time)
Step 8: Apply pattern multiplier (np. dla wzorów −20%)
Step 9: Apply rush fee (przyspieszone terminy +50%)
Step 10: Apply stackable discounts (sorted by priority)
Step 11: Return breakdown JSON
```

### Silniki

- **SheetEngine** — produkty arkuszowe (wizytówki, ulotki, plakaty)
- **LinearMeterEngine** — roll-upy, banery, naklejki (metr bieżący)
- **PieceEngine** — opakowania (sztuka, wymiary custom)

### Overlays (aplikowane POTEM)

```
Loyalty Overlay: price × (1 − loyalty_discount%)
RKW Overlay: shadow calculation (parallel, nie wpływa na cenę)
```

### Konsekwencje

- Port do innej technologii = OK (np. z Livewire na Vue)
- zmiana logiki Pipeline = zmiana w SOURCE OF TRUTH FIRST, potem wszędzie
- Zmiany do Pipeline: review z właścicielem + reaserta testy pricing'u

---

## ADR-006: Design Editor — Konva.js + JSON state

**Status:** ✅ APPROVED  
**Data:** 2026-04-20

### Problem

Design Editor (Moduł 18) musi być:
- Online (nie desktop app)
- Collaborative-ready (future)
- Versionable (historia edycji)
- Exportable to PDF (with bleed + trim marks)

### Decyzja

**Canvas engine = Konva.js (industry standard, active community)**  
**State = JSON (v1, v2, v3 versioning)**  
**Storage = DAMS (MinIO S3 storage)**

### Architektura

```
Vue 3 Component
    ↓
vue-konva binding (reactive layer)
    ↓
Konva.Stage (canvas engine)
    ↓
JSON state (scene snapshot)
    ↓
MinIO (persistent storage)
    ↓
Export: Puppeteer (PDF rendering)
```

### Uzasadnienie

- Konva.js: mature, performant, rich API
- JSON state: lightweight (< 100 KB per design)
- MinIO: unlimited versions, cheap storage
- Puppeteer PDF: accurate CMYK + bleed + trim marks

### Konsekwencje

- Wymagamy headless Chromium/Firefox (F3.x)
- Network: Puppeteer API call + PDF streaming (1–2 s latency)
- Future: WebGL rendering dla 3D mockups (F4+)

### Alternatywy

1. **Fabric.js** → Older, less maintained
2. **Three.js** → Overkill, 3D jest niechciany w F1–F3
3. **Canvas API** → Too low-level, brak editing widgets

---

## ADR-007: Queue-based async processing

**Status:** ✅ APPROVED  
**Data:** 2026-04-20

### Problem

System musi być resilient (network timeouts, slow integrations). Przy synchronicznym execution = app hangs.

### Decyzja

**Wszystkie operacje I/O = Queue jobs (email, file processing, API calls, webhooks).**

### Retry Policy

```
Attempt 1: Immediate
Attempt 2: Backoff 60s
Attempt 3: Backoff 300s
Failed: Move to DLQ (Dead Letter Queue) + alert
```

### Uzasadnienie

- Decoupling: app nie zależy od szybkości integracji
- Resilience: network timeout ≠ app crash
- Observability: Horizon dashboard
- Manual retry: za jeden click w UI

### Konsekwencje

- Testing: tricky (queues asynchronous) → use `Bus::fake()`
- Monitoring: musimy watch failed jobs
- Backup jobs: jeśli queue mnie, muszą być persisted

---

## ADR-008: Event-driven architecture

**Status:** ✅ APPROVED  
**Data:** 2026-04-20

### Problem

Wiele działań zależy od zmian statusu zamówienia. Jeśli hardcodujemy w jednym miejscu = coupling.

### Decyzja

**Event-driven: każda zmiana stanu → emit domain event → listeners react.**

### Przykład

```php
// Event
event(new OrderStatusChanged($order, $fromStatus, $toStatus));

// Listeners (decouplded)
class SendNotificationEmail implements ShouldQueue { ... }
class CreateProductionJob implements ShouldQueue { ... }
class UpdateAnalyticsMetrics { ... }
```

### Uzasadnienie

- Decoupling: dodaj nową akcję bez modyfikacji starego kodu
- Extensibility: każdy moduł nasłuchuje event'ów które go interesują
- Auditability: pełny trail zmiany stanu

### Konsekwencje

- Event naming standard: `OrderStatusChanged` (nie `order.status.changed`)
- Event payload: zawieraj wszystko co might be needed (avoid extra query)

---

## ADR-009: Akceptacja Tailwind CSS v4

**Status:** ✅ APPROVED  
**Data:** 2026-04-21

### Problem

W trakcie inicjalizacji zainstalowano najnowszą wersję pakietu `tailwindcss` (v4), podczas gdy wcześniejsze standardy w projektach opartych na starszych dokumentacjach przewidywały v3.

### Decyzja

**Zatwierdzamy użycie Tailwind CSS v4 w projekcie jako wiodącej technologii stylizacji z shadcn-vue.**

### Uzasadnienie

- Szybszy czas budowania i lżejsza architektura (oparta m.in. na Lightning CSS).
- Uproszczona konfiguracja oparta o CSS zamiast dużego `tailwind.config.js`.
- Brak konfliktów i wysoce rekomendowane środowisko pod nowsze wydania biblioteki UI.

---

## PRZYSZŁE ADR DO DYSKUSJI

- [ ] ADR-009: Multi-tenancy (czy kiedy?)
- [ ] ADR-010: Caching strategy (Redis TTLs)
- [ ] ADR-011: Search backend (Meilisearch vs Elasticsearch)
- [ ] ADR-012: Secrets management (env vs vault)

---

**Dokument:** ARCHITECTURE.md  
**Wersja:** 1.0  
**Ostatnia aktualizacja:** 2026-04-20
