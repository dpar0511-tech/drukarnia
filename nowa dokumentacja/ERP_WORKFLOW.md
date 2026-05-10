# DRUKARNIA ERP — WORKFLOW A→Z (v3)

> ⚠️ **Aktualizacja 2026-04-24:** dokument dostosowany do [`nowa dokumentacja/`](./nowa%20dokumentacja/00_INDEX.md).
> **Źródłem prawdy workflow i eventów jest** [`06_WORKFLOW_I_EVENTY.md`](./nowa%20dokumentacja/06_WORKFLOW_I_EVENTY.md).
> Ten dokument jest wysokopoziomowym przewodnikiem krok-po-kroku.

---

## TL;DR

Pełen cykl życia zamówienia w 16 krokach: **email/portal/sklep → wycena (12-krokowy `PricingPipeline`) → Proforma → płatność P24 → upload plików do DAMS lokalnego → akceptacja projektu (markup PDF, od Fazy 2) → produkcja (Kanban) → gotowe → wysyłka (InPost/DPD/DHL/GLS/odbiór) → dostarczone → faktura VAT/Paragon (KSeF via Subiekt) → zakończone → ew. reklamacja (SLA 48h/14d, przedruk/zwrot).**

FSM **19 stanów** (`spatie/laravel-model-states`). **Preflight odłożony do Fazy 4** (w MVP bez automatycznej walidacji PDF).

---

## 1. FSM 19 STANÓW — DIAGRAM

Pełna tabela i legalne tranzycje: [06_WORKFLOW_I_EVENTY.md](./nowa%20dokumentacja/06_WORKFLOW_I_EVENTY.md) sekcja 1.

```
                  ┌────────┐
  ─ create ─────► │ NOWE   │
                  └───┬────┘
                      │ OrderCreated → Pricing start
                      ▼
                  ┌─────────┐
                  │ WYCENA  │─── PriceCalculated ──┬─► OCZEKUJE_NA_PLIKI
                  └─────────┘                       │
                      │                             └─► OCZEKUJE_NA_PLATNOSC (B2C Proforma)
                      │
                      │ CancelOrder
                      ▼
                 ┌────────────┐
                 │ ANULOWANE  │◄── z każdego stanu przed W_PRODUKCJI
                 └────────────┘

  OCZEKUJE_NA_PLIKI
     │ FileUploaded(all_required) ← event z DAMS
     ▼
  PROJEKTOWANIE (opcj. — gdy zamówiono usługę design)
     │ DesignSubmitted
     ▼
  OCZEKUJE_NA_AKCEPTACJE
     │
     ├── ApprovalApproved ───────► AKCEPTOWANE
     ├── ApprovalRevisionRequested ─► REWIZJA ──┐
     │                                           │
     │    ┌─ (nowa wersja pliku) ◄──────────────┘
     │    │
     │    └─► OCZEKUJE_NA_AKCEPTACJE (runda+1; ≥4 eskalacja)
     │
     └── ApprovalRejected ─────► ODRZUCONE ───► ANULOWANE

  OCZEKUJE_NA_PLATNOSC
     │ PaymentReceived (P24 webhook)
     ▼
  OCZEKUJE_NA_PLIKI  (lub AKCEPTOWANE gdy pliki już są)

  AKCEPTOWANE
     │ createProductionJob
     ▼
  W_PRODUKCJI ⇄ PRODUCTION_HOLD
     │ ProductionCompleted
     ▼
  GOTOWE_DO_WYSYLKI
     │
     ├── SelectShipmentPickup ──► ODBIOR_OSOBISTY ──► ZAKONCZONE
     └── ShipmentDispatched ────► WYSLANE ──► DOSTARCZONE ──► ZAKONCZONE

  DOSTARCZONE | ZAKONCZONE ──► REKLAMACJA (gdy klient zgłosi)
                               ├── approved → (ReprintOrder linkowane) → ZAKONCZONE
                               └── rejected → ZAKONCZONE

  SUSPENDED — stan pomocniczy, reversible do poprzedniego
```

---

## 2. 18 MODUŁÓW BIORĄCYCH UDZIAŁ

| # | Moduł | Kiedy wchodzi do flow |
|---|-------|------------------------|
| 1 | CRM | KROK 1 (identyfikacja klienta z emaila / portalu / sklepu) |
| 2 | Orders | KROK 1 → 16 (główny aktor) |
| 3 | Products | KROK 3 (specyfikacja) |
| 4 | Pricing | KROK 3 (`PricingPipeline::calculate()`) |
| 5 | Quotes (F2) | KROK 3 alternatywny — wycena samodzielna |
| 6 | Approvals | KROK 7 (akceptacja z markup od F2) |
| 7 | DAMS | KROK 5 (upload), KROK 6 (wersjonowanie), KROK 15 (reklamacja evidence) |
| 8 | Production | KROK 9–10 (Kanban, etapy) |
| 9 | Inventory | KROK 9 (auto-odpisy z BOM — F3) |
| 10 | Finance | KROK 4 (Proforma), KROK 5 (Zaliczkowa), KROK 12 (VAT/Paragon), KROK 15 (Korekta) |
| 11 | Logistics | KROK 11 (wybór kuriera, etykieta), KROK 13 (tracking) |
| 12 | Complaints (F3 full) | KROK 15 |
| 13 | CommHub | KROK 1 (IMAP), 2, 4, 7, 10, 11, 13, 14, 15 (emaile + SMS + notifications) |
| 14 | Reports/BI | Przekrojowe — odczyt |
| 15 | RBAC/Settings | Przekrojowe — policy |
| 16 | Integrations | KROK 1 (IMAP, GUS), 4 (Subiekt, P24), 11 (kurier), 12 (KSeF via Subiekt) |
| 17 | Shop (F3) | KROK 1 alternatywny — zamówienie ze sklepu |
| 18 | Design Editor (F3) | KROK 5 alternatywny — klient projektuje online |

---

## 3. WORKFLOW KROK PO KROKU

### KROK 1: Wpływ zapytania

**Źródło:** email / portal klienta / telefon (manual) / sklep e-commerce (F3) / Design Editor (F3).

**Status:** `NOWE`.

**Eventy:** `MessageReceived` (email/IMAP) → `ClientCreated` jeśli nowy → `OrderCreated`.

**Akcje automatyczne:**
- **CommHub** (moduł 13) — IMAP poller co 2 min (`ImapPollCommand`) → `InboundMessageHandler::handle` → dedupe po `external_message_id` → tworzy `WatekKomunikacji`.
- **CRM** (moduł 1) — match klienta po `From:` → `Klient.email_glowny` / `OsobaKontaktowa.email`; jeśli nowy B2B → walidacja NIP via `GusAdapter` (cache Redis 24h) → auto-fill danych firmy.
- **Orders** (moduł 2) — `OrderCreator::create` → `Zamowienie(status=NOWE)`, numer `DRK-YYYY-XXXXX` atomic.
- **DAMS** (moduł 7) — załączniki emaila → ingest do `storage/app/private/messages/{yyyy}/{mm}/` → `FileUploaded`.
- **AI Layer** (F2) — sugestia specyfikacji i priorytetu.

**Powiadomienia:** in-app (Reverb `user.{id}`) + opcjonalnie email → menedżer widzi nowe zamówienie w Inbox.

---

### KROK 2: Komunikacja z klientem

**Ekran:** Inbox (`/inbox`) + karta zamówienia `/orders/{id}`.

**Akcje menedżera:**
- Czyta zapytanie (Inertia + Vue).
- AI Layer sugeruje odpowiedź (`✨ Zasugeruj odpowiedź` — anonimizacja PII przed wysyłką do OpenAI).
- Menedżer edytuje / wysyła przez `MailSender::sendFromTemplate`.

**Eventy:** `MessageSent`.

**Oczekiwanie:** klient odpowiada z brakującymi parametrami → `MessageReceived` → wątek aktualizowany.

---

### KROK 3: Wycena (PricingPipeline 12 kroków)

**Wyzwalacz:** menedżer wypełnia specyfikację pozycji zamówienia → klika „Oblicz cenę" (w F1: Filament action; w F2+: Vue `ProductConfigurator`).

**Status:** `NOWE` → `WYCENA`.

**Eventy:** `PriceCalculated(PricingResult, Zamowienie, PozycjaZamowienia)`.

**Akcje automatyczne:**
- **Pricing** (moduł 4) — `PricingPipeline::calculate(Product $p, CalculationConfig $config)`:
  1. Silnik (`SheetEngine` / `LinearMeterEngine` / `MultipageEngine`).
  2. Koszty parametrów (common/cover/interior) — `ProductParameterOption` z `selectedParameterOptionIds`.
  3. Koszty produktowe.
  4. Total cost (materiał + druk + extras + product extras).
  5. Marża dwupoziomowa (`max(totalCost * margin_base_pct, margin_min_pln)`).
  6. Base price.
  7. Project price (jeśli `orderProject=true`).
  8. Pattern multiplier.
  9. Time multiplier (Standard / Express).
  10. ExclusionRule check — throw jeśli naruszenie.
  11. Assembly `PricingResult`.
  12. Snapshot w `kalkulacje_ceny` (listener `SavePricingSnapshot` na `PriceCalculated`).
- Menedżer widzi breakdown: cena klienta, koszt własny (widoczny TYLKO Admin/Menedżer/Księgowość, nie Operator), marża.

**Akcje menedżera:** „Zastosuj cenę" lub „Edytuj ręcznie" (nowy snapshot).

---

### KROK 4: Wysyłka oferty + Proforma

**Wyzwalacz:** menedżer klika „Wyślij ofertę".

**Status:** `WYCENA` → `OCZEKUJE_NA_PLATNOSC` (lub od razu `OCZEKUJE_NA_PLIKI` dla B2B z kredytem kupieckim).

**Eventy:** `MessageSent`, `ProformaCreated`.

**Akcje automatyczne:**
- **CommHub** — email z ofertą (AI tworzy tekst lub szablon `oferta_wyslana`, system generuje PDF).
- **Finance** (moduł 10) — `ProformaGenerator::generate` → `SubiektAdapter::createProforma` (F1 stub, F2 prawdziwy) → `DokumentFinansowy(typ=proforma)` + link P24.
- **Integrations** — `Przelewy24Adapter::createPayment` → `link_platnosci` w emailu.

**Scenariusze:**
- **B2B stały klient (kredyt kupiecki):** flag `Klient.kredyt_kupiecki = true` → brak Proformy → od razu `OCZEKUJE_NA_PLIKI`.
- **B2B nowy / B2C:** Proforma obowiązkowa → produkcja dopiero po wpłacie.

---

### KROK 5: Płatność (Przelewy24)

**Wyzwalacz:** webhook z Przelewy24 / ręczne potwierdzenie (księgowość) / import z Subiekt.

**Status:** `OCZEKUJE_NA_PLATNOSC` → `OCZEKUJE_NA_PLIKI` (lub `AKCEPTOWANE` jeśli pliki już są).

**Eventy:** `PaymentReceived(Platnosc)`.

**Weryfikacja webhooka (3-stopniowa, PRZED logiką!):**
1. IP whitelist (konfiguracja `.env`).
2. CRC signature `md5("{sessionId}|{orderId}|{amount}|{currency}|{crcKey}")`.
3. Idempotency `webhook_events.provider_event_id` UNIQUE (format `{sessionId}:{orderId}`).

**Akcje automatyczne:**
- `MarkDocumentPaid` listener.
- `CreateInvoiceAdvanceIfB2B` → **Faktura Zaliczkowa** (tylko B2B).
- `RecalculateLoyaltyTier` — przeliczenie poziomu lojalności (po `PaymentReceived`, nie `OrderCompleted`).
- Email do klienta (`platnosc_otrzymana`): „Dziękujemy za płatność, oczekujemy na pliki".

**Timeout:** brak płatności 7 dni → `ReminderSent`; 14 dni → `ORDER_SUSPENDED`.

---

### KROK 6: Upload plików (DAMS lokalny)

**Wyzwalacz:** klient uploaduje pliki przez Portal (F2) / email / FTP (opcjonalnie).

**Status:** `OCZEKUJE_NA_PLIKI` pozostaje; event uruchamia tranzycję po `CheckIfAllRequiredPresent`.

**Eventy:** `FileUploaded(Plik, WersjaPliku, uploader)`.

**Akcje automatyczne (DAMS, moduł 7):**
1. **tus.io upload** — `POST/PATCH /tus/uploads` → chunk resumable, storage `tus_temp`.
2. **`ProcessUploadedFileJob`** (queue `default`):
   - SHA-256 checksum.
   - Dedup po `pliki.checksum_sha256` UNIQUE.
   - Przeniesienie do `storage/app/private/orders/{yyyy}/{mm}/{order_id}/source/{uuid}-{slug}.{ext}`.
   - Utworzenie `Plik` + `WersjaPlik v1` + `PowiazaniePliku`.
   - Dispatch `GenerateThumbnailsJob` (PDF/TIFF/PNG/JPG).
   - Event `FileUploaded`.
3. **`GenerateThumbnailsJob`** (queue `thumbnails`) — Ghostscript + ImageMagick → sm/md/lg PNG w `thumbs/`.
4. `CheckIfAllRequiredPresent` listener — jeśli wszystkie wymagane pliki obecne → tranzycja statusu zamówienia.

> **Uwaga:** Preflight (automatyczna walidacja rozdzielczości/spadów/CMYK/fontów) jest **odłożony do Fazy 4**. W MVP/F2/F3 weryfikacja ręczna przez projektanta/menedżera.

---

### KROK 7: Projektowanie (opcjonalne)

**Dotyczy:** zamówień z usługą projektowania (klient nie ma gotowego pliku).

**Status:** `PROJEKTOWANIE`.

**Akcje:**
- Projektant loguje się → ma dostęp do wątku + pliki źródłowe.
- Opcja A (tradycyjna): projektant tworzy plik lokalnie → upload v1 przez DAMS.
- Opcja B (F3, Design Editor): klient **lub** projektant otwiera `/designer/{product_slug}` → Konva.js canvas → autosave co 5s → `PdfExporter` eksport 300 DPI CMYK + 3mm bleed.

**Eventy:** `DesignSubmittedForApproval`, `DesignExported`.

---

### KROK 8: Akceptacja projektu (Approvals)

**Status:** `OCZEKUJE_NA_AKCEPTACJE`.

**Eventy:** `ApprovalRequested`.

**Akcje automatyczne:**
- `ApprovalRequestSender::send` → UUID v4 token, `wygasa_at = now()+7d`.
- Email do klienta z publicznym linkiem `/approval/{token}` (szablon `link_akceptacji`).
- **F1 (MVP):** klient widzi PDF w PDF.js + 3 przyciski (Akceptuję / Zmiany / Odrzucam).
- **F2:** `PdfMarkupViewer` z canvas overlay → klient dodaje komentarze `(x, y, page)` → `komentarze_akceptacji`.

**Scenariusze:**
- ✅ **Zaakceptowano:** `ApprovalApproved` → status `AKCEPTOWANE` → `CreateProductionJob` listener.
- ❌ **Do poprawy:** `ApprovalRevisionRequested` → status `REWIZJA` → projektant v2 → nowa akceptacja (`runda_nr + 1`).
- ⚠️ **Runda ≥ 4:** `ApprovalEscalated` → menedżer informowany → dopłata za dodatkowe poprawki.
- ❌ **Odrzucono:** `ApprovalRejected` → status `ODRZUCONE` → `ANULOWANE`.

**Timeout:**
- 48h przed wygaśnięciem → `ReminderSent` → email reminder klient.
- 7 dni → `ApprovalExpired` → `ORDER_SUSPENDED`.

---

### KROK 9: Produkcja (Kanban)

**Status:** `AKCEPTOWANE` → `W_PRODUKCJI` (po `ProductionJobCreated`).

**Eventy:** `ProductionJobCreated`, `ProductionStarted`.

**Akcje automatyczne (Production, moduł 8):**
- `JobCreator::createFromOrder` — listener na `ApprovalApproved` → tworzy `ZadanieProdukcyjne` + `EtapProdukcji` z szablonu JSONB per typ produktu.
- Domyślne etapy: **Prepress → Druk → Postpress (cięcie/laminowanie/oprawa) → Packaging**.
- Przypisanie do `Maszyna` + `Operator` (ręczne lub auto).
- Operator loguje `TimeLogger::start/stop` per etap.

**Ekran:** `/production/kanban` — 5 kolumn, drag & drop (`sortablejs`), Reverb presence channel `production.kanban`.

**Statusy etapów:** `oczekuje` → `w_trakcie` → `gotowe` (lub `pominiety`).

**Wstrzymanie:** status zamówienia `PRODUCTION_HOLD` (kontrola jakości) — reversible do `W_PRODUKCJI`.

**RBAC:** Operator **nie widzi** pól `cost_*`/`margin_*` (scope w Inertia props).

**Powiadomienia:** email do klienta (`zamowienie_w_produkcji`) przy pierwszym `ProductionStarted`.

---

### KROK 10: Kontrola jakości + Gotowe

**Status:** `W_PRODUKCJI` → `GOTOWE_DO_WYSYLKI`.

**Eventy:** `ProductionCompleted`.

**Akcje automatyczne:**
- Operator oznacza ostatni etap jako `gotowe` → listener `TransitionOrderToReadyToShip`.
- Aktualizacja raportu RKW (`czas_rzeczywisty` vs `czas_normatywny` per etap) — feed do snapshotu `PricingResult.details` (tylko F3+).
- Email/SMS do klienta (`zamowienie_gotowe`): „Zamówienie gotowe, przygotowujemy wysyłkę".

---

### KROK 11: Wysyłka (Logistics)

**Status:** `GOTOWE_DO_WYSYLKI` → `WYSLANE` (lub `ODBIOR_OSOBISTY`).

**Eventy:** `ShipmentCreated`, `ShipmentDispatched`.

**Akcje automatyczne (Logistics, moduł 11):**
- Menedżer wybiera metodę dostawy — UI w zakładce „Wysyłka" karty zamówienia.
- **F1:** InPost (Paczkomat / kurier) + odbiór osobisty.
- **F2:** + DPD + DHL + GLS.
- `CourierSelector::recommendFor` — heurystyka (waga ≤25kg → Paczkomat; paleta → DPD).
- Formularz adresu (prefill z `Klient`) + wyszukiwarka paczkomatu.
- Button „Generuj etykietę" → job async → adapter Saloon (`InPostAdapter::createShipment`) → etykieta PDF do `storage/app/private/shipment_labels/{yyyy}/{mm}/` → powiązanie z `Wysylka`.
- Tracking number zapisany; webhook kuriera aktualizuje status live.

**Odbiór osobisty:** `ODBIOR_OSOBISTY` → po wydaniu → `ZAKONCZONE`.

**Powiadomienia:** email do klienta (`zamowienie_wyslane` z linkiem tracking) + SMS (F2).

---

### KROK 12: Fakturowanie (po wysyłce)

**Wyzwalacz:** event `ShipmentDispatched` (moment powstania obowiązku podatkowego).

**Eventy:** `InvoiceIssued`, `KsefSubmitted`.

**Akcje automatyczne (Finance, moduł 10):**
- `InvoiceOrchestrator::handlePayment` rozgałęzia:
  - **B2B** → `SubiektAdapter::createFakturaVat` → `DokumentFinansowy(typ=faktura_vat)` → `DispatchToKsef` (via Subiekt → KSeF).
  - **B2C** → `createParagon` (lub Faktura do paragonu na życzenie).
- PDF faktury → DAMS (`storage/app/private/invoices/{yyyy}/{mm}/`).
- Email do klienta z załącznikiem PDF.
- MPP flag: jeśli `brutto > 15000 PLN` AND `B2B` AND appendix15 goods → `mpp_flag = true`.

> **F1 (MVP):** tylko Proforma (stub Subiekt). **F2:** pełny flow VAT/Paragon/KSeF.

---

### KROK 13: Potwierdzenie odbioru

**Wyzwalacz:** webhook kuriera (doręczone) / ręczne potwierdzenie.

**Status:** `WYSLANE` → `DOSTARCZONE` → `ZAKONCZONE`.

**Eventy:** `DeliveryConfirmed`, `OrderCompleted`.

**Akcje automatyczne:**
- `TransitionOrderToDelivered` listener.
- CRM aktualizuje historię obrotów.
- Pricing/Loyalty — `LoyaltyTierRecalculator` (już po `PaymentReceived`, ale też opcjonalnie przy zakończeniu).
- Reports — aktualizacja materialized views (F3, cron co 1h).
- Email (`dziekujemy_za_zamowienie`): „Dziękujemy! Zapraszamy ponownie." + ew. ankieta satysfakcji.

---

### KROK 14: (opcjonalny) Reklamacja

**Wyzwalacz:** klient zgłasza reklamację — portal (`/portal/orders/{id}/complain`) / email / telefon.

**Status:** `DOSTARCZONE`/`ZAKONCZONE` → `REKLAMACJA`.

**Eventy:** `ComplaintSubmitted`.

**Workflow reklamacji (FSM w module 12 full od F3):**
1. `Zgloszona` → menedżer potwierdza przyjęcie (max 24h).
2. `WRozpatrywaniu` → analiza (zdjęcia z `dowody_reklamacji`, porównanie z plikiem).
3. Decyzja:
   - `Uznana + dodruk` → `ReprintOrderCreator::reprint` (nowe `Zamowienie`, `parent_order_id`, `zrodlo=reklamacja`) → Finance opcjonalnie wewnętrzny koszt.
   - `Uznana + zwrot` → `RefundIssued` → Finance → `createFakturaKorygujaca`.
   - `Odrzucona` → uzasadnienie → email klient.
4. `Zamknieta`.

**SLA (zgodnie z polskim prawem Rękojmia):**
- Pierwsza odpowiedź: **48h** (alert `SlaTimer` cron co 1h).
- Rozstrzygnięcie: **14 dni** (escalation do Admin).

---

### KROK 15: (opcjonalny) Dodruk / Repeat Order

**Wyzwalacz:** klient „to samo co ostatnio" / reklamacja uznana.

**Akcje:**
- Menedżer klika „Powtórz zamówienie" na `Pages/Orders/Show.vue`.
- `OrderDuplicator::duplicate(Zamowienie $src): Zamowienie`:
  - Kopia `PozycjaZamowienia` + `SpecyfikacjaDruku`.
  - Linkuje oryginalne pliki z DAMS (nie kopia fizyczna — używa `PowiazaniePliku`).
  - `parent_order_id = $src->id`, `zrodlo = dodruk`.
  - Zachowuje snapshot ceny (lub przelicza jeśli klient chce zmienić ilość).
  - Status `NOWE` lub od razu `WYCENA`.

**Eventy:** `OrderDuplicated(source, new)`.

---

### KROK 16: Projektowanie online (Design Editor — F3)

**Alternatywa** do KROK 5/7. Klient samodzielnie projektuje w przeglądarce.

**Flow:**
1. Klient z sklepu (`/shop/product/{slug}`) klika „Zaprojektuj online" → `/designer/{product_slug}`.
2. Canvas Konva.js + biblioteka szablonów (`TemplateLibraryService`).
3. Autosave co 5s (`SceneAutoSaver` → gzip JSON w `storage/app/private/designs/{id}/scenes/`).
4. Klient klika „Gotowe" → `PdfExporter` job → headless Chrome → **PDF 300 DPI CMYK + 3mm bleed**.
5. Export → DAMS `FileUploader::ingest` → powiązanie z `Zamowienie`.
6. Event `DesignSubmittedForApproval` → automatyczny `ApprovalRequested` (lub od razu `AKCEPTOWANE` jeśli klient akceptuje swój własny projekt).

---

## 4. KATALOG EVENTÓW (mapa)

Pełna lista: [06_WORKFLOW_I_EVENTY.md](./nowa%20dokumentacja/06_WORKFLOW_I_EVENTY.md) sekcja 2.

### Per-step

| Krok | Eventy główne |
|------|---------------|
| 1 | `MessageReceived`, `ClientCreated` (opcj.), `OrderCreated`, `FileUploaded` (załączniki) |
| 2 | `MessageSent` |
| 3 | `PriceCalculated` |
| 4 | `MessageSent`, `ProformaCreated` |
| 5 | `PaymentReceived`, `InvoiceIssued` (Zaliczkowa B2B) |
| 6 | `FileUploaded`, `FileVersionAdded` |
| 7 | `DesignSubmittedForApproval` (F3) |
| 8 | `ApprovalRequested`, `ApprovalApproved` / `ApprovalRevisionRequested` / `ApprovalRejected`, `ApprovalExpired`, `ApprovalEscalated` |
| 9 | `ProductionJobCreated`, `ProductionStarted`, `ProductionStageUpdated` (broadcast) |
| 10 | `ProductionCompleted` |
| 11 | `ShipmentCreated`, `ShipmentDispatched` |
| 12 | `InvoiceIssued`, `KsefSubmitted` |
| 13 | `DeliveryConfirmed`, `OrderCompleted` |
| 14 | `ComplaintSubmitted`, `ComplaintApproved` / `ComplaintRejected`, `ReprintOrderCreated`, `RefundIssued`, `CorrectionIssued` |
| 15 | `OrderDuplicated` |
| 16 | `DesignExported` |

---

## 5. QUEUE TOPOLOGY

| Queue | Priorytet | Typowe jobs |
|-------|-----------|-------------|
| `default` | normal | Listenery domenowe, walidacja, update modeli |
| `pricing` | high | `SavePricingSnapshot`, batch recalculation |
| `emails` | high | Wysyłka maili (klienci czekają) |
| `sms` | high | SMS notyfikacje (F2) |
| `webhooks` | high | Subiekt, P24, kurierzy (od nich zależy workflow) |
| `imap` | low | IMAP polling (1 worker!) |
| `thumbnails` | low | Ciężkie na plikach |
| `preflight` | low | (F4) |
| `reports` | low | Materialized views refresh |

**Retry policy:** `tries=3`, `backoff=[0, 30, 300]`, `retryUntil = now()->addHours(2)`.

---

## 6. WERYFIKACJA E2E

Pełna Pest Feature suite w `tests/Feature/E2e{Mvp,Phase2,Phase3}Test.php`. Scenariusze:

### E2E Faza 1 (MVP)

Email IMAP → wątek → zamówienie → wycena (PricingPipeline) → Proforma (Subiekt stub) → webhook P24 → payment → upload plik (tus) → AV clean → approval basic (accept) → production job → Kanban → completed → InPost label → dispatched → delivered webhook → completed.

### E2E Faza 2

+ Portal login klienta + markup approval (2 rundy) + DPD shipping + pełna Faktura VAT → KSeF.

### E2E Faza 3

+ Shop guest checkout z Omnibus + Design Editor export CMYK + reklamacja + dodruk + refund → korekta.

---

## 7. DOKUMENTY POKREWNE

- [`nowa dokumentacja/06_WORKFLOW_I_EVENTY.md`](./nowa%20dokumentacja/06_WORKFLOW_I_EVENTY.md) — **źródło prawdy** FSM i eventów (380 linii).
- [`nowa dokumentacja/05_MODULY_BIZNESOWE.md`](./nowa%20dokumentacja/05_MODULY_BIZNESOWE.md) — opis 18 modułów.
- [`nowa dokumentacja/07_SILNIK_CENOWY_INTEGRACJA.md`](./nowa%20dokumentacja/07_SILNIK_CENOWY_INTEGRACJA.md) — 12-krokowy `PricingPipeline`.
- [`nowa dokumentacja/08_DAMS_LOKALNE_PRZECHOWYWANIE_PLIKOW.md`](./nowa%20dokumentacja/08_DAMS_LOKALNE_PRZECHOWYWANIE_PLIKOW.md) — DAMS lokalny.
- [`roadmap.md`](./roadmap.md) — pokrokowy plan implementacji.
- [`MASTER_PLAN.md`](./MASTER_PLAN.md) — master plan v3.

---

**Data aktualizacji:** 2026-04-24.
**Poprzednia wersja:** zastąpiona — 14 kroków rozszerzono do 16, FSM 16 → 19 stanów, Preflight odłożony do Fazy 4, dodano Shop i Design Editor alternatives.
