# DRUKARNIA ERP 2026 — MASTER PLAN SKONSOLIDOWANY

> **Wersja:** 3.0 (Consolidated)  
> **Data:** 2026-04-20  
> **Język:** Polski  
> **Status:** Dokument kompilacyjny — źródło prawdy dla całego projektu  
> **Ostatnia aktualizacja:** Połączenie 4 dokumentów strategicznych w jeden ujednolicony plan

---

## SPIS TREŚCI

1. [Executive Summary](#executive-summary)
2. [Audyt krytyczny i wąskie gardła](#audyt-krytyczny-i-wąskie-gardła)
3. [Wizja i cele SMART](#wizja-i-cele-smart)
4. [Stos techniczny](#stos-techniczny)
5. [Architektura 18 modułów](#architektura-18-modułów)
6. [Kanały wejścia zleceń](#kanały-wejścia-zleceń)
7. [Przepływ zdarzeń domenowych](#przepływ-zdarzeń-domenowych)
8. [Decyzje architektoniczne (ADR)](#decyzje-architektoniczne)
9. [Szczegółowy opis modułów](#szczegółowy-opis-modułów)
10. [Workflow end-to-end (scenariusze A→Z)](#workflow-end-to-end)
11. [Integracje zewnętrzne](#integracje-zewnętrzne)
12. [Bezpieczeństwo i zgodność](#bezpieczeństwo-i-zgodność)

---

## EXECUTIVE SUMMARY

### Co to jest DRUKARNIA ERP?

System zarządzania przedsiębiorstwem dla polskiej drukarni cyfrowej i offsetowej obsługującej klientów B2B i B2C. Konwertuje chaotyczny przepływ mailowy w scentralizowany, automatyzowany obieg zamówień od wyceny do wysyłki i obsługi reklamacji.

### Kluczowe problemy którymi się zajmujemy

| Problem | Wpływ | Rozwiązanie |
|---------|--------|------------|
| Wycenianie trwa 15–60 min | 40% klientów rezygnuje w międzyczasie | Web2Print calculator (< 90 s) |
| Brak centralnego backlogu produkcji | Maszyny pracują nieskoordynowanie | Kanban board z całą produkcją |
| Klient nie wie na jakim etapie jest zamówienie | Klient dzwoni 3–5 razy dziennie | Portal klienta + notyfikacje real-time |
| Reklamacje giną, brak śladu akceptacji | Straty finansowe, brak dowodu | Moduł Complaint + e-signature approval |
| Ręczne faktury w Subiekt (podwójna praca) | Błędy, opóźnienia w rozliczeniach | Finance Orchestrator + automatyczne dokumenty |
| Operatorzy wpisują parametry ręcznie | Błędy, duplikacja nakładu | Design Editor online + auto-parametryzacja |

### Deliverables tego planu

1. ✅ Pełny opis architektury, 18 modułów, integracji (ten dokument)
2. ✅ Roadmapa implementacji 4 faz z konkretnymi krokami
3. ✅ Skonsolidowany schemat bazy danych
4. ✅ Mapa API i routingu
5. ✅ Wytyczne dla developera onboarding

---

## AUDYT KRYTYCZNY I WĄSKIE GARDŁA

### 11 krytycznych luk w oryginalnym planie

#### 1. Brak procesu Preflight — krytyczny błąd w poligrafii

**Problem:** Plik przechodzi od klienta do designera i od razu do akceptacji — bez weryfikacji technicznej.

**Skutek:** Operator drukuje wadliwy plik → strata materiałów → reklamacja → koszt.

**Rozwiązanie:** Moduł Preflight Engine (Faza 2) automatycznie weryfikuje:
- Rozdzielczość ≥ 300 DPI
- Spady (bleed) ≥ 3 mm
- Przestrzeń kolorów CMYK (nie RGB)
- Fonty osadzone (embedded)
- Linie cięcia, lakierowania, tłoczenia

**Integracja:** callas pdfToolbox CLI (licencja ~1500€/rok) lub open-source `pdfcpu`.

---

#### 2. Brak modułu dokumentów finansowych

**Problem:** System deleguje "finansy do Subiekt" — ale nie definiuje, kiedy i jaki dokument.

**Skutek:** Brak synchronizacji, duplikowanie czynności, błędy VAT.

**Rozwiązanie:** Finance Orchestrator (Moduł 14) decyduje o przepływie:

```
Kalkulacja ceny (PRICING_CALCULATED) 
  ↓ 
Proforma (do klienta, żądanie zapłaty)
  ↓ 
Płatność wpłynęła (PAYMENT_RECEIVED webhook)
  ↓ 
Faktura Zaliczkowa (obowiązek VAT) → Subiekt
  ↓ 
Produkcja
  ↓ 
Wysyłka
  ↓ 
Faktura VAT → Subiekt + KSeF (jeśli B2B >15k PLN)
  ↓ 
Możliwa Faktura Korygująca (reklamacja/zwrot)
```

---

#### 3. Kompletny brak modułu logistyki i wysyłki

**Problem:** Status "Wysłane" — ale brak integracji z kurierami, generowania listów, śledzenia.

**Rozwiązanie:** Shipping & Logistics (Moduł 10):
- InPost ShipX API (Paczkomaty — 60% rynku PL)
- DPD / DHL / GLS API
- Generowanie etykiet
- Webhook tracking

---

#### 4. Naiwna obsługa plików — brak strategii dla dużych plików

**Problem:** "Zbieramy wszystkie pliki" — ale brak resumable upload, CDN, miniatur.

**Rozwiązanie:** DAMS (Moduł 5) z:
- tus.io resumable upload (brak limitów rozmiaru, wznawialność)
- MinIO S3-compatible storage (on-prem)
- Automatyczne miniatury (Ghostscript + ImageMagick)
- Versionning (v1, v2, v3...)

---

#### 5. Niekompletna maszyna stanów

**Problem:** Brakuje statusów (Oczekiwanie na płatność, Preflight, Do wysyłki, Reklamacja, Wstrzymane).

**Rozwiązanie:** 16 statusów (patrz sekcja State Machine):
```
NEW → PRICING → WAITING_FILES → WAITING_PAYMENT → DESIGNING
→ APPROVAL → REVISION ↔ APPROVED → PRODUCTION → DONE
→ SHIPPING → SHIPPED → COMPLETED → COMPLAINT
SUSPENDED (gwiazda) ↔ dowolny
CANCELLED (gwiazda) ← każdy przed PRODUCTION
```

---

#### 6. Brak workflow reklamacji

**Problem:** Klient zgłasza wadę → system nie wie co robić.

**Rozwiązanie:** Complaint & Reprint (Moduł 11) ze stanami:
- COMPLAINT_CREATED → W rozpatrywaniu
- COMPLAINT_RESOLVED → Uznana/Odrzucona
- Auto-dodruk (kopia oryginalnego zamówienia)
- Raportowanie (% reklamacji per operator)

---

#### 7. Brak walidacji NIP/GUS

**Problem:** Operator ręcznie wpisuje dane firmy B2B → błędy, duplikacja.

**Rozwiązanie:** Client Management (Moduł 2) — walidacja NIP (suma kontrolna) + API GUS:
- Pobierz REGON, nazwę firmy, adres z BIR1
- Auto-uzupełnienie
- Validation webhook (co 6 m-cy)

---

#### 8. "Wszystko przez eventy" — brak strategii resilience

**Problem:** Event system bez retry policy → system "zacina się" przy każdym timeout.

**Rozwiązanie:** Laravel Queues + Redis:
- Retry policy: 3x exponential backoff
- Failed Jobs table
- Dead Letter Queue
- Dashboard (Horizon)

---

#### 9. Brak powtarzalnych zamówień (Repeat Orders)

**Problem:** "To samo co ostatnio, ale 2000 szt." — system nie wspiera kopiowania.

**Rozwiązanie:** Order Management (Moduł 3):
- `Order.duplicate()` — kopiuje specyfikacje i parametry
- Archiwum specyfikacji per klient
- Auto-wycena na bazie historii

---

#### 10. Brak notyfikacji w aplikacji

**Problem:** Menedżer nie wie o nowym emailu, chyba że odświeży stronę.

**Rozwiązanie:** Communication Hub (Moduł 8) + Laravel Reverb:
- WebSocket real-time
- Bell icon w navbar (licznik)
- In-app notifications
- Web Push (opcjonalnie Faza 2)

---

#### 11. Brak kalkulacji kosztów własnych

**Problem:** Pricing Engine liczy cenę DLA klienta — ale nie koszt wytworzenia.

**Rozwiązanie:** Cost Calculator (część Modułu 4):
- Materiał koszt
- Maszyna (koszt/h)
- Robocizna
- Realna marża per zamówienie
- Widoczne tylko dla Admin/Menedżer

---

## WIZJA I CELE SMART

### Wizja

**Drukarnia pracuje 100% cyfrowo. Brak papieru od wyceny do wysyłki. Klient widzi wszystko w portalu. Każdy pracownik wie dokładnie co robić. Finanse są automatyczne.**

### Cele mierzalne (SMART)

| # | Cel | Metryka | Termin | Status |
|---|-----|---------|--------|--------|
| C1 | Redukcja czasu wyceny | 15 min → < 90 s | FAZA 2 | Wymaga Web2Print |
| C2 | Samoobsługa cenowa dla B2C | ≥ 70% zamówień bez operatora | FAZA 3 | Sklep + Design Editor |
| C3 | Centralny backlog produkcji | 100% zleceń na Kanbanie | FAZA 2 | Production Engine |
| C4 | E-signature approval | Każde zlecenie z IP+timestamp | FAZA 2 | Approval System |
| C5 | Automatyczne faktury | Bez udziału księgowej | FAZA 3 | Finance Orchestrator |
| C6 | Portal klienta | 50% powracających loguje się | FAZA 3 | Shop + Portal |
| C7 | Design Editor online | 20% wizytówek w edytorze | FAZA 3 | Module 18 |
| C8 | Zgodność KSeF/MPP | 100% B2B >15k z MPP | FAZA 3 | Finance Orchestrator |

---

## STOS TECHNICZNY

### Backend

| Warstwa | Technologia | Wersja | Powód |
|---------|-------------|--------|-------|
| Runtime | PHP | 8.3 | JIT, typed properties, readonly classes, fibers |
| Framework | Laravel | 11 | Slim, modular, rich ecosystem |
| DB | PostgreSQL | 16 | JSONB, Generated Columns, partycjonowanie |
| Cache/Queues | Redis | 7 | Horizon, cache:lock, priority queues |
| WebSocket | Laravel Reverb | 1 | Native Laravel, pushable events |
| Search | Meilisearch | 1.x | Self-hosted, typo-tolerant, Polish stemmer |
| Storage | MinIO | latest | On-prem S3, duży przepustowość |
| State Machine | spatie/laravel-model-states | 2 | 16 stanów, FSM pattern |
| Audit Log | spatie/laravel-activitylog | 4 | RODO, zmiany modeli |
| Upload | ankitpokhrel/tus-php | 2 | Resumable, chunked, fault-tolerant |
| PDF/Thumbnails | Ghostscript + ImageMagick | 10+7 | Konwersja, resize |
| Auth | Sanctum + Fortify | 4+1 | API tokens, 2FA-ready |
| Permissions | spatie/laravel-permission | 6 | Roles & permissions |

### Frontend

| Warstwa | Technologia | Wersja | Powód |
|---------|-------------|--------|-------|
| Framework | Vue 3 | 3.4+ | Composition API, `<script setup>` |
| Bridge | Inertia.js | v3 | SPA bez REST duplication |
| CSS | Tailwind CSS | 3.x | Utility-first, dark mode |
| UI Kit | shadcn-vue | latest | Spójne komponenty (STRICT) |
| Icons | lucide-vue-next | latest | 4000+ ikon, konsistentne |
| Canvas | Konva.js + vue-konva | 9+3 | Design Editor (Moduł 18) |
| Forms | vee-validate + zod | 4+3 | Validation, type-safe |
| Charts | Chart.js + vue-chartjs | 4+5 | KPI, analytics |
| PDF Viewer | PDF.js | 4 | Preview, paging, zoom |
| WebSocket | laravel-echo + pusher-js | 2+8 | Real-time |
| Build | Vite | 5 | HMR, fast builds |

### DevOps & Infrastruktura

| Komponent | Technologia | Wersja |
|-----------|-------------|--------|
| Deployment | Laravel Octane + FrankenPHP | latest |
| CI/CD | GitHub Actions | latest |
| Monitoring | Laravel Horizon + Pulse | latest |
| Logs | Laravel Log + File rotation | daily |
| Backup | pg_dump + rsync | cron daily |
| Serwer prod | Ubuntu 24.04 LTS | 24.04 |
| RAM/vCPU/SSD | 16 GB / 8 cores / 500 GB | prod spec |

---

## ARCHITEKTURA 18 MODUŁÓW

### Struktura logiczna

```
┌──────────────────────────────────────────────────────────┐
│          WARSTWA DOSTĘPU (ACCESS CONTROL — 13)           │
├──────────────────────────────────────────────────────────┤
│                                                          │
│  COMM HUB (8) ──→ CRM (2) ──→ ORDER MGT (3)            │
│      │                           │                      │
│      │               ┌───────────┼───────────┐          │
│      │               ▼           ▼           ▼          │
│      │         PRICING(4)   DAMS(5)   APPROVAL(7)       │
│      │         [Web2Print]          [e-signature]       │
│      │               │           │           │          │
│      │               ▼           ▼           │          │
│      │         PREFLIGHT(6) ← ───────────────┘          │
│      │               │                                  │
│      │               ▼                                  │
│      │         FINANCE(14)                              │
│      │         [Orchestrator]                           │
│      │               │                                  │
│      │               ▼                                  │
│      │      PAYMENT GATE(15)                            │
│      │               │                                  │
│      │               ▼                                  │
│      │         PRODUCTION(9)                            │
│      │         [Kanban]                                 │
│      │               │                                  │
│      │               ▼                                  │
│      │         SHIPPING(10)                             │
│      │         [Kurierzy API]                           │
│      │               │                                  │
│      │               ▼                                  │
│      │         COMPLAINT(11)                            │
│      │         [RMA Workflow]                           │
│      │                                                  │
│   AI LAYER (12) ─── reads context ───────────────────────
│                                                          │
│   INTEGRATION LAYER ── Subiekt, GUS, Kurierzy, P24 ─────
│                                                          │
│   SHOP (17) ─── Web2Print + Portal klienta ──────────────
│   DESIGN EDITOR (18) ─── Konva.js + vue-konva ──────────
│                                                          │
│   ANALYTICS (16) ─── read-only reporting ────────────────
└──────────────────────────────────────────────────────────┘
```

### Zmiany względem oryginalnego MASTER_PLAN

| Zmiana | Wcześniej | Teraz | Faza |
|--------|----------|--------|------|
| **Pricing Engine** | RegułaCenowa + RabatDefinicja (prymitywne) | Web2Print (11-krokowy pipeline) + overlays | F2 |
| **Nowy: Sklep** | Brak | Shop (17) + Portal klienta | F3 |
| **Nowy: Design Editor** | Brak | Module 18 (Konva.js + Vue) | F3 |
| **Nowy: Finance Orch.** | Brak | Module 14 (automatyczne dokumenty) | F3 |
| **Preflight** | Odłożony do F4 | Wyciągnięty do F2 (Moduł 6) | F2 |
| **Complaint RMA** | Brak | Moduł 11 z własnymi stanami | F2 |
| **Total Modules** | 14 + 1 (deferred) | 18 (aktywne) | All |

---

## KANAŁY WEJŚCIA ZLECEŃ

Każde zamówienie powinno być na **jednym z tych kanałów**:

| Kanał | Opis | Moduł źródłowy | `order.channel` | Operator |
|-------|------|----------------|-----------------|----------|
| Operator manual | Ręcznie w panelu ERP | Order Management (3) | `manual` | Admin/Menedżer |
| Email parser | Import via IMAP + OpenAI brief | Communication Hub (8) | `email` | System |
| Telefon | Ręcznie po rozmowie | Communication Hub (8) | `phone` | Admin/Menedżer |
| **Shop — guest** | Checkout bez konta | **Shop (17)** | `shop_guest` | Guest |
| **Shop — zalogowany** | Checkout z kontem | **Shop (17)** | `shop_user` | Registered Client |
| **Portal repeat** | "Zamów jeszcze raz" | Portal w Shop (17) | `portal_repeat` | Registered Client |
| API B2B | Integracja extern. (agencje) | API (REST Sanctum) | `api` | Partner API |
| Quote accepted | Zaakceptowana wycena | Quotes (5) | `quote` | Client Portal |

---

## PRZEPŁYW ZDARZEŃ DOMENOWYCH

### Core Events (zawsze emitowane)

```
ORDER_CREATED
  ↓
ORDER_STATUS_CHANGED
  ├→ PRICING_STARTED
  ├→ PRICING_CALCULATED
  ├→ FILE_UPLOADED → FILE_VERSION_CREATED
  ├→ PREFLIGHT_PASSED / PREFLIGHT_FAILED
  ├→ APPROVAL_REQUESTED
  ├→ APPROVAL_ACCEPTED / APPROVAL_REJECTED / APPROVAL_REVISION_REQUESTED
  ├→ APPROVAL_ESCALATED (runda ≥ 4)
  ├→ PAYMENT_RECEIVED
  ├→ PRODUCTION_JOB_CREATED
  ├→ PRODUCTION_STARTED → PRODUCTION_STAGE_COMPLETED → PRODUCTION_COMPLETED
  ├→ SHIPMENT_CREATED → SHIPMENT_DELIVERED
  ├→ COMPLAINT_CREATED → COMPLAINT_RESOLVED
  │
  └→ ORDER_SUSPENDED / ORDER_CANCELLED
```

### Communication Events

```
EMAIL_RECEIVED → [create/match client] → EMAIL_SENT
SMS_SENT
REMINDER_SENT (48h przed wygaśnięciem approval linku)
NOTIFICATION_SENT (WebSocket → Bell icon)
```

### Finance Events

```
PROFORMA_CREATED → [mail do klienta]
PAYMENT_RECEIVED → [webhook z P24/PayU]
INVOICE_CREATED (Faktura Zaliczkowa) → [send to Subiekt]
INVOICE_VAT_CREATED (Faktura VAT) → [send to Subiekt + KSeF]
INVOICE_CORRECTED (Faktura Korygująca) → [send to Subiekt + KSeF]
```

### Trigger Automation (jeśli event → wykonaj akcję)

```
ORDER_CREATED → Job: SendWelcomeEmail
PRICING_CALCULATED → Job: CreateProformaDocument
APPROVAL_ESCALATED → Trigger: NotifyManager (priority)
PAYMENT_RECEIVED → Job: UnlockProduction
PRODUCTION_COMPLETED → Trigger: CheckShippingMethod
COMPLAINT_CREATED → Trigger: AutoCreateReprint (copy order)
ORDER_SUSPENDED → Job: SendEscalationReminder
```

---

## DECYZJE ARCHITEKTONICZNE

### ADR-001: Inertia.js zamiast REST API

**Decyzja:** Inertia v3 dla całej warstwy ERP (backend + portal).

**Pros:**
- Brak dublowania walidacji (backend = frontend)
- Vue komponenty native
- useForm Inertii = mniej boilerplate

**Cons:**
- Mniej flexible dla mobilu (ale PWA wystarczy)

**Konsekwencje:** Brak OpenAPI spec; Ziggy musi być zsynchronizowany.

---

### ADR-002: PostgreSQL 16 zamiast MySQL

**Decyzja:** PostgreSQL dla wszystkich środowisk.

**Pros:**
- JSONB (price_breakdown, product_parameters)
- Generated Columns (net_price = gross_price / 1.23)
- Partycjonowanie (activity_log po dacie)
- Window functions (ranking, ROW_NUMBER)

**Cons:**
- Wymaga phpredis extension
- Dev testuje na SQLite (migracje mogą się rozbić)

---

### ADR-003: shadcn-vue jako jedyne źródło UI

**Decyzja:** Wszystkie komponenty UI z `resources/js/Components/ui/`.

**Reguły:**
- Jeśli czegoś brakuje → `npx shadcn-vue@latest add <component>`
- Nigdy nie piszemy custom HTML/CSS dla elementów shadcn

**Konsekwencje:** Spójność, dark mode automatycznie, a11y out-of-box.

---

### ADR-004: Pricing Engine — separacja logiki od UI

**Decyzja:** Silnik cenowy to pure-PHP serwisy bez Eloquent w logice (tylko config).

**Implementacja:**
- Wejścia: DTO (readonly classes)
- Wyjścia: JSONB w bazie
- Testy: unit testy (~10x szybsze)
- Reużycie: sklep + kokpit + API publiczny

---

### ADR-005: Web2Print Pipeline — 11 kroków (niezmienne)

**Źródło prawdy:** `plan_implementacji_modul_web2print_v2.md` (nie podlega zmianom).

**Strategie silnika:**
- `SheetEngine` — produkty arkuszowe (wizytówki, ulotki, plakaty)
- `LinearMeterEngine` — roll-upy, banery, naklejki (metr bieżący)
- `PieceEngine` — opakowania (sztuka, wymiary custom)

**Overlays (aplikowane POTEM):**
- Loyalty Overlay (rabat stałego klienta)
- RKW Overlay (koszty — nie wpływa na cenę)

---

### ADR-006: Design Editor — Konva.js + JSON state

**Decyzja:** Canvas engine Konva.js, stan w JSON (w DAMS).

**Pros:**
- Export do PDF (headless Chromium)
- Wersjonowanie (v1, v2, v3)
- Pełna historia edycji

**PDF Export Roadmap:**
- F3.1: Puppeteer + Canvas
- F3.2: Own microservice (`puppeteer-core` + Redis queue)

---

## SZCZEGÓŁOWY OPIS MODUŁÓW

### MODUŁ 1: SYSTEM CORE (Workflow & Events Engine)

**Odpowiedzialność:** Serce systemu.

**Odpowiada za:**
- Maszynę stanów zamówienia (16 statusów, FSM)
- Dispatch eventów
- Orchestracja triggerów
- Zarządzanie kolejkami (Redis)
- Scheduler (cron jobs)

**State Machine (16 statusów):**

```
NEW — nowe zamówienie
PRICING — w trakcie wyceny
WAITING_FILES — oczekiwanie na pliki od klienta
WAITING_PAYMENT — oczekiwanie na zapłatę (po Proformie)
DESIGNING — projektowanie (jeśli nie było pliku)
APPROVAL — do akceptacji przez klienta
REVISION — do poprawy (po odrzuceniu Approval)
APPROVED — zaakceptowane
PRODUCTION — w produkcji (po PAYMENT_RECEIVED)
DONE — gotowe do wysyłki
SHIPPING — pakowanie + druk etykiety
SHIPPED — wysłane (after kurier pickup)
COMPLETED — zakończone (delivered)
COMPLAINT — reklamacja (z COMPLETED)
SUSPENDED — wstrzymane (timeout, waiting for action)
CANCELLED — anulowane (przed PRODUCTION)
```

**Dozwolone tranzycje:**

```
NEW → [PRICING, CANCELLED]
PRICING → [WAITING_FILES, WAITING_PAYMENT, CANCELLED]
WAITING_FILES → [DESIGNING, APPROVAL, CANCELLED]
WAITING_PAYMENT → [WAITING_FILES, CANCELLED, SUSPENDED]
DESIGNING → [APPROVAL]
APPROVAL → [REVISION, APPROVED]
REVISION → [DESIGNING, APPROVAL]
APPROVED → [WAITING_PAYMENT, PRODUCTION]  # WAITING_PAYMENT jeśli zaliczka
PRODUCTION → [DONE]
DONE → [SHIPPING]
SHIPPING → [SHIPPED]
SHIPPED → [COMPLETED, COMPLAINT]
COMPLETED → [COMPLAINT]
COMPLAINT → [COMPLETED]  # jeśli uznana
SUSPENDED ↔ [poprzedni status]  # gwiazda — można wrócić
CANCELLED ← [każdy status do PRODUCTION]  # gwiazda
```

**Encje:**

```sql
-- StatusDefinition
id, code (VARCHAR UNIQUE), label_pl, color_hex, icon, sort_order

-- TransitionRule
id, from_status, to_status, requires_role (VARCHAR[]), condition_class (nullable)

-- Event (history only)
id, name (VARCHAR UNIQUE), description

-- Trigger (automation)
id, event_name, action_class, payload (JSONB), priority, active (BOOL)

-- ScheduledJob (cron)
id, job_class, payload (JSONB), run_at, run_interval (nullable), active
```

---

### MODUŁ 2: CLIENT MANAGEMENT (CRM)

**Odpowiedzialność:** CRUD klientów, walidacja NIP (GUS API), kontakty, tagi, lojalność.

**Encje:**

```sql
-- Klient
id, typ (B2B/B2C), imie_nazwa, nip (nullable), regon (nullable),
email_glowny, telefon_glowny, adres_ulica, adres_miasto, adres_kod,
status (aktywny/vip/zablokowany), poziom_lojalnosci_id (FK)

-- OsobaKontaktowa
id, klient_id (FK), imie, nazwisko, email, telefon, stanowisko, glowny (BOOL)

-- Tag
id, nazwa, kolor_hex

-- KlientTag (pivot)
klient_id, tag_id

-- PoziomLojalnosci
id, nazwa, prog_obrotow_rocznych, rabat_procent, priorytet_realizacji
```

**Features:**
- Walidacja NIP (suma kontrolna)
- API GUS/BIR1 → auto-uzupełnianie danych
- Tagi (segmentacja)
- Historia zamówień + komunikacji
- Auto-upgrade PoziomLojalności (cron co noc)

---

### MODUŁ 3: ORDER MANAGEMENT

**Odpowiedzialność:** CRUD zamówień, specyfikacje, deadliny, duplikowanie.

**Encje:**

```sql
-- Zamowienie
id, numer (DRK-YYYY-XXXXX), klient_id (FK), menedzer_id (FK), status,
priorytet, zrodlo, termin_realizacji, parent_order_id (nullable → dodruk)

-- Pozycja
id, zamowienie_id (FK), nazwa_produktu, naklad, format, material,
kolorystyka, uszlachetnienie, cena_jednostkowa_netto

-- Specyfikacja
id, pozycja_id (FK), parametry (JSONB) — elastyczne per typ

-- HistoriaStatusow
id, zamowienie_id (FK), stary_status, nowy_status, user_id, komentarz
```

**Features:**
- Auto-generowanie numeru (DRK-2026-00001)
- Duplikowanie zamówienia (repeat orders)
- Kopiowanie specyfikacji (bez fizycznej kopii plików — linki do DAMS)

---

### MODUŁ 4: PRICING ENGINE (Web2Print)

**Odpowiedzialność:** Wycena zaawansowana, reguły cenowe, rabaty, RKW.

**Silnik Web2Print Pipeline (11 kroków):**

```
[INPUT]
   ↓
1. Validate product & parameters
2. Calculate sheet layout (imposition) — ile użytków na arkuszu
3. Calculate waste (scrap) — % odpad tech. per material
4. Load base price rule (per product/material/range)
5. Calculate cost of goods (material quantity × unit price)
6. Calculate production time (estymacja)
7. Calculate machine costs (maszyna.koszt_godz × czas)
8. Apply pattern multiplier (np. dla wzorów −20%)
9. Apply rush fee (przyspieszone terminy +50%)
10. Apply stackable discounts (sortowanie po priorytecie)
11. [OUTPUT]: price_breakdown (JSONB)
   ↓
[OVERLAYS — aplikowane POTEM, nie część pipeline'u]
   ↓
Loyalty Overlay: cena × (1 − loyalty_discount%)
RKW Overlay: shadow calculation (parallel, nie wpływa na cenę)
```

**Encje:**

```sql
-- RegulaCenowa
id, produkt_typ, material, naklad_od, naklad_do, cena_jednostkowa

-- IndywidualyCennik
id, klient_id (FK), reguła_id (FK), cena_specjalna, active_from, active_to

-- RabatDefinicja
id, nazwa, typ (procent/kwota), warunek, wartosc, stackowalny (BOOL), priorytet

-- KalkulacjaCeny
id, zamowienie_id (FK), cena_bazowa, modyfikatory (JSONB[]),
rabat_kwota, cena_netto, vat_kwota, cena_brutto

-- KosztWlasny
id, zamowienie_id (FK), material_koszt, maszyna_koszt, robocizna, marza_realna
```

**Features:**
- Trzy strategie silnika (Sheet, LinearMeter, Piece)
- Full JSONB breakdown w KalkulacjaCeny
- RKW widoczne tylko dla Admin/Menedżer
- Wycena „w locie" (no database queries w logice)

---

### MODUŁ 5: DAMS (Document & Asset Management)

**Odpowiedzialność:** Upload (tus.io), storage (MinIO), wersjonowanie, miniatury.

**Encje:**

```sql
-- Plik
id, nazwa_oryginalna, slug, mime_type, rozmiar_bajtow, sciezka_s3 (key),
thumbnail_sm_url, thumbnail_md_url, thumbnail_lg_url, checksum_sha256

-- WersjaPlik
id, plik_id (FK), numer_wersji, sciezka_s3, komentarz, aktywna (BOOL)

-- PowiazaniePliku (polymorphic)
id, plik_id (FK), fileable_type, fileable_id, rola, created_at

-- TemporaryUpload
id, tus_upload_id, plik_id (FK nullable), status, metadata (JSONB), expires_at
```

**Features:**
- tus.io resumable upload (wznawialność)
- MinIO S3 backend
- Automatyczne miniatury (Ghostscript → PNG → ImageMagick resize)
- Wersjonowanie (każdy ponowny upload = nowa wersja)
- Polymorphic relations (Zamówienie/Wiadomość/Akceptacja)

---

### MODUŁ 6: PREFLIGHT ENGINE

**Odpowiedzialność:** Automatyczna weryfikacja techniczna plików.

**Encje:**

```sql
-- RaportPreflight
id, plik_wersja_id (FK), status (passed/warning/failed), szczegoly (JSONB)

-- RegułaPreflight
id, nazwa, parametr, wartosc_min, wartosc_max, severity (error/warning)
```

**Weryfikacje:**
- Rozdzielczość ≥ 300 DPI
- Spady ≥ 3 mm
- Przestrzeń CMYK (nie RGB)
- Fonty osadzone
- Linie cięcia
- Overprint
- Trim box

**Integracja:** callas pdfToolbox CLI lub pdfcpu.

---

### MODUŁ 7: APPROVAL SYSTEM

**Odpowiedzialność:** Żądania akceptacji, komentarze na PDF, limity rund.

**Encje:**

```sql
-- ZadanieAkceptacji
id, zamowienie_id (FK), plik_wersja_id (FK), status, link_token (UUID),
runda_nr, wyslane_at, odpowiedz_at, wygasa_at

-- KomentarzAkceptacji
id, zadanie_id (FK), tresc, pozycja_x, pozycja_y, strona, autor_typ,
autor_id, created_at
```

**Features:**
- Token linku (wygasa po 7 dniach)
- Auto-przypomnienie 48h przed wygaśnięciem
- Runda ≥ 4 → eskalacja + opcja dopłaty
- Portal klienta: PDF + annotacje + 3 przyciski
- e-signature (IP + timestamp)

---

### MODUŁ 8: COMMUNICATION HUB

**Odpowiedzialność:** Email (IMAP), SMS, wątki, szablony, in-app notifications.

**Encje:**

```sql
-- Watek
id, zamowienie_id (FK nullable), klient_id (FK nullable), temat, status

-- Wiadomosc
id, watek_id (FK), kierunek (przychodzacy/wychodzacy), kanal (email/sms/system),
tresc_html, nadawca_email, odbiorca_email, zewnetrzny_message_id (UNIQUE nullable),
status_dostarczenia, przeczytana

-- ZalacznikWiadomosci
id, wiadomosc_id (FK), plik_id (FK)

-- SzablonWiadomosci
id, nazwa, kanal, temat, tresc_html, zmienne (JSON)

-- Powiadomienie
id, user_id (FK), typ, tytul, tresc, link (nullable), przeczytane
```

**Features:**
- IMAP polling (co 2 min)
- Auto-match klienta po email
- Wysyłka z szablonów (placeholder interpolation)
- WebSocket notifications (Laravel Reverb)
- Bell icon + dropdown w navbar

---

### MODUŁ 9: PRODUCTION ENGINE

**Odpowiedzialność:** Zlecenia produkcyjne, etapy, Kanban, śledzenie czasu.

**Encje:**

```sql
-- ZlecenieProdukcyjne
id, zamowienie_id (FK), status, priorytet, data_planowana

-- EtapProdukcji
id, zlecenie_id (FK), typ (prepress/druk/postpress), status,
maszyna_id (FK), operator_id (FK), czas_start, czas_stop

-- Maszyna
id, nazwa, typ, status, koszt_godz

-- OperatorProdukcji
id, user_id (FK), specjalizacja (JSON), dostepny (BOOL)
```

**Features:**
- Auto-tworzenie zlecenia (po APPROVAL_ACCEPTED)
- Kanban board: Prepress | Druk | Postpass | Gotowe
- Operator loguje start/stop
- Real-time update przez Reverb
- KPI: avg czas per etap

---

### MODUŁ 10: SHIPPING & LOGISTICS

**Odpowiedzialność:** Kurierzy, listy przewozowe, etykiety, tracking.

**Encje:**

```sql
-- Wysylka
id, zamowienie_id (FK), kurier (inpost/dpd/dhl/gls/odbior),
numer_listu, tracking_url, etykieta_url, koszt_wysylki,
data_nadania, data_dostawy

-- AdresWysylki
id, wysylka_id (FK), imie_nazwisko, firma, ulica, kod_pocztowy,
punkt_odbioru_id (dla InPost Paczkomat)
```

**Integracje:**
- InPost ShipX API (Paczkomaty)
- DPD / DHL / GLS API
- Webhook tracking

---

### MODUŁ 11: COMPLAINT & REPRINT

**Odpowiedzialność:** Reklamacje, RMA workflow, dodruki.

**Encje:**

```sql
-- Reklamacja
id, zamowienie_id (FK), klient_id (FK), powod, opis, status,
decyzja, rozpatrujacy_id

-- DowodReklamacji
id, reklamacja_id (FK), plik_id (FK), typ (zdjecie/skan)
```

**Stany:**
- COMPLAINT_CREATED
- IN_REVIEW
- ACCEPTED / REJECTED
- REPRINT_CREATED (auto-copy order)

---

### MODUŁ 12: AI LAYER

**Odpowiedzialność:** Sugestie, analiza, asystent.

**Features:**
- Sugestie odpowiedzi na emaile (GPT-4o)
- Analiza zapytania klienta → propozycja specyfikacji
- Wyjaśnianie wyceny
- PII anonimizacja
- Max token limits

**WAŻNE:** AI nigdy nie podejmuje decyzji automatycznie — zawsze sugestia.

---

### MODUŁ 13: ACCESS CONTROL (RBAC)

**Odpowiedzialność:** Auth, role, permissions, audit log.

**Role:**
- Admin — pełny dostęp
- Menedżer — zarządzanie zamówieniami, wyceny, raportowanie
- Projektant — edycja projektów, approval
- Operator — produkcja, logowanie czasu
- Ksiegowosc — finanse, faktury
- Klient — portal, swoje zamówienia

**Features:**
- Laravel Sanctum + Fortify
- spatie/laravel-permission
- Audit log (`spatie/laravel-activitylog`)

---

### MODUŁ 14: FINANCE ORCHESTRATOR

**Odpowiedzialność:** Automatyczne dokumenty finansowe, przepływ pieniądza.

**Encje:**

```sql
-- DokumentFinansowy
id, zamowienie_id (FK), typ (proforma/zaliczkowa/vat/paragon/korekta),
numer_subiekt, kwota_netto, kwota_brutto, status

-- Platnosc
id, dokument_id (FK), kwota, metoda, data, transaction_id_provider

-- MppTracker
id, dokument_id (FK), mpp_required (bool), mpp_status
```

**Przepływ:**

```
PRICING_CALCULATED 
  ↓ [Job]
create DokumentFinansowy (typ=proforma)
send email: "wycena gotowa, link do płatności"
  ↓
[webhook P24/PayU: PAYMENT_RECEIVED]
  ↓ [Job]
create DokumentFinansowy (typ=zaliczkowa) → send to Subiekt
  ↓
[Production complete]
  ↓ [Job]
create DokumentFinansowy (typ=vat) → send to Subiekt + KSeF (jeśli MPP)
```

---

### MODUŁ 15: INTEGRATION LAYER

**Odpowiedzialność:** Adaptry do Subiekt, GUS, kurierów, P24.

**Integracje:**
- Subiekt nexo (REST Sfera)
- GUS/NIP (SOAP BIR1)
- Przelewy24 / PayU
- InPost ShipX
- DPD / DHL / GLS
- Twilio (SMS)

**Resilience:**
- Retry policy: 3x exponential backoff
- Dead Letter Queue
- Idempotency keys

---

### MODUŁ 16: ANALYTICS & REPORTING

**Odpowiedzialność:** KPI, raportowanie, insights (read-only).

**Dashboard Admin:**
- KPI: % on-time delivery, avg time per status, marża realna, revenue
- Wykres: orders per channel, production bottlenecks
- Tabela: top clients, top produkty

**Dashboard Operator:**
- Dzisiejsza lista etapów
- Tempo vs. plan

**Tabele materiałów (read-only z Subiekt):**
- Stan magazynowy papieru
- Alerty: niski stan

---

### MODUŁ 17: SHOP & PORTAL KLIENTA

**Odpowiedzialność:** Publikacja katalogowej, Web2Print configurator, portal.

**Strony publiczne (niezalogowany):**
- Catalog — grid produktów
- Product page — opis, galeria, reviews
- Configurator — Web2Print calc (live preview)
- Pricing table — ceny standardowe
- Cart + Checkout (guest checkout z email)

**Portal klienta (zalogowany):**
- My Orders — historia zamówień
- Repeat Order — "zamów jeszcze raz"
- My Account — dane kontaktowe, adresy dostawy
- Invoices — pobieranie faktur (link do Subiekt)
- Notifications — historia powiadomień

**Features:**
- Same pricing engine dla shop i backend
- Guest order → magic link rejestracji
- Indywidualne cenniki (jeśli istnieją)

---

### MODUŁ 18: DESIGN EDITOR

**Odpowiedzialność:** Online edytor projektów (Konva.js).

**Features:**
- Szablony biblioteki
- Canvas engine (Konva.js)
- Edycja tekstu, kształtów, efektów
- Upload logo/zdjęć
- Podgląd front/back
- Export PDF (headless Chromium)
- Wersjonowanie projektu (JSON state)

**Stack:**
- Vue 3 + Konva.js + vue-konva
- State w DAMS (JSON)
- PDF export: Puppeteer + Canvas

---

## WORKFLOW END-TO-END

### Scenariusz A: Email B2B → Wycena → Akceptacja → Produkcja → Wysyłka

```
[1] Email wchodzi na skrzynię drukarni
    ↓
[2] IMAP job (co 2 min) pobiera email
    ↓
[3] Job ProcessIncomingEmail: parsuje → tworzy Watek + Wiadomosc
    ↓
[4] Auto-match klienta po From:
    - Jeśli EXIT: link do istniejącego Klienta
    - Jeśli NOT: create Klient (draft status) → alert dla Menedżera
    ↓
[5] OpenAI brief extractor → propozycja spec + pytania
    ↓
[6] Menedżer otwiera wiadomość w Inbox
    - Widzi: Email + propozycja AI
    - Klika: "Utwórz zamówienie"
    ↓
[7] Create Zamowienie:
    - channel: "email"
    - Pozycje (z proponowanej spec)
    - status: PRICING
    ↓
[8] Menedżer klika "Oblicz cenę"
    ↓
[9] PricingEngine runs 11-step pipeline
    - Breakdown: JSONB w KalkulacjaCeny
    - Marża kalkulowana (visible to Manager)
    ↓
[10] Event: PRICING_CALCULATED
     ↓ [Trigger]
     → Job: CreateProformaDocument
       - Create DokumentFinansowy (proforma)
       - Send email: "Wycena gotowa, link do płatności"
       - Status: WAITING_PAYMENT
    ↓
[11] Klient kliczy link → PayU/Przelewy24 checkout
     ↓
[12] Payment received → Webhook
     ↓
[13] Event: PAYMENT_RECEIVED
     ↓ [Trigger]
     → Job: CreateZaliczkowa
       - Create DokumentFinansowy (Faktura Zaliczkowa) → send to Subiekt
     → Job: RequestFiles
       - status: WAITING_FILES
       - Send email: "Oczekujemy na pliki do druku"
     → Job: UnlockProduction (if repeat → proceed)
    ↓
[14] Klient wrzuca pliki (tus.io upload)
     ↓
[15] Event: FILE_UPLOADED
     ↓ [Trigger]
     → Job: ProcessUploadedFile
       - checksum, s3 upload
       - Generate thumbnails (Ghostscript + ImageMagick)
     → Job: PreflightFile
       - weryfikacja 300 DPI, CMYK, spady, etc.
    ↓
[16] Preflight: PASSED / FAILED / WARNING
     ↓
    IF FAILED:
      - Event: PREFLIGHT_FAILED
      - Send email: "Plik nie przeszedł preflightu, popraw i wrzuć ponownie"
      - status: WAITING_FILES
    ↓
    IF PASSED:
      - Event: PREFLIGHT_PASSED
      - status: APPROVAL
      - Create ZadanieAkceptacji (token link, wygasa 7 dni)
      - Send email: "Link do akceptacji projektu: [link]"
    ↓
[17] Klient otwiera link akceptacji
     - Widzi: PDF + annotacje
     - 3 opcje: Zaakceptuj / Do poprawy / Odrzuć
    ↓
[18] Klient kliczy "Zaakceptuj" + e-signature (IP, timestamp)
     ↓
[19] Event: APPROVAL_ACCEPTED
     ↓ [Trigger]
     → Job: CreateProductionJob
       - Create ZlecenieProdukcyjne
       - Create EtapyProdukcji (Prepress, Druk, Postpass)
       - status: PRODUCTION
     → Broadcast Reverb: Production team widzi nowe zlecenie na Kanbanie
    ↓
[20] Operator: drag-n-drop na Kanban
     - Prepress: Start → [loguje czas]
     - Druk: Start → Stop
     - Postpass: Start → Stop
     - Gotowe
    ↓
[21] Event: PRODUCTION_COMPLETED
     ↓ [Trigger]
     → status: DONE
     → Send email: "Zamówienie gotowe, pakujemy"
     → Job: CheckShippingMethod
    ↓
[22] Menedżer wybiera kurier (InPost / DPD / etc)
    ↓
[23] Event: SHIPMENT_CREATED
     ↓ [Trigger]
     → Job: GenerateShippingLabel
       - API: InPost ShipX / DPD / etc
       - numer_listu, tracking_url, etykieta (PDF na MinIO)
     → status: SHIPPING
     → Send email: "Wysyłamy! Śledzenie: [tracking_url]"
    ↓
[24] Kurier picks up → webhook: "Dispatched"
     ↓
[25] Event: SHIPMENT_DELIVERED
     ↓ [Trigger]
     → status: SHIPPED
     → Send email: "Przesyłka dostarczona! Dziękujemy."
     → Job: CreateFakturaVAT
       - Faktura VAT → send to Subiekt
       - IF B2B >15k PLN: MPP + send to KSeF
    ↓
[26] Menedżer odznaczy: status: COMPLETED
    ↓
[HAPPY PATH DONE]
```

---

## INTEGRACJE ZEWNĘTRZNE

### Krytyczne (P0)

| Integracja | Cel | API | Failover |
|-----------|------|------|----------|
| **Subiekt nexo** | Kontrahenci, faktury, magazyn | REST Sfera / XML | Manual sync daily |
| **GUS API** | Walidacja NIP, BIR1 | SOAP BIR1 | Cache (6 m-cy) |
| **Przelewy24** | Płatności online | REST + Webhooks | SMS alert |

### Wysokie (P1)

| Integracja | Cel | API | Failover |
|-----------|------|------|----------|
| **InPost ShipX** | Paczkomaty | REST | Ręczna etykieta |
| **DPD / DHL / GLS** | Kurierzy | REST | Ręczna etykieta |
| **IMAP / MS Graph** | Import emaili | IMAP / REST | Polling fallback |

### Przyszłość (P2)

| Integracja | Cel | Faza |
|-----------|------|------|
| **Twilio** | SMS przypomnienia | F2 |
| **callas pdfToolbox** | Zaawansowany preflight | F2 |
| **Firebase FCM** | Web Push | F2 |
| **OpenAI API** | AI assistant | F1 |
| **Meilisearch** | Full-text search | F2 |

---

## BEZPIECZEŃSTWO I ZGODNOŚĆ

### RODO/GDPR

- Encryption at rest: AES-256 (MinIO, PostgreSQL)
- Encryption in transit: TLS 1.3
- PII dalam logs: Anonimizacja (salting hashe)
- Audit log: spatie/laravel-activitylog (5 lat)
- Data retention: usuwanie starych backupów po 90 dni

### Ustawa o Rachunkowości

- Faktury: VAT wg przepisów (Subiekt)
- KSeF: B2B >15k PLN automatycznie
- Rachunkowość: 5 lat przechowywania
- Niezaprzeczalność: e-signature (IP, timestamp)

### Bezpieczeństwo Aplikacji

- OWASP Top 10 (Laravel Shield)
- SQL Injection: prepared statements (Eloquent)
- XSS: Vue auto-escape
- CSRF: Laravel middleware
- CORS: restricted origins
- Rate limiting: 60 req/min per user
- 2FA: optional (Fortify ready)

---

## NASTĘPNE KROKI

1. ✅ Przeczytaj ten dokument całkowicie
2. ⏳ Zapoznaj się z `ROADMAP_CONSOLIDATED.md` (fazy + konkretne kroki)
3. ⏳ Setup lokalnego środowiska (Docker Compose)
4. ⏳ Pierwsze repo + initial commit (Faza 1.0)
5. ⏳ Implementacja modułów w kolejności: 13 → 8 → 2 → 3 → 4 → 5 → ...

---

**Dokument zaktualizowany:** 2026-04-20  
**Wersja:** 3.0 Consolidated  
**Autor:** Claude (na bazie 4 źródeł)
