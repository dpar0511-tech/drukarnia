# DRUKARNIA ERP — ROADMAP IMPLEMENTACJI 2026-2027

> **Wersja:** 3.0 (Consolidated)  
> **Obowiązuje:** 4 fazy w 18–24 miesiące (1 dev full-time + AI assistance)  
> **Status:** Każdy krok = 1 commit + PR + test  
> **Legenda:** [BE]=Backend [FE]=Frontend [DB]=Database [CFG]=Config [TEST]=Test [INT]=Integration

---

## FAZA 1 — MVP: PODSTAWOWY OBIEG ZAMÓWIEŃ (10–12 tygodni)

**Cel:** Email → Wycena → Pliki → Produkcja → Wysłane.

**Metryki sukcasu:** 100% zleceń elektronicznie; 0 papierowych notatek.

### ETAP 1.0 — SETUP ŚRODOWISKA (1 tydzień)

#### Krok 1.0.1 — Inicjalizacja projektu [CFG]

- [ ] `composer create-project laravel/laravel drukarnia-erp`
- [ ] `.env`: PostgreSQL, Redis, MinIO, Queue driver=redis
- [ ] Instalacja core packages:
```bash
composer require \
  inertiajs/inertia-laravel \
  tightenco/ziggy \
  spatie/laravel-model-states \
  spatie/laravel-activitylog \
  spatie/laravel-permission \
  laravel/horizon \
  laravel/reverb \
  laravel/sanctum \
  ankitpokhrel/tus-php
npm install \
  @inertiajs/vue3 \
  vue@3 \
  @vitejs/plugin-vue \
  shadcn-vue \
  tailwindcss \
  typescript
```
- [ ] Konfiguracja Inertia: `app.blade.php`, middleware `HandleInertiaRequests`
- [ ] Konfiguracja Vite: `vite.config.js` + Vue plugin
- [ ] `php artisan horizon:install` + `php artisan reverb:install`
- [ ] Docker Compose: app, nginx, postgres, redis, minio, meilisearch, horizon
- [ ] MinIO: bucket `drukarnia-files` (private policy)
- [ ] Git: initial commit

#### Krok 1.0.2 — Baza danych: fundament [DB]

- [ ] Migracja: `users`, `roles`, `permissions`
- [ ] Seeders: role systemowe (Admin, Menedżer, Projektant, Operator, Księgowość, Klient)
- [ ] Seeder: Admin account (admin@drukarnia.local)

---

### ETAP 1.1 — MODULE 13: ACCESS CONTROL (1.5 tygodnia)

#### Krok 1.1.1 — Backend Auth [BE] [DB]

- [ ] `php artisan fortify:install`
- [ ] Model `User` + relacja `belongsTo(Role)`
- [ ] Model `Role` + hasMany `permissions` (JSONB)
- [ ] Middleware `CheckPermission`
- [ ] Observer `AuditLogObserver` (log model changes)
- [ ] Model `AuditLog`

#### Krok 1.1.2 — Frontend Auth [FE]

- [ ] Strona `/login` (form + error handling)
- [ ] Strona `/dashboard` (placeholder, grid kart per role)
- [ ] `AppLayout.vue`: sidebar nav, navbar + user menu, bell icon
- [ ] Composable `useAuth()` + `can(permission)` helper

#### Krok 1.1.3 — Admin Panel [BE] [FE]

- [ ] CRUD `/admin/users`: index, create, edit, soft-delete
- [ ] CRUD `/admin/roles`: edit permissions
- [ ] Policy `UserPolicy`

#### Krok 1.1.4 — Testy [TEST]

- [ ] Feature: login, deny unauthorized
- [ ] Feature: role-based access

---

### ETAP 1.2 — MODULE 8: COMMUNICATION HUB (2 tygodnie)

#### Krok 1.2.1 — Baza danych [DB]

- [x] Migracja: `watki_komunikacji`, `wiadomosci`, `szablony_wiadomosci`, `powiadomienia`
- [x] Refaktoryzacja: Zgodność z `04_DATABASE_SCHEMA.md` (DAMS, Loyalty, Order expansion)
- [x] Indeksy: `wiadomosci.zewnetrzny_id UNIQUE`, `wiadomosci.watek_id`, `powiadomienia.user_id`

#### Krok 1.2.2 — IMAP Import [BE]

- [ ] Klasa `ImapMailImporter` (PHP IMAP)
- [ ] Job `FetchImapEmails` (Queue)
- [ ] Schedule: co 2 minuty
- [ ] `.env`: IMAP_HOST, PORT, USER, PASS
- [ ] Deduplikacja: `zewnetrzny_message_id` UNIQUE

#### Krok 1.2.3 — Email Parser [BE]

- [ ] Klasa `ProcessIncomingEmail` (Job):
  - Parsuj From, Subject, Body, Attachments
  - Tworzy Watek + Wiadomosc
  - Auto-match klienta (email lookup)
  - Dispatch event `EMAIL_RECEIVED`

#### Krok 1.2.4 — Wysyłka emaili [BE]

- [ ] Klasa `MailSender` (template parser)
- [ ] Event `EmailSent` (write to DB)

#### Krok 1.2.5 — Szablony seed [BE]

- [ ] Seeder: `SzablonWiadomosciSeeder` (9 szablonów)
  - oferta_wyslana, proforma_wyslana, platnosc_otrzymana, prosba_o_pliki, link_akceptacji, etc.

#### Krok 1.2.6 — In-App Notifications [BE] [FE]

- [ ] Model `Powiadomienie`
- [ ] Service `NotificationService::send(User, typ, tytul, tresc, link)`
- [ ] Event `NewNotification` + broadcast Reverb
- [ ] Vue composable `useNotifications()` (subscribe)
- [ ] Bell component: dropdown, unread count

#### Krok 1.2.7 — Frontend Inbox [FE]

- [ ] Strona `/inbox` (list wątków + filters)
- [ ] Strona `/inbox/{id}` (rozmowa, reply field)
- [ ] Komponent `MessageForm.vue` (editor + attach)

#### Krok 1.2.8 — Testy [TEST]

- [ ] Unit: `ImapMailImporter` (mock IMAP)
- [ ] Feature: new email → create wiadomosc

---

### ETAP 1.3 — MODULE 2: CLIENT MANAGEMENT (1.5 tygodnia)

#### Krok 1.3.1 — Baza danych [DB]

- [ ] Migracja: `klienci`, `osoby_kontaktowe`, `tagi`, `klient_tag` (pivot), `poziomy_lojalnosci`

#### Krok 1.3.2 — NIP Validator [BE]

- [ ] Klasa `NipValidator::validate()` (algorytm sumy kontrolnej)
- [ ] Stub `GusApiClient` (do F2)

#### Krok 1.3.3 — Backend CRM [BE]

- [ ] Model: `Klient`, `OsobaKontaktowa`, `Tag`, `PoziomLojalności`
- [ ] Controller `KlientController` (CRUD)
- [ ] Policy `KlientPolicy`
- [ ] Resource `KlientResource`
- [ ] FormRequest `StoreKlientRequest`

#### Krok 1.3.4 — Frontend CRM [FE]

- [ ] Strona `/clients` (table + search + filter)
- [ ] Strona `/clients/create` (form B2B/B2C, NIP field)
- [ ] Strona `/clients/{id}` (card, osoby kontaktowe, historia)
- [ ] Komponenty: `ClientStatusBadge`, `LoyaltyTierBadge`

#### Krok 1.3.5 — Testy [TEST]

- [ ] Unit: `NipValidator`
- [ ] Feature: CRUD klientów

---

### ETAP 1.4 — MODULE 3: ORDER MANAGEMENT (2 tygodnie)

#### Krok 1.4.1 — Baza danych [DB]

- [ ] Migracja: `zamowienia`, `pozycje_zamowienia`, `specyfikacje_druku`, `historia_statusow`
- [ ] Seeder: `StatusDefinitionSeeder` (16 statusów z kodami, kolorami)

#### Krok 1.4.2 — State Machine [BE]

- [ ] Instalacja `spatie/laravel-model-states`
- [ ] Klasy stanów (16): NewState, PricingState, WaitingFilesState, ... , CancelledState
- [ ] TransitionRules (allowed transitions)
- [ ] Observer: status change → `historia_statusow` + event dispatch

#### Krok 1.4.3 — Events [BE]

- [ ] Event klasy: `OrderCreated`, `OrderStatusChanged`, `OrderDuplicated`
- [ ] EventServiceProvider: mapping event → listener
- [ ] Listener: `LogOrderEvent` → write to `historia_statusow`

#### Krok 1.4.4 — Backend Orders [BE]

- [ ] Model `Zamowienie`: relacje, `generateNumber()` (DRK-YYYY-XXXXX), `duplicate()`
- [ ] Controller: index, store, show, update, changeStatus, duplicate
- [ ] Policy `ZamowieniePolicy`
- [ ] Resource `ZamowienieResource`

#### Krok 1.4.5 — Frontend Orders [FE]

- [ ] Strona `/orders` (list + filtry + search)
- [ ] Strona `/orders/create` (wizard: client → positions → spec → deadline)
- [ ] Strona `/orders/{id}` (full card, tabs, sidebar actions)
- [ ] Komponenty:
  - `OrderStatusBadge.vue` (live Reverb update)
  - `StatusTimeline.vue` (historia)
  - `ChangeStatusModal.vue` (transitions)

#### Krok 1.4.6 — Testy [TEST]

- [ ] Unit: state machine (all transitions)
- [ ] Unit: `generateNumber()` (uniqueness, format)
- [ ] Feature: CRUD orders

---

### ETAP 1.5 — MODULE 4: PRICING ENGINE (1.5 tygodnia)

#### Krok 1.5.1 — Baza danych [DB]

- [ ] Migracja: `reguly_cenowe`, `indywidualne_cenniki`, `rabaty_definicje`, `kalkulacje_cen`, `koszty_wlasne`

#### Krok 1.5.2 — Pricing Service [BE]

- [ ] DTO: `PricingInput`, `PricingOutput`
- [ ] Klasa `PricingEngine`:
  - `calculate(PricingInput): KalkulacjaCeny`
  - Load rules, apply modifiers, calculate final price
  - Return full JSONB breakdown
- [ ] Klasa `CostCalculator`:
  - `calculate(Zamowienie): KosztWlasny`
  - material + machine + labor

#### Krok 1.5.3 — Admin Panel [BE] [FE]

- [ ] CRUD `/admin/pricing/rules`
- [ ] CRUD `/admin/pricing/discounts`
- [ ] Frontend: tabela reguł, formularz

#### Krok 1.5.4 — Frontend: Pricing Calc [FE]

- [ ] Tab `Wycena` na karcie zamówienia
- [ ] Button "Oblicz cenę" → Server Action
- [ ] Komponenty:
  - `PricingBreakdown.vue` (breakdown table)
  - `MarginWidget.vue` (cost vs price, widoczne Admin/Menedżer)

#### Krok 1.5.5 — Testy [TEST]

- [ ] Unit: `PricingEngine` (różne nakłady, materiały)
- [ ] Feature: calculate via HTTP

---

### ETAP 1.6 — MODULE 5: DAMS (2 tygodnie)

#### Krok 1.6.1 — Baza danych [DB]

- [ ] Migracja: `pliki`, `wersje_plikow`, `powiazania_plikow` (polymorphic), `temporary_uploads`

#### Krok 1.6.2 — MinIO Config [CFG]

- [ ] `config/filesystems.php` → disk `minio` (S3 driver)
- [ ] Klasa `MinioStorageService`:
  - `store(UploadedFile): string`
  - `getPresignedUrl(string): string`
  - `delete(string): void`

#### Krok 1.6.3 — tus.io Upload [BE]

- [ ] Instalacja `ankitpokhrel/tus-php`
- [ ] Route: `POST/PATCH /uploads/tus`
- [ ] Middleware: auth, throttle
- [ ] Hook `OnUploadComplete` → Job `ProcessUploadedFile`

#### Krok 1.6.4 — Upload Processing [BE]

- [ ] Job `ProcessUploadedFile`:
  - Pobierz z temp storage
  - Wylicz checksum
  - Upload to MinIO
  - Create `Plik` record
  - Trigger: `FILE_UPLOADED` event
- [ ] Job `GenerateThumbnails`:
  - PDF: Ghostscript → PNG
  - Image: ImageMagick resize (sm/md/lg)

#### Krok 1.6.5 — Frontend: Upload Widget [FE]

- [ ] npm: `@uppy/core @uppy/tus @uppy/ui`
- [ ] Komponent `UploadWidget.vue` (drag-drop, progress, file list)
- [ ] `FileCard.vue` (thumbnail, name, size, version)
- [ ] Tab `Pliki` na karcie zamówienia

#### Krok 1.6.6 — PDF Preview [FE]

- [ ] npm: `pdfjs-dist`
- [ ] Komponent `PdfViewer.vue` (load presigned URL, paging, zoom)

#### Krok 1.6.7 — Testy [TEST]

- [ ] Unit: `MinioStorageService` (mock S3)
- [ ] Feature: upload small file → create Plik

---

### ETAP 1.7 — MODULE 9: PRODUCTION ENGINE (1.5 tygodnia)

#### Krok 1.7.1 — Baza danych [DB]

- [ ] Migracja: `zlecenia_produkcyjne`, `etapy_produkcji`, `maszyny`, `operatorzy_produkcji`
- [ ] Seeder: 5 maszyn przykładowych

#### Krok 1.7.2 — Backend Production [BE]

- [ ] Model: `ZlecenieProdukcyjne`, `EtapProdukcji`, `Maszyna`, `OperatorProdukcji`
- [ ] Klasa `ProductionJobFactory` (creates job + etapy from template)
- [ ] Listener `CreateProductionJob` (nasłuchuje `APPROVAL_ACCEPTED`)
- [ ] Controller: index, update status, log time

#### Krok 1.7.3 — Frontend: Kanban Board [FE]

- [ ] npm: `vue-draggable-plus`
- [ ] Strona `/production` (Kanban: Prepress | Druk | Postpass | Gotowe)
- [ ] Komponent `ProductionCard.vue`
- [ ] Buttons: Start / Stop (timer)
- [ ] Real-time update via Reverb

#### Krok 1.7.4 — Testy [TEST]

- [ ] Feature: create job, change stage, log time

---

### ETAP 1.8 — MODUŁ 1: SYSTEM CORE (1 tydzień)

#### Krok 1.8.1 — Scheduler & Automation [BE]

- [ ] Job `CheckOrderTimeouts` (cron hourly):
  - WAITING_PAYMENT > 7 dni → send reminder
  - APPROVAL > 7 dni → suspend
- [ ] Job `RecalculateLoyaltyTiers` (cron nightly)
  - Update `klient.poziom_lojalnosci_id`

#### Krok 1.8.2 — Trigger System [BE]

- [ ] `TriggerDispatcher`: po event → find active triggers → execute
- [ ] Built-in actions: SendEmail, CreateJob, ChangeStatus

#### Krok 1.8.3 — Event Log UI [FE]

- [ ] Komponenty na dashboard (KPI tiles, recent events)

---

### ETAP 1.9 — INTEGRATION LAYER (1 tydzień)

#### Krok 1.9.1 — Queue + Retry Policy [CFG]

- [ ] `config/queue.php`: retry=3, backoff exponential
- [ ] Job `ProcessFailedJob` (move to DLQ)

#### Krok 1.9.2 — Stub Integracje [INT]

- [ ] Klasa `SubiektApiClient` (stub, test mode)
- [ ] Klasa `GusApiClient` (stub, test mode)
- [ ] Klasa `PaymentGateway` (P24/PayU stub)

#### Krok 1.9.3 — Webhook Handling [BE]

- [ ] Route: `POST /webhooks/payment` (P24/PayU callback)
- [ ] Middleware: signature validation
- [ ] Dispatch event `PAYMENT_RECEIVED`

---

### ETAP 1.10 — TESTING & DEPLOYMENT (1 tydzień)

#### Krok 1.10.1 — Test Suite [TEST]

- [ ] Feature tests: full workflow (email → order → shipping)
- [ ] Unit tests: all services, models
- [ ] Coverage: >80%

#### Krok 1.10.2 — Local Docker [CFG]

- [ ] `docker-compose.yml`: all services
- [ ] `.env.example`
- [ ] Dokumentacja: "Jak zaciągnąć i uruchomić"

#### Krok 1.10.3 — Git & CI/CD [CFG]

- [ ] GitHub repo: initial push
- [ ] GitHub Actions: PHPUnit on push
- [ ] `.github/workflows/test.yml`

---

**FAZA 1 — SUMMARY**

- 10 etapów
- ~70 kroków
- ~12 tygodni (1 dev full-time)
- Metryka: Email → Zamówienie → Produkcja → Wysłane (bez papieru)

---

## FAZA 2 — ZAAWANSOWANE (8–10 tygodni)

**Cel:** Preflight, Approvals, Complaints, Logistics, Inventory sync.

### ETAP 2.1 — MODULE 6: PREFLIGHT ENGINE

- [ ] Job `PreflightFile` (po FILE_UPLOADED)
- [ ] Integracja: callas pdfToolbox CLI (lub pdfcpu stub)
- [ ] Checks: 300 DPI, CMYK, spady, fonty, trim box
- [ ] Report: JSON w RaportPreflight
- [ ] Event: PREFLIGHT_PASSED / FAILED / WARNING
- [ ] Frontend: status badge na File card

### ETAP 2.2 — MODULE 7: APPROVAL SYSTEM

- [ ] Model `ZadanieAkceptacji`, `KomentarzAkceptacji`
- [ ] Listener: `PREFLIGHT_PASSED` → create ZadanieAkceptacji
- [ ] Portal klienta (simplistic): open PDF + 3 buttons
- [ ] Annotacje na PDF (placeholder, full F3)
- [ ] Event: APPROVAL_ACCEPTED / REJECTED / REVISION_REQUESTED
- [ ] Auto-reminder (48h przed wygaśnięciem)
- [ ] Runda limit: runda ≥ 4 → APPROVAL_ESCALATED

### ETAP 2.3 — MODULE 10: SHIPPING & LOGISTICS

- [ ] Model `Wysylka`, `AdresWysylki`
- [ ] Listener: `PRODUCTION_COMPLETED` → allow select kurier
- [ ] Form: choose kurier (InPost/DPD/etc), address, pickup time
- [ ] Job `GenerateShippingLabel`:
  - API call (stub, real integracja F3)
  - Etykieta PDF → MinIO
  - tracking_url
- [ ] Event: SHIPMENT_CREATED, SHIPMENT_DELIVERED

### ETAP 2.4 — MODULE 11: COMPLAINT & REPRINT

- [ ] Model `Reklamacja`, `DowodReklamacji`
- [ ] Listener: `COMPLETED` → klient może zgłosić (portal)
- [ ] States: CREATED → IN_REVIEW → ACCEPTED/REJECTED → REPRINT
- [ ] Auto-copy order (jeśli recognized)
- [ ] Dashboard: complaint stats

### ETAP 2.5 — INVENTORY SYNC

- [ ] Klasa `InventorySync` (stub Subiekt API)
- [ ] Cron: co 1h pull magazyn z Subiekt
- [ ] Materiał availability check (preflight wyceny)
- [ ] Alert: niski stan papierów

### ETAP 2.6 — ADVANCED NOTIFICATIONS

- [ ] Webhook: order status change → email menedżera (priority)
- [ ] WebSocket: live badge updates
- [ ] SMS (stub): kluczowe statusy

---

**FAZA 2 — SUMMARY**
- 6 etapów
- ~40 kroków
- ~10 tygodni
- Metryka: 0 reklamacji z powodu preflightu; 100% na time delivery

---

## FAZA 3 — FULL STACK: SHOP + DESIGN EDITOR + FINANCE (12–14 tygodni)

**Cel:** Sklep publiczny, portal klienta, edytor projektów, automatyczne faktury.

### ETAP 3.1 — MODULE 17: SHOP & PORTAL KLIENTA (4 tygodnie)

#### Część 1: Katalog & Configurator

- [ ] Tabela `produkty` z parametrami (JSON)
- [ ] Strony: /shop/catalog, /shop/{product}, /shop/{product}/configurator
- [ ] Web2Print calculator (reuse PricingEngine)
- [ ] Live preview (canvas mockup lub 3D, F4)
- [ ] Koszyk (session/localStorage)

#### Część 2: Checkout (Guest + Registered)

- [ ] Checkout flow: cart → address → payment → confirmation
- [ ] Guest checkout: email only (flag `guest_order=true`)
- [ ] Magic link rejestracji (email verification)
- [ ] Registered: auto-fill address from profile
- [ ] Payment gateway integration (P24/PayU stub)

#### Część 3: Portal Klienta

- [ ] Login page (magic link lub password)
- [ ] Dashboard: my orders (list)
- [ ] Order detail: status, files, tracking
- [ ] Repeat order: "Order again with same spec"
- [ ] My account: profile, addresses, payment methods
- [ ] Notifications: email + in-app

#### Część 4: Frontend

- [ ] SSR dla Catalog (SEO)
- [ ] responsive design (mobile-first)
- [ ] Dark mode toggle

### ETAP 3.2 — MODULE 18: DESIGN EDITOR (5 tygodni)

#### Część 1: Canvas Engine

- [ ] npm: konva, vue-konva
- [ ] Strona: /shop/{product}/design-editor
- [ ] Canvas: drag-drop objects
- [ ] Toolbar: text, shapes, image upload
- [ ] Properties panel: color, font, size, etc.
- [ ] Undo/Redo (history)

#### Część 2: Templates & Assets

- [ ] Tabela `szablony_projektow` (JSON template)
- [ ] Template library: "Wizytówka lekarza", "Ulotka restauracji", etc.
- [ ] Asset upload: logo, photos
- [ ] Font selection (Google Fonts lub Adobe Fonts API, F3.x)

#### Część 3: Preview & Export

- [ ] Front/back preview (dla dwustronnych)
- [ ] Export to JSON (save project)
- [ ] Export to PDF:
  - Headless Chromium + canvas rendering
  - CMYK 300 DPI, spady 3mm, linie cięcia (w PDF markach)

#### Część 4: Project Persistence

- [ ] Model `Projekt` (DAMS + JSON state)
- [ ] Wersjonowanie projektów
- [ ] Autosave (debounce 5s)
- [ ] Share link (public/private)

### ETAP 3.3 — MODULE 14: FINANCE ORCHESTRATOR (4 tygodnie)

#### Część 1: Document Lifecycle

- [ ] Model `DokumentFinansowy` (typ, numer_subiekt, kwota, status)
- [ ] Job `CreateProformaDocument`:
  - Event `PRICING_CALCULATED` → create Proforma
  - Wygeneruj PDF (template)
  - Send email: "Wycena gotowa: [link do płatności]"
  - status: WAITING_PAYMENT

#### Część 2: Payment Flow

- [ ] Webhook handler: P24/PayU callback
- [ ] Event `PAYMENT_RECEIVED` → Job `CreateZaliczkowaDocument`
  - Create Faktura Zaliczkowa (JSON template)
  - Send to Subiekt (API, real integracja)
  - Update order status: WAITING_FILES

#### Część 3: Shipment & Final Invoice

- [ ] Job: `PRODUCTION_COMPLETED` → check status
- [ ] Job: `SHIPMENT_DELIVERED` → create Faktura VAT
  - IF B2B >15k PLN: generate MPP headers, send to KSeF
  - Else: standard invoice → Subiekt

#### Część 4: Corrections & RMA

- [ ] Job: `COMPLAINT_ACCEPTED` → create Faktura Korygująca
- [ ] Reprint order: track second shipment

### ETAP 3.4 — INTEGRACJE PRODUKCYJNE (3 tygodnie)

#### Subiekt nexo API

- [ ] Klasa `SubiektApiClient` (real endpoints)
- [ ] CRUD: Klienci, Faktury, Magazyn
- [ ] Sync: daily import nowych produktów/materiałów
- [ ] Error handling + retry

#### GUS API (Real)

- [ ] Klasa `GusApiClient` (SOAP BIR1 integration)
- [ ] Validate NIP on client create/edit
- [ ] Cache results (6 months)
- [ ] Webhook: re-validate quarterly

#### Payment Gateways

- [ ] Przelewy24 API (real endpoints)
- [ ] PayU API (stub/optional)
- [ ] Webhook signature validation
- [ ] Status polling (fallback)

#### Kurierzy API

- [ ] InPost ShipX (real)
- [ ] DPD (stub F3.x)
- [ ] Etykieta PDF generation
- [ ] Tracking webhook

### ETAP 3.5 — KPI DASHBOARDS (2 tygodnie)

- [ ] Admin dashboard: KPI tiles (orders/day, avg price, margin%, delivery rate)
- [ ] Charts: orders per channel, status distribution, top clients
- [ ] Operator dashboard: today's tasks, completion status
- [ ] Finance dashboard: revenue/month, invoice aging, payment methods

---

**FAZA 3 — SUMMARY**
- 5 etapów
- ~50 kroków
- ~14 tygodni
- Metryka: 70% zamówień przez sklep; Shop revenue >20% total

---

## FAZA 4 — OPTIMIZATION & AI (6–8 tygodni)

**Cel:** AI Preflight, Mobile app, Advanced Analytics, Multi-tenant (future).

### ETAP 4.1 — AI LAYER (3 tygodnie)

- [ ] OpenAI GPT-4o integration (real API keys)
- [ ] Auto-brief extractor (parse email → propose spec)
- [ ] AI suggestions: "Similar orders by this client" → copy params
- [ ] Chatbot: "What's my order status?" (Reverb WebSocket)
- [ ] Context window: max 4k tokens (anonymize PII)

### ETAP 4.2 — AI PREFLIGHT (2 tygodnie)

- [ ] Integracja callas pdfToolbox (paid license)
- [ ] Rules engine: custom rules per material/product
- [ ] Auto-fix suggestions (dla prostych błędów)
- [ ] Confidence score + manual review toggle

### ETAP 4.3 — PWA & OFFLINE (1.5 tygodnia)

- [ ] Service Worker + offline cache
- [ ] Mobile-optimized UI
- [ ] Camera: capture photos (complaints)
- [ ] Geolocation: delivery address autocomplete

### ETAP 4.4 — ANALYTICS & BI (1.5 tygodnia)

- [ ] Materialized views (PG) dla KPI
- [ ] Meilisearch integration: fast search (orders, clients, files)
- [ ] Export: CSV/PDF reports
- [ ] Forecast: predict demand per product (ML, F5+)

---

**FAZA 4 — SUMMARY**
- 4 etapy
- ~30 kroków
- ~8 tygodni
- Metryka: 95% preflight pass rate; AI suggestions = +15% repeat orders

---

## TIMELINE PODSUMOWANIE

| Faza | Opis | Czas | Status |
|------|------|------|--------|
| 1.0–1.10 | MVP: Email → Prod | 12 w | 📍 START HERE |
| 2.1–2.5 | Preflight + Approval + Logistics | 10 w | Q3 2026 |
| 3.1–3.5 | Shop + Editor + Finance Auto | 14 w | Q4 2026 |
| 4.1–4.4 | AI + PWA + Analytics | 8 w | Q1 2027 |
| **Total** | **Full system** | **44 w (10.5 m)** | |

Estimate (1 dev full-time + AI help): **12–16 месяцы** (slack na bugs, meetings, learning curve).

---

## KRYTYCZNE KAMIENIE MILOWE (go/no-go)

| Milestone | Warunek sukcesu | Faza |
|-----------|-----------------|------|
| **M1: Email Processing** | 100% emaili parsují bez błędów | F1 |
| **M2: MVP Live** | 5 test orders end-to-end bez booleans | F1 |
| **M3: Preflight** | 95% plików przechodzi auto-check | F2 |
| **M4: Production Go-Live** | Używana codziennie przez drukarnię | F2 |
| **M5: Shop Launch** | First customer order from public shop | F3 |
| **M6: Finance Locked** | 100% faktury automatyczne | F3 |

---

## BEST PRACTICES REPO

```
drukarnia-erp/
├── README.md (start here)
├── CONTRIBUTING.md
├── docker-compose.yml
├── .env.example
├── app/
│   ├── Models/ (Eloquent)
│   ├── Http/Controllers/
│   ├── Services/ (business logic)
│   ├── Jobs/ (queued tasks)
│   ├── Events/ (domain events)
│   ├── Listeners/
│   └── States/ (FSM states)
├── database/
│   ├── migrations/
│   └── seeders/
├── resources/
│   ├── views/ (blade)
│   └── js/
│       ├── Pages/ (Inertia pages)
│       ├── Components/
│       │   └── ui/ (shadcn-vue)
│       ├── Composables/
│       └── Layouts/
├── routes/ (api.php, web.php)
├── tests/
│   ├── Feature/ (end-to-end)
│   └── Unit/ (logic)
└── docs/
    ├── ARCHITECTURE.md
    ├── API.md
    └── DEPLOYMENT.md
```

### Git Workflow

```bash
# Feature branch
git checkout -b feat/module-name-step

# Commit (meaningful messages)
git commit -m "feat(module-3): add duplicate order button"

# PR review
git push origin feat/module-name-step

# Merge after approval
# CI/CD runs tests automatically
```

---

## KLUCZE DO SUKCESU

1. **Commit daily** — mały PR = szybka review = mniejszy merge conflict
2. **Test first** — unit test → TDD = mniej bugów w F3
3. **Use Horizon** — inspect queues, failed jobs, retries
4. **Keep MASTER_PLAN.md updated** — dokumentacja = dokumentacja kodu
5. **Backup often** — `pg_dump` + S3 (Backblaze B2)
6. **Ask questions** — AI (Claude/Gemini) mieć dostęp do kodu

---

**Roadmap wersja:** 3.0 Consolidated  
**Ostatnia aktualizacja:** 2026-04-20  
**Autorzy:** Claude + Zespół Drukarni
