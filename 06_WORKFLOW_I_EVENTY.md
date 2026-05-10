# 06 — WORKFLOW I EVENTY

## 1. FSM zamówienia — 19 stanów

Implementacja: `spatie/laravel-model-states`. Klasa bazowa `App\States\Order\OrderState` extends `State`. 19 konkretnych stanów, każdy jako osobna klasa. Przejścia: `App\States\Order\Transitions\{NazwaTransition}`.

### Diagram stanów

```
                  ┌────────┐
  ─ create ─────► │ NOWE   │
                  └───┬────┘
                      │ trigger: OrderCreated → Pricing start
                      ▼
                  ┌─────────┐
                  │ WYCENA  │─── PriceCalculated ──┬─► OCZEKUJE_NA_PLIKI
                  └─────────┘                       │
                      │                             └─► OCZEKUJE_NA_PLATNOSC (B2C, Proforma)
                      │
                      │ CancelOrder                        
                      ▼                                     
                 ┌────────────┐                            
                 │ ANULOWANE  │◄── z każdego stanu przed IN_PRODUCTION
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
  W_PRODUKCJI
     │ ProductionCompleted
     ▼
  GOTOWE_DO_WYSYLKI
     │
     ├── SelectShipmentPickup ──► ODBIOR_OSOBISTY ──► ZAKONCZONE
     └── ShipmentDispatched ────► WYSLANE ──► DOSTARCZONE ──► ZAKONCZONE

  W dowolnym momencie po dostawie:
  DOSTARCZONE | ZAKONCZONE ──► REKLAMACJA (gdy klient zgłosi)
                               ├── approved → (ReprintOrder linkowane) → ZAKONCZONE
                               └── rejected → ZAKONCZONE

  SUSPENDED — stan pomocniczy, reversible do poprzedniego (np. brak plików > 7 dni)
```

### Tabela stanów (z kodami dla `status` VARCHAR(32))

| Kod | Klasa | Opis | Następne legalne |
|-----|-------|------|-----------------|
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

### Implementacja w Laravel

```php
// app/Models/Orders/Zamowienie.php
use Spatie\ModelStates\HasStates;

class Zamowienie extends Model
{
    use HasStates;

    protected function casts(): array
    {
        return ['status' => OrderState::class];
    }
}

// app/States/Order/OrderState.php
abstract class OrderState extends State
{
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(New::class)
            ->allowTransition(New::class, Pricing::class)
            ->allowTransition(Pricing::class, WaitingFiles::class, PricingToWaitingFiles::class)
            ->allowTransition(Pricing::class, WaitingPayment::class, PricingToWaitingPayment::class)
            // … wszystkie przejścia …
            ;
    }
}
```

Przejście w kontrolerze/serwisie:
```php
$zamowienie->status->transitionTo(WaitingFiles::class);
```

Transition class może mieć `handle()` z side-effectami (event dispatch, notyfikacje).

---

## 2. Katalog eventów

Każdy event to `readonly class` w `app/Events/*`. Konwencja nazw: czas przeszły (`OrderCreated`, nie `CreateOrder`).

### 2.1 Orders

| Event | Payload | Listenery (queue) |
|-------|---------|-------------------|
| `OrderCreated` | `Zamowienie` | `TriggerInitialPricing` (pricing), `AddToCommThread` (default), `NotifyManager` (emails), `LogAuditEntry` (default) |
| `OrderStatusChanged` | `Zamowienie`, `from`, `to` | `BroadcastOrderStatusToClient` (default, broadcast), `LogAuditEntry` |
| `OrderCancelled` | `Zamowienie`, `reason` | `CancelPendingProductionJob`, `RefundIfPaid` |
| `OrderDuplicated` | `source`, `new` | `CopyFileLinks` |

### 2.2 Pricing

| Event | Payload | Listenery |
|-------|---------|-----------|
| `PriceCalculated` | `PricingResult`, `Zamowienie`, `PozycjaZamowienia` | `CreateProformaIfRequired` (webhooks), `NotifyClientAboutQuote` (emails), `SavePricingSnapshot` (pricing), `UpdateOrderToWaitingStep` (default) |
| `PriceRecalculated` | analogicznie | `InvalidateOldSnapshots` (mark inactive) |

### 2.3 DAMS

| Event | Payload | Listenery |
|-------|---------|-----------|
| `FileUploaded` | `Plik`, `WersjaPliku`, `uploader` | `GenerateThumbnailsJob` (thumbnails), `CheckIfAllRequiredPresent` (default) |
| `FileVersionAdded` | `Plik`, `WersjaPliku` | `NotifyWatchers` (emails), `TriggerReapproval` (jeśli approval był approved) |

### 2.4 Approvals

| Event | Payload |
|-------|---------|
| `ApprovalRequested` | `ZadanieAkceptacji` (z tokenem) → email do klienta z linkiem publicznym |
| `ApprovalApproved` | → `transition(WaitingProduction)`, create `ZadanieProdukcyjne`, notify manager |
| `ApprovalRevisionRequested` | → `transition(Revision)`, notify designer, increment `round_number` |
| `ApprovalRejected` | → `transition(Rejected)`, email klient |
| `ApprovalExpired` | (cron) → reminder po 48 h, suspend po 7 dniach |

### 2.5 Production

| Event | Listenery |
|-------|-----------|
| `ProductionJobCreated` | `AddToKanbanBroadcast` |
| `ProductionStarted` | `NotifyClientProductionStarted` (emails) |
| `ProductionStageUpdated` (ShouldBroadcast) | `kanban.*` channel |
| `ProductionCompleted` | `TransitionOrderToReadyToShip`, `NotifyLogisticsManager` |

### 2.6 Finance

| Event | Listenery |
|-------|-----------|
| `ProformaCreated` | `GeneratePaymentLinkJob` (webhooks → P24), `SendProformaEmail` (emails) |
| `PaymentReceived` (z webhooka P24) | `MarkDocumentPaid`, `CreateInvoiceAdvanceIfB2B`, `RecalculateLoyaltyTier` |
| `InvoiceIssued` | `SendInvoiceEmail`, `DispatchToKsef` (webhooks) |
| `KsefSubmitted` | `StoreKsefNumber` |
| `CorrectionIssued` | `SendCorrectionEmail` |

### 2.7 Logistics

| Event | Listenery |
|-------|-----------|
| `ShipmentCreated` | `GenerateLabelJob` (webhooks) |
| `ShipmentDispatched` | `TransitionOrderToShipped`, `SendTrackingEmail`, `CreateVatInvoiceForB2B` |
| `DeliveryConfirmed` (webhook) | `TransitionOrderToDelivered`, `SendThankYouEmail` |

### 2.8 Complaints

| Event | Listenery |
|-------|-----------|
| `ComplaintSubmitted` | `CreateCommThread`, `NotifyManager`, `StartSlaTimer` |
| `ComplaintApproved` | `CreateReprintOrUpdateDecision` |
| `ReprintOrderCreated` | `LinkToParent`, `NotifyProduction` |
| `RefundIssued` | `CreateCorrectionInvoice` (Finance) |

### 2.9 Communication

| Event | Listenery |
|-------|-----------|
| `MessageReceived` (IMAP) | `MatchClientAndThread`, `NotifyThreadParticipants` |
| `MessageSent` | `MarkDelivered`, `UpdateThreadLastMessageAt` |
| `NotificationCreated` (ShouldBroadcast) | `user.{id}` channel |

---

## 3. Przykładowa implementacja eventu i listenera

```php
// app/Events/PriceCalculated.php
namespace App\Events;

use App\Services\Pricing\ValueObjects\PricingResult;
use App\Models\Orders\Zamowienie;
use App\Models\Orders\PozycjaZamowienia;

class PriceCalculated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly PricingResult $result,
        public readonly Zamowienie $order,
        public readonly PozycjaZamowienia $item,
    ) {}
}

// app/Listeners/CreateProformaIfRequired.php
namespace App\Listeners;

use App\Events\PriceCalculated;
use App\Services\Finance\ProformaGenerator;

class CreateProformaIfRequired implements ShouldQueue
{
    public string $queue = 'webhooks';

    public function __construct(private ProformaGenerator $generator) {}

    public function handle(PriceCalculated $event): void
    {
        if ($event->order->klient->typ === 'B2C') {
            $this->generator->generate($event->order);
        }
        // B2B: proforma generowana później, po akceptacji ręcznej
    }
}
```

Rejestracja w `app/Providers/EventServiceProvider.php` lub przez `Event::listen()` auto-discovery.

---

## 4. Orkiestracja Finance — flow dokumentów

```
PriceCalculated
    │
    ▼
Proforma (Subiekt + P24 payment link) ──→ email do klienta
    │
    │  PaymentReceived (webhook P24)
    ▼
Faktura Zaliczkowa (tylko B2B, po wpłacie zaliczki)
    │
    ▼
ShipmentDispatched
    │
    ├── B2B ──→ Faktura VAT (Subiekt → KSeF)
    └── B2C ──→ Paragon (lub Faktura na życzenie)
    │
    ▼
Ew. CorrectionIssued (reklamacja → Faktura Korygująca)
```

**MPP flag:**
```php
public function isRequired(DokumentFinansowy $d): bool
{
    return $d->amount_brutto > 15000
        && $d->zamowienie->klient->typ === 'B2B'
        && $this->containsGoodsFromAppendix15($d->zamowienie);
}
```

---

## 5. Queue topology i DLQ

(Patrz też [03_ARCHITEKTURA_SYSTEMU.md](./03_ARCHITEKTURA_SYSTEMU.md) sekcja 6.)

Kolejki priorytetyzowane:

| Queue | Priorytet | Typowe jobs |
|-------|-----------|-------------|
| `default` | normal | Listenery domenowe, walidacja, update modeli |
| `pricing` | high | PricingPipeline asynchroniczny (np. dla batch recalculation) |
| `emails` | high | Wysyłka maili (klienci czekają) |
| `sms` | high | SMS notyfikacje |
| `webhooks` | high | Wywołania Subiekt, P24, kurierzy — na nich polega workflow |
| `imap` | low | Polling IMAP (1 worker!) |
| `preflight`, `thumbnails` | low | Ciężkie zadania na plikach |
| `reports` | low | Odświeżanie materialized views |

**Retry policy** (attempt → delay):
1. Immediate.
2. +30 s.
3. +5 min.
→ Po 3 próbach: job w `failed_jobs` + alert do Admina.

Implementacja:
```php
class GenerateLabelJob implements ShouldQueue
{
    public int $tries = 3;
    public array $backoff = [0, 30, 300];
    public function retryUntil(): \DateTime { return now()->addHours(2); }
}
```

---

## 6. Idempotency keys

Przy integracjach zewnętrznych **UNIQUE** index chroni przed duplikatami:

| Tabela | Kolumna idempotencyjna |
|--------|-----------------------|
| `wiadomosci` | `external_message_id` UNIQUE NULL — Message-ID z IMAP |
| `platnosci` | `transaction_id_provider` UNIQUE — ID transakcji P24 |
| `webhook_events` (nowa, ogólna) | `provider_event_id` UNIQUE — dowolny webhook |
| `przesylki` | `tracking_number` UNIQUE NULL |

Tabela audytowa `webhook_events`:
```sql
CREATE TABLE webhook_events (
    id BIGSERIAL PRIMARY KEY,
    provider VARCHAR(32),           -- przelewy24|inpost|dpd|subiekt
    provider_event_id VARCHAR(128) UNIQUE,
    payload JSONB,
    signature_valid BOOLEAN,
    processed_at TIMESTAMPTZ,
    error TEXT,
    created_at TIMESTAMPTZ DEFAULT NOW()
);
```

Controller webhooka:
```php
public function przelewy24(Request $r)
{
    $eventId = $r->input('sessionId').':'.$r->input('orderId');
    WebhookEvent::firstOrCreate(
        ['provider' => 'przelewy24', 'provider_event_id' => $eventId],
        ['payload' => $r->all(), 'signature_valid' => $this->verifyCrc($r)]
    );
    ProcessPrzelewy24WebhookJob::dispatch($eventId);
    return response()->noContent();
}
```

---

## 7. Reguła szczególna: re-kalkulacja ceny

Ceny **nie są** automatycznie odświeżane przy zmianie `GlobalSetting`. Snapshoty w `kalkulacje_ceny` są historyczne. Re-kalkulacja:
1. Ręcznie przyciskiem menedżera „Przelicz cenę" na pozycji.
2. Automatycznie przy każdej zmianie konfiguracji zamówienia (parametry, ilość).
3. **Nigdy** jako side-effect zmiany ustawienia globalnego — cofnęłoby to zamówienia już zaakceptowane przez klientów.

Każde przeliczenie = **nowy rekord** `kalkulacje_ceny` (audit trail). Najnowszy rekord jest "active" (najwyższe `id` dla `(zamowienie_id, pozycja_id)`).
