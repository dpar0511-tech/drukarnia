# 10 — INTEGRACJE ZEWNĘTRZNE

Wszystkie integracje zewnętrzne zapakowane w adapters w `app/Integrations/{Nazwa}/`. Nikt poza modułem 16 nie wywołuje zewnętrznego API bezpośrednio — wszystko przez interfejs adaptera, co pozwala mockować w testach.

Framework: **Saloon v4** (`saloonphp/saloon`) — strukturyzowane Connectory + Requesty, retry logic, middleware, idempotency.

---

## 1. Architektura adaptera

```
app/Integrations/{Nazwa}/
├── Connector.php              # Saloon Connector (base URL, auth, default headers)
├── Contracts/
│   └── NazwaAdapterContract.php   # interfejs
├── NazwaAdapter.php            # implementacja (wywołuje Connector)
├── Requests/
│   ├── CreateThingRequest.php
│   └── ...
├── Responses/                  # DTO z odpowiedzi
├── Exceptions/
│   └── NazwaIntegrationException.php
└── Tests/                      # Http::fake() tests
```

`AppServiceProvider::register()`:
```php
$this->app->bind(NazwaAdapterContract::class, NazwaAdapter::class);
```

Zawsze wstrzykuj **interfejs**, nie konkretną klasę.

---

## 2. Wzór resilience (wspólny dla wszystkich adapterów)

### Retry policy

```php
public function send(string $endpoint, array $data): array
{
    return retry(
        3,                                       // próby
        fn () => $this->connector->send(new SomeRequest($data))->json(),
        fn ($attempt) => [0, 30_000, 300_000][$attempt - 1] ?? 0   // backoff ms: 0 / 30s / 5min
    );
}
```

Po 3 nieudanych → wyjątek, job trafia do `failed_jobs` + event `IntegrationFailure` → alert Admina.

### Idempotency key

```php
public function createShipment(array $data): Shipment
{
    $idemKey = hash('sha256', json_encode($data));
    $existing = WebhookEvent::where('provider_event_id', $idemKey)->first();
    if ($existing?->processed_at) return $existing->payload;   // zwróć cached
    // … call API …
    WebhookEvent::create([...]);
}
```

### Circuit breaker (Faza 2 opcjonalnie)
`ganeshkesari/laravel-circuit-breaker`: po 5 kolejnych failach adapter wchodzi w stan OPEN (20 min), returning cached/defaults/alert.

---

## 3. Weryfikacja webhooków (inbound)

Każdy webhook sprawdzany **3 razy:**
1. IP whitelist (konfiguracja adaptera) lub reverse-DNS.
2. Signature HMAC / CRC (każdy dostawca ma własny).
3. Idempotency UNIQUE w tabeli `webhook_events`.

**Przykład Przelewy24 CRC:**
```php
$expected = md5("{$sessionId}|{$orderId}|{$amount}|{$currency}|{$crcKey}");
if ($expected !== $r->input('sign')) abort(401);
```

---

## 4. Katalog integracji

### 4.1 Subiekt nexo

**Rola:** generowanie Proform, Faktur Zaliczkowych, Faktur VAT, Paragonów, Korekt; sync stanu magazynu (materiałów); wysyłka do KSeF.

**API:** Subiekt Sfera REST (subskrypcja) — endpoint lokalny w sieci firmy, Subiekt działa na Windows.

**Uwaga:** MVP zakłada, że Subiekt nexo działa na tym samym serwerze lub w LAN drukarni. Jeśli nie — **reverse tunnel / VPN wymagany**.

**Klasa:** `App\Integrations\Subiekt\SubiektAdapter implements SubiektAdapterContract`.

**Metody publiczne:**
```php
createContrahent(Klient $k): string            // zwraca ID w Subiekt
issueProforma(Zamowienie $z): DokumentFinansowy;
issueInvoiceAdvance(Zamowienie $z, Platnosc $p): DokumentFinansowy;
issueInvoiceVat(Zamowienie $z): DokumentFinansowy;
issueParagon(Zamowienie $z): DokumentFinansowy;
issueCorrection(DokumentFinansowy $base, string $reason): DokumentFinansowy;
checkPaymentStatus(string $docNumber): string;
dispatchToKsef(DokumentFinansowy $d): string;   // zwraca KSeF number
```

**Resilience:** jeśli Subiekt offline → job w kolejce `webhooks`, retry 0/30s/5min, potem DLQ + alert. **Zamówienie nie blokuje się** — FSM przechodzi do `OCZEKUJE_NA_PLATNOSC` z `dokument_id = null`, listener asynchronicznie dopina po sukcesie Subiekta.

**Konfiguracja:** `config/services.php`:
```php
'subiekt' => [
    'endpoint' => env('SUBIEKT_ENDPOINT', 'http://localhost:5555'),
    'token'    => env('SUBIEKT_TOKEN'),
    'company'  => env('SUBIEKT_COMPANY_NIP'),
],
```

### 4.2 GUS API (REGON/BIR1)

**Rola:** walidacja NIP przy tworzeniu klienta B2B + auto-uzupełnienie nazwy, REGON, adresu.

**API:** GUS REGON BIR1 — SOAP (tak, SOAP; alternatywa: `bir1.stat.gov.pl/BIR/UslugaBIRzewnPubl/UslugaBIRzewnPubl.svc`).

**Klucz API:** darmowy po rejestracji.

**Klasa:** `App\Integrations\Gus\GusAdapter`.

```php
validate(string $nip): ?GusCompanyData;
```

**Cache:** 24 h w Redis (`gus:nip:{nip}`).

**Rate limit:** 10 req/s po stronie GUS.

### 4.3 Przelewy24

**Rola:** bramka płatności — BLIK, przelew ekspresowy, karta. Webhook potwierdzenia.

**API:** REST (secure.przelewy24.pl/api/v1).

**Klasa:** `App\Integrations\Przelewy24\Przelewy24Adapter`.

```php
createTransaction(DokumentFinansowy $d): string;  // zwraca paymentUrl
verify(string $sessionId, string $orderId): bool;  // confirm po webhooku
```

**Weryfikacja webhooka:** CRC MD5 (sekcja 3).

**Kolejka webhooka:** `webhooks`, priorytet wysoki.

### 4.4 InPost ShipX

**Rola:** paczkomaty InPost — tworzenie przesyłki, drukowanie etykiety, tracking.

**API:** ShipX API (Bearer token).

**Klasa:** `App\Integrations\InPost\InPostAdapter`.

```php
createShipment(Przesylka $p): array;               // {tracking_number, label_url}
downloadLabel(string $trackingNumber): string;     // PDF binary
findLockers(string $postalCode): array;            // paczkomaty w okolicy
```

**Webhook status:** `/webhooks/inpost` → `WebhookEvent` → `UpdateShipmentStatus` job.

### 4.5 DPD / DHL / GLS

Analogiczne — każdy ma własny Connector.

**DPD API:** DPD Services (WebAPI), token.
**DHL 24:** REST, basic auth.
**GLS:** XML/REST hybryda.

Wspólny interfejs `CourierAdapterContract`:
```php
interface CourierAdapterContract {
    public function createShipment(Przesylka $p): ShipmentResult;
    public function getStatus(string $trackingNumber): string;
    public function downloadLabel(string $trackingNumber): string;
}
```

`ShippingLabelService` wybiera konkretnego adaptera na podstawie `przesylka.method`.

### 4.6 SMSAPI.pl

**Rola:** SMS notyfikacje (wysyłka dostarczona, przypomnienie o płatności).

**API:** REST, Bearer token.

**Klasa:** `App\Integrations\SmsApi\SmsApiAdapter`.

```php
send(string $phone, string $message): string;  // zwraca message_id
```

**Fallback:** gdy SMS fail → automatycznie fallback na email (w `NotificationDispatcher`).

### 4.7 IMAP (poczta przychodząca)

**Rola:** polling skrzynki zamówień drukarni, parsowanie maili, dedupe, match klienta.

**Biblioteka:** `webklex/laravel-imap` (PHP IMAP extension wrapper).

**Klasa:** `App\Integrations\Imap\ImapAdapter` + `App\Console\Commands\ImapPollCommand`.

```php
fetchUnseen(): iterable<Email>;
markSeen(Email $e): void;
```

**Scheduler:**
```php
$schedule->command('imap:poll')->everyTwoMinutes()->withoutOverlapping();
```

**Worker:** 1 (nie może być race condition — tylko 1 proces pobiera UIDy).

**Dedupe:** UNIQUE na `wiadomosci.external_message_id` (Message-ID z nagłówka).

**Match klienta:** po `From:` → `klienci.email_main` lub `osoby_kontaktowe.email`. Brak matcha → utwórz wątek z pustym `klient_id`, flaga „do manual assign" dla menedżera.

### 4.8 Mail outbound (Mailgun / SMTP)

**MVP:** SMTP (np. Gmail App Password, SendGrid, SMTP firmy).
**Faza 2:** Mailgun (wysyłka masowa).

**Konfiguracja:** `config/mail.php` standard Laravela.

Templates renderowane przez `TemplateRenderer` — podstawione zmienne `{{client_name}}`, `{{order_number}}`, `{{payment_link}}` itd.

### 4.9 KSeF (opcjonalnie, Faza 4)

**MVP/Faza 1-3:** przez Subiekt nexo.
**Faza 4:** własna integracja.

**API:** KSeF REST, autentykacja przez token lub pieczęć kwalifikowana.

Złożoność: musi być zgodne z aktualną schematyką FA(2) XML, obowiązkowe pola, walidacja, retry.

Opuszczamy MVP świadomie — Subiekt jest certified.

---

## 5. Tabela wszystkich adapterów

| Adapter | Protocol | Auth | Kierunek | Krytyczność MVP |
|---------|----------|------|----------|-----------------|
| Subiekt | REST (lokalny) | Token | → out | Wysoka (dokumenty) |
| GUS | SOAP | Session key | → out | Średnia (NIP auto-fill) |
| Przelewy24 | REST | API key + CRC | in + out | Wysoka (płatności) |
| InPost ShipX | REST | Bearer | in + out | Wysoka (dominujący kurier w PL) |
| DPD / DHL / GLS | REST/SOAP | różne | in + out | Niska MVP (tylko InPost) |
| SMSAPI | REST | Bearer | → out | Niska MVP (Faza 2) |
| IMAP | IMAP4 | Password | in | Wysoka (źródło zamówień) |
| SMTP/Mailgun | SMTP/REST | Password/Key | → out | Wysoka (komunikacja) |
| KSeF | REST | Token/eID | → out | Niska MVP (via Subiekt) |

---

## 6. Testowanie integracji

**Zasada:** zero prawdziwych wywołań zewnętrznych w testach. Wszystko przez `Http::fake()` lub `Saloon::fake()`.

```php
Saloon::fake([
    CreateSubiektProformaRequest::class => MockResponse::make(['id' => 'PRO/2026/0001'], 201),
]);

$svc = app(ProformaGenerator::class);
$doc = $svc->generate($order);

Saloon::assertSent(CreateSubiektProformaRequest::class);
```

Szczegóły strategii testów: [12_STRATEGIA_TESTOWA.md](./12_STRATEGIA_TESTOWA.md).

---

## 7. Monitoring i alerty

### Tabela `webhook_events`
Każdy inbound webhook (sukces i fail) loguje się do tej tabeli. Query dashboardu:
```sql
SELECT provider, COUNT(*) FILTER (WHERE processed_at IS NOT NULL) as ok,
       COUNT(*) FILTER (WHERE processed_at IS NULL AND error IS NOT NULL) as failed
FROM webhook_events WHERE created_at > NOW() - INTERVAL '24 hours'
GROUP BY provider;
```

### Laravel Pulse cards
- „Webhooks last 24h" — licznik per provider.
- „Failed integrations" — `failed_jobs` w ostatniej godzinie.
- „Avg response time" — per Connector (Saloon middleware log).

### Alerting
- Slack/email gdy `failed_jobs` > 10 w ciągu 1 h dla jednego providera.
- Slack gdy Subiekt offline (failed health check 3× z rzędu).

---

## 8. Konfiguracja środowisk

`.env`:
```
SUBIEKT_ENDPOINT=http://10.0.0.5:5555
SUBIEKT_TOKEN=...
SUBIEKT_COMPANY_NIP=1234567890

GUS_API_KEY=...
GUS_ENDPOINT=https://bir1.stat.gov.pl/...

PRZELEWY24_MERCHANT_ID=...
PRZELEWY24_POS_ID=...
PRZELEWY24_CRC_KEY=...
PRZELEWY24_API_KEY=...
PRZELEWY24_ENV=sandbox

INPOST_TOKEN=...
INPOST_ORGANIZATION_ID=...

IMAP_HOST=imap.firma.pl
IMAP_PORT=993
IMAP_USER=zamowienia@drukarnia.pl
IMAP_PASSWORD=...

MAIL_MAILER=smtp
MAIL_HOST=...
MAIL_FROM_ADDRESS=no-reply@drukarnia.pl

SMSAPI_TOKEN=...
```

**Nigdy** nie commitować `.env`. Rotacja kluczy przy każdym odejściu pracownika.

---

## 9. Fallback / graceful degradation

- **Subiekt offline:** zamówienie postępuje; proforma generowana async po powrocie. Klient nie widzi błędu.
- **Przelewy24 offline:** generujemy ręczny link przelew tradycyjny (manual); menedżer ręcznie oznacza opłacone.
- **InPost offline:** menedżer może wybrać innego kuriera (DPD/DHL) lub odbiór osobisty.
- **GUS offline:** pole NIP przyjmuje manual input (walidacja formatu checksum algorithm) — bez auto-fill nazwy.
- **IMAP offline:** notyfikacja Admina, zamówienia nie tworzą się automatycznie; menedżer wprowadza ręcznie w Filamencie.
- **SMS offline:** fallback na email.

**Zasada:** biznes nie zatrzymuje się, gdy integracja pada. Każdy manual override loguje się w `audit_logs` z powodem.
