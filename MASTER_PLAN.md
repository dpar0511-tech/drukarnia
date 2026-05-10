# DRUKARNIA ERP — MASTER PLAN (v3)

> ⚠️ **NOTA O STATUSIE:** Ten dokument został zaktualizowany **2026-04-24** do zgodności z folderem [`nowa dokumentacja/`](./nowa%20dokumentacja/00_INDEX.md) (14 plików, ~6500 linii, stan 2026-04-23).
> **Źródłem prawdy architektonicznej pozostaje `nowa dokumentacja/`** — ten MASTER_PLAN jest uzupełniającym dokumentem wysokopoziomowym.
> W razie konfliktu między tym plikiem a `nowa dokumentacja/` — **wygrywa `nowa dokumentacja/`**.

---

## 0. TL;DR

**DRUKARNIA ERP** — modularny monolit w Laravel 11 + Vue 3 (Inertia v3) dla drukarni B2B/B2C.

- **18 modułów** biznesowych jako bounded contexts (1 CRM, 2 Orders, 3 Products, 4 Pricing, 5 Quotes, 6 Approvals, 7 DAMS, 8 Production, 9 Inventory, 10 Finance, 11 Logistics, 12 Complaints, 13 CommHub, 14 Reports/BI, 15 RBAC/Settings, 16 Integrations, 17 Shop, 18 Design Editor).
- **FSM 19 stanów** zamówienia (`spatie/laravel-model-states`).
- **Silnik cenowy** — 12-krokowy `PricingPipeline` z `_nowy_SILNIK_CENOWY/` (3 silniki, 49 testów 1:1).
- **Storage plików** — **wyłącznie lokalnie** (`storage/app/private/`) + `spatie/laravel-medialibrary` + `tus-php`. **Bez MinIO/S3.**
- **UI** — wyłącznie shadcn-vue + `lucide-vue-next` + kolory semantyczne.
- **Stack:** PHP 8.3 · Laravel 11 · Vue 3 · Inertia v3 · PostgreSQL 16 · Redis 7 · Reverb · Meilisearch · Filament v3 (admin).
- **Fazowanie:** 0 Scaffolding (3 dni) → 1 MVP (10 tyg.) → 2 Portal (8–10 tyg.) → 3 Shop + Design Editor (10–12 tyg.) → 4 Enterprise (otwarte).

---

## 1. STACK TECHNICZNY

| Warstwa | Technologia | Wersja |
|---------|-------------|--------|
| Runtime | PHP + php-fpm lub **Octane + FrankenPHP** | 8.3 |
| Framework backend | Laravel | 11 |
| Framework frontend | Vue 3 (Composition API) | 3.4+ |
| Bridge FE/BE | Inertia.js | v3 |
| Admin panel | **Filament** (Livewire, oddzielne `/admin`) | v3 |
| Baza danych | PostgreSQL | 16 |
| Cache / Queue / Sessions | Redis | 7 |
| WebSocket | Laravel Reverb | v1 |
| Queue monitor | Laravel Horizon | v5 |
| Search | Meilisearch + Laravel Scout | 1.11+ |
| Auth | Laravel Fortify + Sanctum | v1 + v4 |
| RBAC | `spatie/laravel-permission` | v6 |
| FSM | `spatie/laravel-model-states` | v2 |
| Audit log | `spatie/laravel-activitylog` | v4 |
| DAMS storage | **Lokalne dyski** + `spatie/laravel-medialibrary` | v11 |
| Upload resumable | `ankitpokhrel/tus-php` (tus.io) | v2 |
| Miniatury | `spatie/pdf-to-image` + `intervention/image` v3 + Ghostscript + ImageMagick | — |
| Arytmetyka pieniędzy | `brick/math` `BigDecimal` (scale 10) | v0.12 |
| Integracje HTTP | **`saloonphp/saloon`** v4 | v4 |
| CSV | `league/csv` | v9 |
| Formatter | Laravel Pint | v1 |
| Testy | Pest 4 (wrapper PHPUnit 11) | — |
| UI components | **shadcn-vue only** + Reka UI | latest |
| Ikony | **`lucide-vue-next` only** | latest |
| Build | Vite | 5 |
| Style | Tailwind CSS | 3 |
| TypeScript | opcjonalnie (z Breeze `--typescript`) | 5 |
| PDF viewer | PDF.js | 4 |
| Charts | Chart.js + vue-chartjs | 4 + 5 |
| Design Editor canvas | vue-konva + konva.js | 3 + 9 |
| Deploy | Deployer v7 | — |
| Kontener (dev) | Docker Compose | — |
| OS prod | Ubuntu LTS | 24.04 |
| Web server | Nginx | 1.24+ |
| PDF ops | Ghostscript 10+, ImageMagick 7+ | — |

**Usunięte z legacy (nie instalować):**
- `league/flysystem-aws-s3-v3` — storage **wyłącznie lokalne**.
- `pusher/pusher-php-server` — Reverb zastępuje.
- `laravel/nova` — Filament v3 darmowy i lepszy.

---

## 2. 18 MODUŁÓW DOMENOWYCH

Pełny opis: [05_MODULY_BIZNESOWE.md](./nowa%20dokumentacja/05_MODULY_BIZNESOWE.md).

| # | Moduł | Odpowiedzialność |
|---|-------|------------------|
| 1 | **CRM** | Klienci B2B/B2C, osoby kontaktowe, walidacja NIP (GUS), lojalność, tagi, GDPR |
| 2 | **Orders** | Zamówienia, pozycje, FSM 19 stanów, numeracja `DRK-YYYY-XXXXX`, duplikacja (dodruki) |
| 3 | **Products** | Katalog produktów + parametry; konfig w Filament |
| 4 | **Pricing** | **12-krokowy `PricingPipeline`** (3 silniki: Sheet/LinearMeter/Multipage) + produkty proste |
| 5 | **Quotes** | Wyceny, wygaśnięcia (14 dni), konwersja do zamówień |
| 6 | **Approvals** | Akceptacja projektów (link publiczny), markup na PDF, rundy rewizji, eskalacja ≥4 |
| 7 | **DAMS** | Upload tus.io, versioning, miniatury, polimorficzne linkowanie — **lokalnie** |
| 8 | **Production** | Zadania produkcyjne, Kanban, etapy (prepress/druk/postpress), logowanie czasu |
| 9 | **Inventory** | Magazyn, low-stock alerts, auto-odpisy zużycia z produkcji (BOM) |
| 10 | **Finance** | Orkiestrator dokumentów (Proforma → Zaliczkowa → VAT/Paragon → Korekta), Subiekt nexo, KSeF, MPP |
| 11 | **Logistics** | InPost / DPD / DHL / GLS / odbiór osobisty; etykiety, tracking |
| 12 | **Complaints** | RMA, SLA 48h response / 14 dni resolution (Rękojmia), przedruk/zwrot |
| 13 | **CommHub** | IMAP poll (2min), outbound email/SMS, templates, threading, in-app + web push |
| 14 | **Reports / BI** | Dashboardy, materialized views, eksport CSV/XLSX |
| 15 | **RBAC / Settings** | 6 ról (Admin/Menedżer/Projektant/Operator/Księgowość/Klient), Fortify + 2FA, audit log |
| 16 | **Integrations** | Adaptery Saloon (Subiekt, GUS, P24, InPost, DPD, DHL, GLS, SMSAPI, IMAP) |
| 17 | **Shop** (Faza 3) | E-commerce B2C/B2B, cart, guest checkout, promo codes, Omnibus 30d |
| 18 | **Design Editor** (Faza 3) | Konva.js canvas, autosave, eksport CMYK PDF 300 DPI + 3mm bleed |

### Schemat zależności

```
  CommHub ←────────── Orders ─────────→ Pricing
     │                 │                   │
     │          ┌──────┴──────┐           │
     │          ▼             ▼           │
     │      Production    DAMS ←──── Design Editor
     │          │          │
     │          └──┬───────┘
     │             ▼
     │          Finance ──→ Integrations
     │             │
     │             ▼
     │          Logistics ──→ Integrations
     │
     ▼
  Notifications → wszystkie moduły

  Shop ──→ Pricing, Orders, Finance, CommHub
  Approvals ──→ Orders, DAMS, CommHub
  Complaints ──→ Orders, Finance, CommHub
  Reports ──→ wszystkie (read-only)
  RBAC ──→ wszystkie (policies)
  Quotes ──→ Pricing, Orders, CommHub
```

**Żelazna zasada:** moduły komunikują się **wyłącznie przez eventy** lub **interfejsy serwisów** (DI).

---

## 3. MODULE 1 — CRM

Szczegóły: [05_MODULY_BIZNESOWE.md](./nowa%20dokumentacja/05_MODULY_BIZNESOWE.md) sekcja MODUŁ 1.

**Tabele:** `klienci`, `osoby_kontaktowe`, `tagi`, `klient_tagi`, `progi_lojalnosciowe`.

**Serwisy:**
- `App\Services\CRM\ClientRegistrar::create` — walidacja NIP (`NipValidator`), auto-fill z GUS.
- `App\Services\CRM\GusValidator::validate(string $nip)` — Saloon adapter, cache Redis 24h.
- `App\Services\CRM\LoyaltyTierRecalculator::recalculate(Klient $k)` — listener na `PaymentReceived`.
- `App\Services\CRM\ClientAnonymizationService::anonymize(Klient $k)` — RODO prawo do bycia zapomnianym.

**Eventy:** `ClientCreated`, `ClientBlocked`, `LoyaltyTierChanged`, `ClientNipValidated`.

---

## 4. MODULE 2 — ORDERS + FSM 19 STANÓW

**Tabele:** `zamowienia`, `pozycje_zamowien`, `specyfikacje_produktowe`, `historia_statusow_zamowien`.

**Numer zamówienia:** `DRK-YYYY-XXXXX` — atomic (`SELECT ... FOR UPDATE` per rok).

### FSM 19 stanów (`spatie/laravel-model-states`)

| Kod | Klasa | Opis | Legalne następne |
|-----|-------|------|------------------|
| `NOWE` | `New` | Zamówienie utworzone | WYCENA, ANULOWANE |
| `WYCENA` | `Pricing` | Trwa kalkulacja / czekamy na akceptację wyceny | OCZEKUJE_NA_PLIKI, OCZEKUJE_NA_PLATNOSC, ANULOWANE |
| `OCZEKUJE_NA_PLIKI` | `WaitingFiles` | Klient ma dostarczyć materiały | PROJEKTOWANIE, OCZEKUJE_NA_AKCEPTACJE, SUSPENDED, ANULOWANE |
| `OCZEKUJE_NA_PLATNOSC` | `WaitingPayment` | Proforma wystawiona, czekamy na P24 | OCZEKUJE_NA_PLIKI, AKCEPTOWANE, SUSPENDED, ANULOWANE |
| `PROJEKTOWANIE` | `Designing` | Usługa graficzna w toku | OCZEKUJE_NA_AKCEPTACJE |
| `OCZEKUJE_NA_AKCEPTACJE` | `WaitingApproval` | Link do klienta, czekamy na odpowiedź | REWIZJA, AKCEPTOWANE, ODRZUCONE |
| `REWIZJA` | `Revision` | Klient zażądał zmian (rundy 2, 3, 4…) | PROJEKTOWANIE, OCZEKUJE_NA_AKCEPTACJE |
| `ODRZUCONE` | `Rejected` | Klient odrzucił projekt | ANULOWANE |
| `AKCEPTOWANE` | `Approved` | Projekt OK, czekamy na slot produkcji | W_PRODUKCJI |
| `W_PRODUKCJI` | `InProduction` | Kanban aktywny | GOTOWE_DO_WYSYLKI, PRODUCTION_HOLD |
| `PRODUCTION_HOLD` | `Hold` | Wstrzymane na produkcji (kontrola jakości) | W_PRODUKCJI |
| `GOTOWE_DO_WYSYLKI` | `ReadyToShip` | Czeka na wysyłkę / odbiór | WYSLANE, ODBIOR_OSOBISTY |
| `WYSLANE` | `Shipped` | Kurier odebrał | DOSTARCZONE |
| `ODBIOR_OSOBISTY` | `PersonalPickup` | Klient odebrał osobiście | ZAKONCZONE |
| `DOSTARCZONE` | `Delivered` | Potwierdzenie z kuriera | ZAKONCZONE, REKLAMACJA |
| `ZAKONCZONE` | `Completed` | Zamówienie zakończone | REKLAMACJA (do 14 dni) |
| `REKLAMACJA` | `Complaint` | Procedura RMA | ZAKONCZONE (po zamknięciu) |
| `SUSPENDED` | `Suspended` | Czasowo wstrzymane | (reversible do ostatniego stanu) |
| `ANULOWANE` | `Cancelled` | Terminal | — |

**Eventy:** `OrderCreated`, `OrderStatusChanged`, `OrderCancelled`, `OrderDuplicated`.

---

## 5. MODULE 4 — PRICING (PricingPipeline)

> ⚠️ **UWAGA:** Tabele `RegulaCenowa`, `IndywidualyCennik`, `RabatDefinicja`, `KosztWlasny` z poprzednich wersji **są USUNIĘTE**. Zostaje jedynie `kalkulacje_ceny` jako snapshot wyniku `PricingPipeline::calculate()`.

Pełna specyfikacja: [07_SILNIK_CENOWY_INTEGRACJA.md](./nowa%20dokumentacja/07_SILNIK_CENOWY_INTEGRACJA.md) (1375 linii).

### 5.1. Typy produktów

```php
enum ProductType: string { case CALCULATED = 'calculated'; case SIMPLE = 'simple'; }
enum EngineType: string { case SHEET = 'sheet'; case LINEAR_METER = 'linear_meter'; case MULTIPAGE = 'multipage'; }
enum PartRole: string { case COMMON = 'common'; case COVER = 'cover'; case INTERIOR = 'interior'; }
```

### 5.2. 12-krokowy pipeline

1. **Engine** — `$this->engines->get($product->engine_type)->calculate(...)` → `EngineResult`.
2. **Extras cost** — parametry (COMMON/COVER/INTERIOR) × `CostPriceTrait` pola.
3. **Product extra cost** — `sumCostFields([$product], ...)`.
4. **Total cost** = materialCost + printCost + extrasCost + productExtraCost.
5. **Margin** (dwupoziomowa): `marginAmount = max(totalCost * margin_base_pct, margin_min_pln)`.
6. **Base price** = totalCost + marginAmount.
7. **Project price** — jeśli `orderProject=true` → z `ProjectPageResolver` + `ProjectSetting`.
8. **Pattern multiplier** — `basePrice * patternMultiplier`.
9. **Time multiplier** — `timeOption.multiplier` × `patternMultipliedPrice`.
10. **ExclusionRule check** — jeśli naruszenie → `throw ExclusionRuleViolated`.
11. **Assembly** `PricingResult` z pełnym breakdownem.
12. **Snapshot** w `kalkulacje_ceny` (listener na `PriceCalculated`).

### 5.3. 3 silniki

- `SheetEngine` — algorytm arkuszowy (imposition, sheets_base/final z odpadem).
- `LinearMeterEngine` — metry bieżące (rolka).
- `MultipageEngine` — cover + interior sections, `details['sections']`.

### 5.4. Resolver cascade

`SettingResolver::resolve(Product $p, string $key)`:
1. `ProductOverride` (per produkt).
2. `PatternSetting` (globalny pattern).
3. `GlobalSetting` (singleton).

### 5.5. Kluczowe tabele (angielskie nazwy)

`global_settings`, `pattern_settings`, `project_settings`, `custom_format_settings`, `media_formats`, `formats`, `materials`, `zadruk_options`, `time_options`, `page_configs`, `parameters`, `parameter_options`, `simple_parameters`, `simple_parameter_options`, `products`, `product_override`, `product_material` (pivot z rolą), `product_zadruk_option`, `product_time_option`, `product_page_config`, `product_parameter` (z rolą), **`product_parameter_option`** (🔥 PK w `CalculationConfig.selectedParameterOptionIds`), `product_simple_parameter`, `product_simple_parameter_option`, `simple_product_discount`, `exclusion_rule`, `material_size`.

### 5.6. `CostPriceTrait` — 8 pól

`cost_per_sheet`, `cost_per_piece`, `cost_per_item`, `cost_per_kg`, `cost_per_m2`, `cost_per_mb`, `margin_base_pct`, `margin_min_pln`.

Trait aplikowany do: `Material`, `ZadrukOption`, `ParameterOption`, `Product`.

### 5.7. Testy

**49 testów portowanych 1:1 z `_nowy_SILNIK_CENOWY/05-06`:**
- `CalculatorTest` (15)
- `SimplePricingTest` (10)
- `PipelineTest` (14)
- `MultipageEngineTest` (10)

**Coverage gate** `app/Services/Pricing/*` ≥80% (CI blocker).

---

## 6. MODULE 7 — DAMS (LOKALNY)

> ⚠️ **Zastępuje** poprzednią specyfikację z S3/MinIO. Aktualnie **wyłącznie lokalny dysk**.

Pełna specyfikacja: [08_DAMS_LOKALNE_PRZECHOWYWANIE_PLIKOW.md](./nowa%20dokumentacja/08_DAMS_LOKALNE_PRZECHOWYWANIE_PLIKOW.md).

### 6.1. Dyski (`config/filesystems.php`)

- `local_private` (default) — `storage/app/private`, perms 0600/0750.
- `local_public` — `storage/app/public` (symlink).
- `tus_temp` — `storage/app/temp/tus` (niedokończone uploady).
- `backup_local` — `/mnt/backup/drukarnia-erp`.

### 6.2. Struktura katalogów

```
storage/app/private/
├── orders/{yyyy}/{mm}/{order_id}/
│   ├── source/       # oryginały klienta
│   ├── proofs/       # proof do akceptacji
│   ├── production/   # finalne produkcyjne
│   └── thumbs/       # sm/md/lg PNG
├── designs/{project_id}/
│   ├── scenes/       # Konva JSON autosave
│   └── exports/      # wyeksportowane PDF CMYK
├── invoices/{yyyy}/{mm}/
├── complaints/{rkl_id}/evidence/
├── shipment_labels/{yyyy}/{mm}/
└── messages/{yyyy}/{mm}/
```

### 6.3. Tabele

`pliki`, `wersje_plikow`, `powiazania_plikow` (polymorphic), `media` (Spatie medialibrary), `temporary_uploads`.

### 6.4. Flow

1. Klient upload przez tus.io (`POST/PATCH /tus/uploads`).
2. `ProcessUploadedFileJob` — SHA-256, dedup, przeniesienie do `local_private`.
3. `GenerateThumbnailsJob` — Ghostscript (PDF → PNG) + ImageMagick resize (sm/md/lg).
5. `FileAccessService::streamForUser` → Nginx `X-Accel-Redirect: /_protected/{path}` (po autoryzacji Laravel policy).
6. `CleanupService` — soft-deleted > 90 dni → fizyczne usunięcie.

### 6.5. Nginx config (kluczowe)

```nginx
location /_protected/ {
    internal;
    alias /var/www/drukarnia-erp/current/storage/app/private/;
}

location /tus/uploads {
    client_max_body_size 5G;
    proxy_request_buffering off;
    try_files $uri /index.php?$query_string;
}
```

### 6.6. Backup off-site (opcjonalny)

- **Tylko jako encrypted backup**, nigdy jako warstwa dostępowa.
- `age` / `gpg` szyfrowanie przed uploadem do Backblaze B2 / S3.
- Nigdy nie traktować S3 jako storage produkcyjnego.

---

## 7. MODULE 10 — FINANCE ORCHESTRATOR

**Tabele:** `dokumenty_finansowe`, `platnosci`, `metody_platnosci`, `webhook_events` (idempotency UNIQUE).

**Typy dokumentów:** proforma, faktura_zaliczkowa, faktura_vat, paragon, faktura_korygujaca.

**Kolumny `dokumenty_finansowe`:** `mpp_flag` bool, `ksef_number` VARCHAR nullable, `link_platnosci` VARCHAR, `retencja_do` DATE (5 lat dla księgowych).

### Flow orkiestracji

```
PriceCalculated
    ↓
Proforma (Subiekt + P24 payment link) → email klienta
    ↓ PaymentReceived (webhook P24)
Faktura Zaliczkowa (B2B only, po wpłacie zaliczki)
    ↓
ShipmentDispatched
    ├── B2B → Faktura VAT → Subiekt → KSeF
    └── B2C → Paragon (lub Faktura na życzenie)
    ↓
Ew. CorrectionIssued (reklamacja → Faktura Korygująca)
```

**MPP flag:** `brutto > 15000 PLN` AND `B2B` AND `containsGoodsFromAppendix15`.

**Webhook security (3-stopniowa weryfikacja):** IP whitelist → Signature (CRC/HMAC) → Idempotency (`webhook_events.provider_event_id` UNIQUE).

---

## 8. NOWE MODUŁY (QUOTES, SHOP, DESIGN EDITOR)

### 8.1. MODULE 5 — QUOTES (Faza 2)

**Tabele:** `wyceny`, `pozycje_wycen`.

**Kluczowe:** `expires_at` DEFAULT `created_at + 14 days`. Publiczny link `/quotes/{token}` do akceptacji.

**Serwisy:** `QuoteGenerator::generate`, `QuoteConverter::convertToOrder` (zachowuje snapshot ceny).

**Eventy:** `QuoteGenerated`, `QuoteExpired`, `QuoteAccepted`.

### 8.2. MODULE 17 — SHOP (Faza 3)

**Tabele:** `koszyki`, `pozycje_koszykow`, `kody_promocyjne`, `produkty_historia_cen` (**Omnibus — najniższa cena z 30 dni**).

**Serwisy:** `CartService`, `CheckoutService`, `PromoCodeValidator`, `OmnibusPriceTracker`.

**Flow:** Guest browse → cart (session_token) → checkout → guest Klient → payment → `GuestConvertedToUser` (magic link).

**UOKiK compliance:**
- Pokazanie netto/brutto/**najniższej ceny 30d**.
- Info o personalizacji (brak 14 dni odstąpienia dla produktów personalizowanych).

### 8.3. MODULE 18 — DESIGN EDITOR (Faza 3)

**Tabele:** `projekty_graficzne`, `wersje_projektow`, `komentarze_projektow`, `szablony_projektow`.

**Tech:** vue-konva + konva.js v9, canvas + warstwy + transformer, undo/redo, keyboard shortcuts.

**Serwisy:**
- `SceneAutoSaver::save` — JSON gzip do `storage/app/private/designs/{id}/scenes/` co 5s.
- `PdfExporter` (job async) — headless Chrome/Puppeteer → **PDF 300 DPI CMYK + 3mm bleed**.
- `TemplateLibraryService` — biblioteka szablonów (wizytówki/ulotki/plakaty).

---

## 9. KATALOG EVENTÓW (≥40)

Pełna lista: [06_WORKFLOW_I_EVENTY.md](./nowa%20dokumentacja/06_WORKFLOW_I_EVENTY.md) sekcja 2.

### Orders
`OrderCreated`, `OrderStatusChanged`, `OrderCancelled`, `OrderDuplicated`.

### CRM
`ClientCreated`, `ClientBlocked`, `LoyaltyTierChanged`, `ClientNipValidated`.

### Pricing
`PriceCalculated`, `PriceRecalculated`.

### DAMS
`FileUploaded`, `FileVersionAdded`, `FileDeleted`.

### Approvals
`ApprovalRequested`, `ApprovalApproved`, `ApprovalRevisionRequested`, `ApprovalRejected`, `ApprovalExpired`, `ApprovalEscalated`.

### Production
`ProductionJobCreated`, `ProductionStarted`, `ProductionStageUpdated` (ShouldBroadcast), `ProductionCompleted`.

### Finance
`ProformaCreated`, `PaymentReceived`, `InvoiceIssued`, `KsefSubmitted`, `CorrectionIssued`.

### Logistics
`ShipmentCreated`, `ShipmentDispatched`, `DeliveryConfirmed`, `DeliveryFailed`.

### Complaints
`ComplaintSubmitted`, `ComplaintApproved`, `ComplaintRejected`, `ReprintOrderCreated`, `RefundIssued`.

### CommHub
`MessageReceived`, `MessageSent`, `SmsSent`, `NotificationCreated` (ShouldBroadcast), `ThreadClosed`.

### Quotes
`QuoteGenerated`, `QuoteExpired`, `QuoteAccepted`.

### Shop
`CartUpdated`, `CheckoutStarted`, `OrderCreatedFromShop`, `GuestConvertedToUser`.

### Design Editor
`DesignSubmittedForApproval`, `DesignExported`.

### System
`ReminderSent`, `OrderSuspended`, `IntegrationFailure`.

---

## 10. BEZPIECZEŃSTWO I RBAC

Pełna specyfikacja: [11_BEZPIECZENSTWO_I_RBAC.md](./nowa%20dokumentacja/11_BEZPIECZENSTWO_I_RBAC.md).

### 6 ról

| Rola | Dostęp |
|------|--------|
| **Admin** | Pełne — konfiguracja systemu, użytkownicy, pricing settings, integracje |
| **Menedżer** | Operacyjne — zamówienia, klienci, produkcja, finanse, raporty |
| **Projektant** | DAMS, Approvals, Inbox (własne wątki), Design Editor |
| **Operator** | Production Kanban (własne etapy), **bez widoczności `cost_*`/`margin_*`** |
| **Księgowość** | Finance, raporty, dokumenty; odczyt zamówień |
| **Klient** (portal) | Własne zamówienia, Approvals, płatności, reklamacje, sklep |

### Kluczowe zabezpieczenia

- **Auth:** Laravel Fortify (email+hasło) + **2FA TOTP** (obligatoryjne Admin/Menedżer, opcjonalne Klient).
- **Session:** Laravel Sanctum (SPA cookies, SameSite).
- **Webhook security:** IP whitelist + signature (CRC/HMAC) + idempotency UNIQUE — PRZED jakąkolwiek logiką.
- **File access:** Nginx `X-Accel-Redirect` + Laravel policy.
- **RBAC:** `spatie/laravel-permission` → `can('module.action')` middleware.
- **Audit log:** `spatie/laravel-activitylog` na wszystkich wrażliwych modelach.
- **RODO:** `ClientAnonymizationService` (prawo do bycia zapomnianym), `ClientExportService` (eksport danych JSON).
- **Rate limiting:** login 5/min, webhooks 100/min, public approval 30/min, API 60/min.

---

## 11. INTEGRACJE ZEWNĘTRZNE

Pełna specyfikacja: [10_INTEGRACJE_ZEWNETRZNE.md](./nowa%20dokumentacja/10_INTEGRACJE_ZEWNETRZNE.md).

Wszystkie adaptery: `App\Integrations\{Nazwa}\{Connector, Adapter, Requests/, Responses/, Contracts/, Exceptions/}` z użyciem **`saloonphp/saloon` v3**.

| Adapter | Operacje | Priorytet |
|---------|----------|-----------|
| **Subiekt nexo** (REST/Sfera) | Kontrahent, Faktury (Proforma/Zaliczkowa/VAT/Paragon/Korekta), Płatności, KSeF | P0 |
| **GUS BIR1** (SOAP) | Walidacja NIP, auto-fill danych firmy | P0 |
| **Przelewy24** (REST v2.1) | Link płatności, webhook CRC | P0 |
| **InPost ShipX** (REST v1) | Paczkomat, kurier, etykieta, tracking | P1 |
| **DPD / DHL / GLS** (REST) | Analogicznie do InPost | P1 |
| **SMSAPI.pl** | SMS transakcyjne | P1 |
| **IMAP** (PHP IMAP / webklex) | Import emaili, polling co 2 min | P0 |
| **OpenAI GPT-4o** | AI Layer (Faza 2+) | P2 |

### Retry policy (wspólny)

```php
public int $tries = 3;
public array $backoff = [0, 30, 300];
public function retryUntil(): \DateTime { return now()->addHours(2); }
```

Po 3 próbach → `failed_jobs` + event `IntegrationFailure` → alert Admin.

---

## 12. OGRANICZENIA PRAWNE (POLSKA)

| Ograniczenie | Konsekwencja architektoniczna |
|--------------|-------------------------------|
| **KSeF** (Krajowy System e-Faktur) | Faktury VAT B2B → KSeF. **MVP/F2:** przez Subiekt nexo. **F4:** własna integracja. |
| **MPP** (Mechanizm Podzielonej Płatności) | `mpp_flag` na fakturze gdy `brutto > 15000 PLN` AND `B2B` AND towar z załącznika 15 VAT. |
| **RODO / GDPR** | `laravel-activitylog`, `ClientAnonymizationService`, eksport JSON, rejestr czynności. |
| **Retencja dokumentów księgowych** | 5 lat → `retencja_do` DATE, cron `PurgeExpiredFinancialDocsJob`. |
| **Rękojmia** | `reklamacje` z SLA 48h response + 14 dni resolution. |
| **UOKiK / Omnibus** | W sklepie: netto + brutto + **najniższa cena z 30 dni** (`produkty_historia_cen.historical_min_price_last_30d`). |
| **Ustawa o prawach konsumenta** | B2C: 14 dni odstąpienia → `cancellable_until` w `zamowienia`. Wyjątek: personalizowane (większość druków) — info w flow. |

---

## 13. FAZY WDROŻENIA

Pełny plan tygodniowy: [`roadmap.md`](./roadmap.md).

| Faza | Zakres | Czas | Tag |
|------|--------|------|-----|
| **0 — Scaffolding** | Breeze + shadcn-vue + Filament + Docker + CI | 3 dni | `v0.0.0-scaffolded` |
| **1 — MVP** | Email → Wycena → Pliki → Produkcja → Wysyłka. Moduły: RBAC, CRM, Orders, Products, Pricing (pełny), DAMS, Production (basic), Finance (Proforma), Logistics (InPost), CommHub, Integrations (GUS/Subiekt stub/P24/InPost). | 10 tyg. | `v1.0.0-mvp` |
| **2 — Portal + Full Pricing UI + Full Finance** | Client Portal, Approvals markup, Full Finance (VAT/Paragon/Korekta + KSeF via Subiekt), DPD/DHL/GLS, SMS, Quotes, AI basic, Web Push. | 8–10 tyg. | `v2.0.0-portal` |
| **3 — Shop + Design Editor + Analytics** | Shop (moduł 17), Design Editor (moduł 18), Complaints full, Reports + Materialized Views, Production full (harmonogram), Loyalty/Inventory full, Security Audit. | 10–12 tyg. | `v3.0.0-shop` |
| **4 — Enterprise** | **Preflight Engine**, multi-tenant, JDF/JMF, native mobile app, własna integracja KSeF, forecasting ML, AI Layer full. | Otwarte (6+ mies.) | 🔒 |

---

## 14. WERYFIKACJA I TESTY

Pełna strategia: [12_STRATEGIA_TESTOWA.md](./nowa%20dokumentacja/12_STRATEGIA_TESTOWA.md).

### Coverage gates (CI blocker)

- `app/Services/Pricing/*` — **≥80%** (49 testów 1:1 z `_nowy_SILNIK_CENOWY`).
- `app/Services/Finance/*` — ≥70%.
- `app/Integrations/*` — ≥60% (Http::fake).
- Reszta `app/` — ≥50%.

### E2E testy per faza

- **Faza 1:** email IMAP → zamówienie → wycena → Proforma → P24 → pliki → produkcja → InPost → completed.
- **Faza 2:** + markup approval + KSeF + DPD.
- **Faza 3:** + sklep guest checkout + Design Editor + reklamacja + dodruk.

### Security audit (Faza 3 końcowa)

- OWASP Top 10 checklist.
- Webhook signature 100% coverage.
- RBAC: test każdej roli z zabronionym dostępem.
- Load test k6: 50 concurrent users, 10 min mixed workload.

---

## 15. KLUCZOWE DECYZJE ARCHITEKTONICZNE (ADR)

Z [01_WIZJA_I_ZALOZENIA.md](./nowa%20dokumentacja/01_WIZJA_I_ZALOZENIA.md) sekcja 8:

1. **Modularny monolit, nie mikrousługi** — 18 modułów jako bounded contexts w jednej bazie kodu.
2. **Inertia.js v3 jako jedyny most FE/BE** — brak równoległego REST API; Filament oddzielną aplikacją Livewire w `/admin`.
3. **PostgreSQL 16 z JSONB** — breakdowny pricingu, specyfikacje produktowe, scena Konva — wszystko jako JSONB.
4. **Redis + Horizon** — kolejki, cache, locks; brak Supervisor/Beanstalkd standalone.
5. **Reverb (nie Pusher)** — Laravel-native WebSocket bez zewnętrznej usługi.
6. **Meilisearch** — full-text search z polskim tokenizerem.
7. **Lokalne storage** — `storage/app/private/` + `tus-php` + `spatie/laravel-medialibrary`. **Bez MinIO/S3.**
8. **Silnik cenowy z `_nowy_SILNIK_CENOWY`** — nie własny ad-hoc; 49 testów 1:1.
9. **`spatie/laravel-model-states`** — FSM 19 stanów zamiast `if/else` na kolumnie `status`.
10. **`brick/math` BigDecimal** — nigdy `float` dla pieniędzy.
11. **shadcn-vue only** — spójność wizualna, semantyczne kolory, dark mode ready.
12. **Saloon v4** — wszystkie integracje HTTP jednolicie.
13. **Scope OUT Faza 1–3:** integracja maszynowa (JDF/JMF) — Faza 4.

---

## 16. DOKUMENTY POKREWNE

- [`nowa dokumentacja/00_INDEX.md`](./nowa%20dokumentacja/00_INDEX.md) — pełna aktualna specyfikacja (14 plików).
- [`roadmap.md`](./roadmap.md) — pokrokowy plan tygodniowy implementacji (v2).
- [`ERP_WORKFLOW.md`](./ERP_WORKFLOW.md) — workflow A→Z zamówienia.
- [`steady-hopping-aurora.md`](./steady-hopping-aurora.md) — stub z linkiem do archiwum.
- [`steady-hopping-aurora.ARCHIVE.md`](./steady-hopping-aurora.ARCHIVE.md) — pełna archiwalna treść poprzedniej wizji (historyczne).
- `_nowy_SILNIK_CENOWY/01..08.md` — źródło portu silnika cenowego (Django → Laravel).

---

**Data aktualizacji:** 2026-04-24.
**Autor zmiany:** Chief Software Architect (AI-assisted).
**Poprzednia wersja:** zastąpiona — różnice w [Appendix E nowego `roadmap.md`](./roadmap.md#appendiksy).
