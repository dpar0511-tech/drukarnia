# 05 — MODUŁY BIZNESOWE (18)

Każdy moduł = bounded context: odpowiedzialność, publiczne serwisy, eventy, tabele, UX skrót, granice.

---

## MODUŁ 1 — CRM

**Odpowiedzialność:** ewidencja klientów B2B/B2C, osoby kontaktowe, lojalność, tagi, walidacja NIP przez GUS.

**Serwisy publiczne:**
- `App\Services\CRM\ClientRegistrar::create(array $data): Klient`
- `App\Services\CRM\GusValidator::validate(string $nip): ?GusCompanyData` — cache 24 h
- `App\Services\CRM\LoyaltyTierRecalculator::recalculate(Klient $k): void` — po `PaymentReceived` event
- `App\Services\CRM\ClientAnonymizationService::anonymize(Klient $k): void` — RODO

**Eventy:** `ClientCreated`, `ClientBlocked`, `LoyaltyTierChanged`.

**Tabele:** `klienci`, `osoby_kontaktowe`, `tagi`, `klient_tagi`, `progi_lojalnosciowe`.

**UX:** `/crm/clients` (lista + filtry), `/crm/clients/{id}` (szczegóły, zakładki: Informacje / Zamówienia / Kontakty / Historia / Faktury). Komponenty: `DataTable`, `Tabs`, `Card`, `Badge` (status).

**Granice:** Nie tworzy zamówień (to moduł Orders). Nie wysyła emaili (to CommHub).

---

## MODUŁ 2 — ORDERS

**Odpowiedzialność:** tworzenie zamówień, pozycje, specyfikacje, numeracja, duplikacja, historia statusów. FSM 16 stanów (patrz 06).

**Serwisy:**
- `App\Services\Orders\OrderCreator::create(array $data, User $user): Zamowienie`
- `App\Services\Orders\OrderDuplicator::duplicate(Zamowienie $src): Zamowienie`
- `App\Services\Orders\StatusTransitioner::transition(Zamowienie $z, string $to, ?string $comment): void`
- `App\Services\Orders\OrderNumberGenerator::next(): string` — format `DRK-YYYY-XXXXX`, sekwencyjny per rok (atomic insert + `SELECT ... FOR UPDATE`).

**Eventy:** `OrderCreated`, `OrderStatusChanged`, `OrderCancelled`, `OrderDuplicated`.

**Tabele:** `zamowienia`, `pozycje_zamowien`, `historia_statusow_zamowien`, opcjonalnie `specyfikacje_produktowe`.

**UX:** `/orders` (lista), `/orders/{id}` (zakładki: Pozycje / Pliki / Wycena / Produkcja / Dokumenty / Wysyłka / Komunikacja). Strona `/orders/create` z wizardem (Klient → Pozycje → Specyfikacja → Termin → Podgląd).

**Granice:** Wycena delegowana do Pricing. Pliki — do DAMS. Produkcja — do Production.

---

## MODUŁ 3 — PRODUCTS

**Odpowiedzialność:** katalog produktów + parametry + parametry proste + konfiguracja pricingu. **Konfigurowany głównie przez Filament (admin).**

**Serwisy:**
- `App\Services\Products\ProductRegistrar::create(array $data): Product`
- `App\Services\Products\ProductConfigService::attachParameter(Product $p, Parameter $param, string $role, bool $isDefault): void`

**Tabele:** `product`, `product_override`, wszystkie pivoty `product_*` (patrz 07 sekcja 7).

**UX:** **Filament** — `ProductResource` z zakładkami i inlinami (override, formaty, materiały, zadruki, parametry, czasy, strony).

Sklep (moduł 17) wyświetla produkty przez `/shop/product/{slug}` — Inertia+Vue, korzysta z tego samego modelu.

---

## MODUŁ 4 — PRICING (silnik cenowy)

**Odpowiedzialność:** kalkulacja cen (12-kroków dla `calculated`, rabat wykładniczy dla `simple`), wykluczenia, overlays loyalty i RKW.

**Serwisy:** Pełna lista (≈20 klas) — patrz [07_SILNIK_CENOWY_INTEGRACJA.md](./07_SILNIK_CENOWY_INTEGRACJA.md).

**Eventy:** `PriceCalculated`, `PriceRecalculated`.

**Tabele:** warstwa pricing (patrz 04 sekcja 3) + `kalkulacje_ceny`.

**UX:** w MVP admin Filament — akcja „Przelicz cenę" na pozycji zamówienia → modal z konfiguracją → wynik. W Fazie 2/3 — frontendowy konfigurator Vue.

---

## MODUŁ 5 — QUOTES (wyceny)

**Odpowiedzialność:** oferty wygenerowane dla klienta, z czasem ważności (14 dni), konwersja do zamówienia.

**Serwisy:**
- `App\Services\Quotes\QuoteGenerator::generate(Klient $k, array $items): Quote`
- `App\Services\Quotes\QuoteConverter::convertToOrder(Quote $q): Zamowienie`

**Eventy:** `QuoteGenerated`, `QuoteExpired`, `QuoteAccepted`.

**Tabele:** `wyceny`, `pozycje_wycen` (analogiczne do zamówień; `expires_at` DEFAULT `created_at + 14 days`).

**UX:** `/quotes` lista. Link publiczny z tokenem do akceptacji (analogicznie do approvals).

---

## MODUŁ 6 — DESIGNS / APPROVALS

**Odpowiedzialność:** proces akceptacji projektu graficznego przez klienta z markup na PDF, runda rewizji, eskalacja ≥4 rundy.

**Serwisy:**
- `App\Services\Designs\ApprovalRequestSender::send(Zamowienie $z, WersjaPliku $v, ?User $designer): ZadanieAkceptacji`
- `App\Services\Designs\ApprovalResponseHandler::handle(string $token, string $action, ?array $comments): void`
- `App\Services\Designs\EscalationDetector` — cron, flaguje rundy ≥4 → notify menedżer, upcharge alert.

**Eventy:** `ApprovalRequested`, `ApprovalApproved`, `ApprovalRevisionRequested`, `ApprovalRejected`, `ApprovalExpired`.

**Tabele:** `zadania_akceptacji`, `komentarze_akceptacji`.

**UX:**
- Panel pracownika — `/orders/{id}/approvals`, tworzenie nowego, preview PDF przez PDF.js.
- Publiczny link `/approval/{token}` — markup na PDF (komponent PdfMarkupViewer), przyciski Akceptuję / Zmiany / Odrzuć.

**Granice:** Nie modyfikuje plików (to DAMS). Nie zmienia statusu zamówienia — emituje event, FSM reaguje.

---

## MODUŁ 7 — DAMS (Document & Asset Management)

**Odpowiedzialność:** upload resumable (tus.io), versioning, miniatury, polimorficzne linkowanie, bezpieczny dostęp. **Wyłącznie lokalnie** — patrz [08_DAMS_LOKALNE_PRZECHOWYWANIE_PLIKOW.md](./08_DAMS_LOKALNE_PRZECHOWYWANIE_PLIKOW.md).

**Serwisy:**
- `App\Services\DAMS\FileUploader` — integracja tus-php, finalize hook, SHA-256.
- `App\Services\DAMS\ThumbnailGenerator` — job-ed.
- `App\Services\DAMS\FileAccessService::streamForUser(Plik $p, User $u): StreamedResponse`
- `App\Services\DAMS\CleanupService` — cron, usuwa pliki soft-deleted > 90 dni.

**Eventy:** `FileUploaded`, `FileVersionAdded`, `FileDeleted`.

**Tabele:** `pliki`, `wersje_plikow`, `powiazania_plikow`, `miniatury` (lub media-library Spatie).

**UX:** komponent `<FileUploader>` (tus client) + `<FileList>` + `<FilePreview>` (PDF.js / img / blob).

---

## MODUŁ 8 — PRODUCTION

**Odpowiedzialność:** zadania produkcyjne, Kanban, etapy, logowanie czasu operatora, maszyny, dane do RKW.

**Serwisy:**
- `App\Services\Production\JobCreator::createFromOrder(Zamowienie $z): ZadanieProdukcyjne`
- `App\Services\Production\KanbanService::moveCard(EtapProdukcji $e, string $newStatus): void`
- `App\Services\Production\TimeLogger::start/stop(EtapProdukcji $e, Operator $op): void`

**Eventy:** `ProductionJobCreated`, `ProductionStarted`, `ProductionStageUpdated` (broadcast), `ProductionCompleted`.

**Tabele:** `zadania_produkcyjne`, `etapy_produkcji`, `maszyny`, `operatorzy`.

**UX:** `/production/kanban` — drag-drop kolumn (Prepress → Print → Postpress → Packaging → Done). Komponenty shadcn-vue: `Card`, `Badge`, `Avatar`, `Tooltip`. Drag-drop: `@vueuse/integrations` + `sortablejs`. Reverb nasłuchuje `production.kanban` (presence) — wszyscy operatorzy widzą zmiany natychmiast.

**Granice:** Operator nie widzi kosztów/marż (policy `ProductionStagePolicy`). Widzi tylko: nazwę zamówienia, produkt, ilość, termin, etap.

---

## MODUŁ 9 — INVENTORY (MVP uproszczony)

**Odpowiedzialność:** stan magazynu, alerty low-stock, automatyczne odpisy zużycia z produkcji.

**Serwisy:**
- `App\Services\Inventory\StockManager::in/out(PozycjaMagazynu $p, float $qty, ...): TransakcjaMagazynowa`
- `App\Services\Inventory\LowStockAlerter` — cron.

**Eventy:** `LowStockDetected`, `StockAdjusted`.

**Tabele:** `pozycje_magazynu`, `transakcje_magazynowe`.

**UX:** prosta lista + wykres zużycia; pełna wizualizacja — Faza 3.

---

## MODUŁ 10 — FINANCE (orkiestrator dokumentów)

**Odpowiedzialność:** generowanie Proformy → Zaliczkowej → VAT/Paragon → Korekta, integracja Subiekt nexo, KSeF, MPP flag, linki Przelewy24.

**Serwisy:**
- `App\Services\Finance\ProformaGenerator::generate(Zamowienie $z): DokumentFinansowy`
- `App\Services\Finance\InvoiceOrchestrator::handlePayment(Platnosc $p): void`
- `App\Services\Finance\MppEvaluator::isRequired(DokumentFinansowy $d): bool`
- `App\Services\Finance\KsefDispatcher` (MVP: przez Subiekt; Faza 4: bezpośrednio)

**Eventy:** `ProformaCreated`, `PaymentReceived`, `InvoiceIssued`, `CorrectionIssued`, `KsefSubmitted`.

**Tabele:** `dokumenty_finansowe`, `platnosci`, `metody_platnosci`.

**UX:** `/finance/documents` lista + filtry; widok dokumentu z PDF embed (z `pliki`).

**Orkiestracja:** patrz [06_WORKFLOW_I_EVENTY.md](./06_WORKFLOW_I_EVENTY.md) sekcja 6.

---

## MODUŁ 11 — LOGISTICS

**Odpowiedzialność:** wybór kuriera, generowanie etykiet, tracking, obsługa zwrotów.

**Serwisy:**
- `App\Services\Logistics\ShippingLabelService::createLabel(Przesylka $p): Plik`
- `App\Services\Logistics\TrackingService::updateFromWebhook(array $payload): void`
- `App\Services\Logistics\CourierSelector::recommendFor(Zamowienie $z): string` — sugestia kuriera (np. InPost paczkomat dla małych, DPD dla dużych).

**Eventy:** `ShipmentCreated`, `ShipmentDispatched`, `DeliveryConfirmed`, `DeliveryFailed`.

**Tabele:** `przesylki`, `adresy_wysylki`.

**Adaptery:** `App\Integrations\InPost\*`, `App\Integrations\Dpd\*` itd. (patrz 10).

**UX:** w widoku zamówienia — sekcja „Wysyłka" z wyborem kuriera, guzikiem „Generuj etykietę" (job async → plik PDF → widoczny w DAMS).

---

## MODUŁ 12 — COMPLAINTS (RMA)

**Odpowiedzialność:** rejestracja reklamacji, SLA timery, dowody, decyzja (przedruk / zwrot częściowy / pełny / odrzucenie).

**Serwisy:**
- `App\Services\Complaints\ComplaintCreator::create(Zamowienie $z, array $data): Reklamacja`
- `App\Services\Complaints\ReprintOrderCreator::reprint(Reklamacja $r): Zamowienie`
- `App\Services\Complaints\CorrectionInvoiceTrigger` — eventem do Finance.
- `App\Services\Complaints\SlaTimer` — cron, alert gdy `submitted_at + 48h` bez odpowiedzi.

**Eventy:** `ComplaintSubmitted`, `ComplaintApproved`, `ComplaintRejected`, `ReprintOrderCreated`, `RefundIssued`.

**Tabele:** `reklamacje`, `dowody_reklamacji`.

**UX:** `/complaints` + `/complaints/{id}`. Klient zgłasza z portalu (`/portal/orders/{id}/complain`).

---

## MODUŁ 13 — COMMHUB

**Odpowiedzialność:** jednolita komunikacja — IMAP poll, outbound email/SMS, in-app notifications, templates, threading.

**Serwisy:**
- `App\Services\Comm\ImapPoller` — `App\Console\Commands\ImapPollCommand` co 2 min.
- `App\Services\Comm\InboundMessageHandler` — dedupe po `external_message_id`, match klienta po `From:`.
- `App\Services\Comm\TemplateRenderer::render(string $templateName, array $vars): string`
- `App\Services\Comm\NotificationDispatcher::dispatch(User $u, NotificationData $n): void`
- `App\Services\Comm\SmsSender` — SMSAPI adapter.

**Eventy:** `MessageReceived`, `MessageSent`, `NotificationCreated` (broadcast), `ThreadClosed`.

**Tabele:** `watki_komunikacji`, `wiadomosci`, `zalaczniki_wiadomosci`, `szablony_wiadomosci`, `powiadomienia`.

**UX:** `/inbox` — widok listy wątków + czat w prawym panelu; bell icon w navbar z Reverb broadcast.

---

## MODUŁ 14 — REPORTS / BI

**Odpowiedzialność:** dashboardy, eksport CSV/XLSX, materialized views.

**Serwisy:**
- `App\Services\Reports\DashboardDataAggregator::getForRole(User $u): array`
- `App\Services\Reports\CsvExporter`, `XlsxExporter` (biblioteka: `openspout/openspout` lub `maatwebsite/excel`).

**Eventy:** żadne (tylko odczyt).

**Tabele:** Materialized views (patrz 04 sekcja 15).

**UX:** `/dashboard` (role-dependent), `/reports` (menedżer, księgowość). Wykresy Chart.js + vue-chartjs. Filtry dat, kategorii.

---

## MODUŁ 15 — RBAC / SETTINGS

**Odpowiedzialność:** role, uprawnienia, 2FA, ustawienia systemu.

**Serwisy:**
- `App\Services\Auth\PermissionSeeder` — seed 6 ról.
- `App\Services\Auth\AuditObserver` — hook na modele krytyczne.

**Tabele:** `uzytkownicy`, `role`, `permissions`, `audit_logs`.

**UX:** Filament — `UserResource`, `RoleResource`. 2FA enroll w `/user/profile` (Fortify).

Szczegóły: [11_BEZPIECZENSTWO_I_RBAC.md](./11_BEZPIECZENSTWO_I_RBAC.md).

---

## MODUŁ 16 — INTEGRATIONS

**Odpowiedzialność:** adaptery wszystkich integracji zewnętrznych. Resilience (retry + DLQ), idempotencja, weryfikacja webhooków.

Szczegóły: [10_INTEGRACJE_ZEWNETRZNE.md](./10_INTEGRACJE_ZEWNETRZNE.md).

---

## MODUŁ 17 — SHOP (Faza 3)

**Odpowiedzialność:** sklep e-commerce B2C/B2B, koszyk, guest checkout, promocje, live pricing.

**Serwisy:**
- `App\Services\Shop\CartService` — dodawanie, usuwanie, live re-pricing, promo codes.
- `App\Services\Shop\CheckoutService` — tworzy `Zamowienie` + `Klient` (guest → user po zapłacie).
- `App\Services\Shop\PromoCodeValidator`.
- `App\Services\Shop\OmnibusPriceTracker` — zapisuje `produkty_historia_cen`.

**Eventy:** `CartUpdated`, `CheckoutStarted`, `OrderCreatedFromShop`, `GuestConvertedToUser`.

**Tabele:** `koszyki`, `pozycje_koszykow`, `kody_promocyjne`, `produkty_historia_cen`.

**UX:**
- `/` — homepage (kategorie + promo).
- `/shop/product/{slug}` — strona produktu z konfiguratorem Vue (live pricing).
- `/cart` — koszyk.
- `/checkout` — wieloetapowy wizard.
- `/confirm/{hash}` — magic-link post-payment → "dokończ rejestrację".

**Pricing integration:** `POST /api/pricing/calculate` (patrz 07) z debouncem 300 ms z frontend Vue.

---

## MODUŁ 18 — DESIGN EDITOR (Faza 3)

**Odpowiedzialność:** edytor online szablonów (wizytówki, ulotki, plakaty), autosave, eksport CMYK PDF z preflight.

**Serwisy:**
- `App\Services\Design\SceneAutoSaver` — zapisuje `scene_state` co 5 s (kompresja JSON).
- `App\Services\Design\PdfExporter` — job async, używa headless browser (Puppeteer via subproces) + jsPDF. Output 300 DPI CMYK + 3 mm bleed.
- `App\Services\Design\TemplateLibraryService`.

**Eventy:** `DesignSubmittedForApproval`, `DesignExported`.

**Tabele:** `projekty_graficzne`, `wersje_projektow`, `komentarze_projektow`.

**UX:** `/designer/{product_slug}` — canvas Konva.js, sidebar z warstwami, biblioteka szablonów, undo/redo stack (Vue state).

**Granice:** Nie dotyka moduł DAMS bezpośrednio — eksportowany PDF trafia do DAMS przez `FileUploader`.

---

## Mapa zależności (kto na kim polega)

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
```

**Żelazna zasada:** moduły komunikują się wyłącznie przez **eventy** lub **interfejsy serwisów** (DI). Brak bezpośrednich zapytań Eloquent między domenami w Controllers — tylko w Services danego modułu.
