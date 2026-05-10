# 11 — BEZPIECZEŃSTWO I RBAC

## 1. Autentykacja (Fortify)

`laravel/fortify` — login, register (disabled dla pracowników — seed), forgot password, email verification, opcjonalne TOTP 2FA.

### Konfiguracja `config/fortify.php`

```php
'features' => [
    Features::registration(),              // Tylko dla portalu klienta (Shop Faza 3)
    Features::resetPasswords(),
    Features::emailVerification(),
    Features::updateProfileInformation(),
    Features::updatePasswords(),
    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
        'window' => 0,                     // TOTP dokładny 30s
    ]),
],
```

**Rate limiting:**
```php
RateLimiter::for('login', fn ($r) =>
    Limit::perMinute(5)->by($r->input('email').$r->ip()));
```

### Sanctum dla API (portal klienta, mobile PWA)

Tokeny wydawane z `user->createToken('portal', ['orders.view','files.view'])`. Ability-based permissions na routach API.

### Single Sign-On (opcjonalnie, Faza 4)

Azure AD / Google Workspace dla pracowników — `laravel/socialite` + adapter. Nie w MVP.

---

## 2. RBAC — spatie/laravel-permission

6 ról seedowanych w `PermissionSeeder`:

```php
$admin   = Role::create(['name' => 'admin']);
$manager = Role::create(['name' => 'menedzer']);
$designer = Role::create(['name' => 'projektant']);
$operator = Role::create(['name' => 'operator']);
$acc      = Role::create(['name' => 'ksiegowosc']);
$client   = Role::create(['name' => 'klient']);
```

### Granularne uprawnienia

```
auth.*                  → admin, menedzer
clients.view            → admin, menedzer, ksiegowosc
clients.create, edit    → admin, menedzer
clients.delete          → admin
clients.anonymize       → admin                 (RODO hard delete)
orders.view             → admin, menedzer, ksiegowosc
orders.create, edit     → admin, menedzer
orders.cancel           → admin, menedzer
orders.view_own         → klient (portal)
pricing.view_cost       → admin, menedzer, ksiegowosc           (NIE operator, NIE projektant, NIE klient)
pricing.edit_settings   → admin
pricing.calculate       → admin, menedzer
products.manage         → admin
production.view_kanban  → admin, menedzer, operator
production.log_time     → operator (tylko własne etapy)
production.move_stage   → admin, menedzer
files.upload            → admin, menedzer, projektant, klient
files.view              → auto (przez FilePolicy — per zasób)
approvals.send          → admin, menedzer, projektant
approvals.respond       → klient (przez publiczny token — bez auth)
finance.view            → admin, menedzer, ksiegowosc
finance.issue           → admin, ksiegowosc
finance.correction      → admin, ksiegowosc
logistics.create_label  → admin, menedzer
logistics.track         → admin, menedzer, ksiegowosc
complaints.view         → admin, menedzer, ksiegowosc
complaints.submit       → klient (portal)
complaints.decide       → admin, menedzer
messaging.inbox         → admin, menedzer, projektant, ksiegowosc
messaging.send          → admin, menedzer, projektant
reports.view            → admin, menedzer, ksiegowosc
shop.admin              → admin, menedzer
shop.customer           → wszyscy (klient + guest)
designer.edit           → klient (własne projekty), admin, menedzer
settings.manage         → admin
users.manage            → admin
audit.view              → admin
```

### Policies

Dla zasobów granularnych (plik, zamówienie per klient) — policies. Przykład `FilePolicy` w [08_DAMS_LOKALNE_PRZECHOWYWANIE_PLIKOW.md](./08_DAMS_LOKALNE_PRZECHOWYWANIE_PLIKOW.md#5-bezpieczny-dostęp).

`PricingPolicy::viewCost(User $u): bool` — blokuje widoczność pól `cost_*` dla Operatora i Klienta:
```php
public function viewCost(User $u): bool
{
    return $u->hasAnyPermission(['pricing.view_cost']);
}
```

W Vue prop filtering — Controller Inertia:
```php
return Inertia::render('Orders/Show', [
    'order' => new OrderResource($order)
        ->additional(['show_cost' => $request->user()->can('viewCost', Pricing::class)])
]);
```

Komponent Vue:
```vue
<PricingBreakdown v-if="showCost" :breakdown="order.calculation.breakdown" />
```

### Macierz ról × akcji (skrót)

| Akcja | Admin | Menedżer | Projektant | Operator | Księgowość | Klient |
|-------|:-----:|:--------:|:----------:|:--------:|:----------:|:------:|
| Widoczność kosztów/marż | ✅ | ✅ | ❌ | ❌ | ✅ | ❌ |
| Tworzenie zamówienia | ✅ | ✅ | ❌ | ❌ | ❌ | ✅ (portal) |
| Akceptacja projektu (własne) | ✅ | ✅ | ❌ | ❌ | ❌ | ✅ (portal) |
| Log produkcji (własne etapy) | ✅ | ✅ | ❌ | ✅ | ❌ | ❌ |
| Wystawianie VAT | ✅ | ❌ | ❌ | ❌ | ✅ | ❌ |
| Zarządzanie użytkownikami | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Zmiana ustawień pricingu | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Audit log odczyt | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |

---

## 3. Two-Factor Authentication (2FA)

### TOTP (Google Authenticator, Authy, 1Password)

Zaoferowany w `/user/profile` → strona „Secure Your Account". Fortify generuje QR code, user skanuje, wprowadza kod → 2FA aktywne. Recovery codes (8 sztuk) wyświetlane raz i zapisywane przez usera.

### Rekomendacja

**Wymagane dla Admin, Menedżer, Księgowość.** `MiddlewareForce2fa` w route group `admin.*` + `finance.*`:
```php
Route::middleware(['auth', 'role:admin|menedzer|ksiegowosc', 'enforce-2fa'])->group(...);
```

`EnforceTwoFactor` middleware przekierowuje na `/user/two-factor-required` jeśli user nie ma skonfigurowanego 2FA.

### Dla klientów: opcjonalne
Portal klienta oferuje 2FA, ale nie wymusza (UX — utrata klienta).

### SMS 2FA?
Nie w MVP (SMSAPI koszty + mniej bezpieczne niż TOTP). Faza 2: rozważyć dla Księgowości.

---

## 4. Audit log (spatie/laravel-activitylog)

### Co logować

Modele krytyczne z trait `LogsActivity`:
- `User` — create, update (role_id), soft-delete.
- `Klient` — update, anonymize.
- `Zamowienie` — status changes, cancellations.
- `DokumentFinansowy` — issue, correction.
- `ProductOverride`, `GlobalSetting` — zmiany ustawień pricingu.
- `Maszyna` — pricing per hour (wpływa na RKW).

### Przykład

```php
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class GlobalSetting extends Model
{
    use LogsActivity, SingletonTrait;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->useLogName('pricing_settings')
            ->dontSubmitEmptyLogs();
    }
}
```

### UI odczytu

Filament resource `ActivityLogResource` (tylko dla Admin role). Filtry: log_name, subject_type, causer, date range.

---

## 5. OWASP Top 10 — checklist

| # | Zagrożenie | Ochrona |
|---|-----------|---------|
| A01 | Broken Access Control | `spatie/laravel-permission`, policies, FormRequest, Gate |
| A02 | Cryptographic Failures | `APP_KEY` 32-byte, wszystkie passwords przez `bcrypt`, encrypted casts (`$casts = ['pii' => 'encrypted']`), TLS 1.3 only, HSTS |
| A03 | Injection | Tylko Eloquent / Query Builder; zakaz `DB::raw(string)` z user input; prepared statements wbudowane |
| A04 | Insecure Design | ADR-y, code review, threat modeling per moduł |
| A05 | Security Misconfiguration | `APP_DEBUG=false` w prod, rate limiting, CSP header, headers security middleware |
| A06 | Vulnerable Components | `composer audit`, `npm audit`, Dependabot PRs automatyczne |
| A07 | Identification & Auth Failures | Fortify + rate limit login + 2FA + account lockout po 10 prób |
| A08 | Software & Data Integrity | Signed URLs, CSRF tokens, webhook signatures |
| A09 | Security Logging | activitylog, Sentry, audit tables |
| A10 | Server-Side Request Forgery | Walidacja URL w adapters (whitelist hosts), zakaz follow-redirect do localhost |

### Headers security middleware

```php
// app/Http/Middleware/SecurityHeaders.php
public function handle($request, Closure $next)
{
    $response = $next($request);
    return $response
        ->header('Strict-Transport-Security', 'max-age=63072000; includeSubDomains')
        ->header('X-Content-Type-Options', 'nosniff')
        ->header('X-Frame-Options', 'SAMEORIGIN')
        ->header('Referrer-Policy', 'same-origin')
        ->header('Content-Security-Policy',
            "default-src 'self'; script-src 'self' 'nonce-{$nonce}'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self' data:; connect-src 'self' wss:");
}
```

### CSRF

Laravel wbudowany — `@csrf` w formach Blade + Inertia automatycznie dodaje header `X-XSRF-TOKEN`. Wyjątek: `/webhooks/*` — wyłączyć CSRF (weryfikacja przez HMAC).

### Rate limiting

```php
// RouteServiceProvider
RateLimiter::for('api', fn ($r) => Limit::perMinute(60)->by($r->user()?->id ?? $r->ip()));
RateLimiter::for('login', fn ($r) => Limit::perMinute(5)->by($r->input('email').$r->ip()));
RateLimiter::for('uploads', fn ($r) => Limit::perMinute(10)->by($r->user()->id));
RateLimiter::for('pricing', fn ($r) => Limit::perMinute(30)->by($r->user()?->id ?? $r->ip()));
RateLimiter::for('webhooks', fn ($r) => Limit::perMinute(600)->by($r->ip()));
RateLimiter::for('public-file', fn ($r) => Limit::perMinute(20)->by($r->ip()));
```

---

## 6. RODO / GDPR

### Zgoda (consent)

Portal klienta przy rejestracji: checkbox akceptacji polityki prywatności + opcjonalnej zgody na marketing. `klienci.consent_marketing_at` + `consent_privacy_at`.

### Prawo do bycia zapomnianym

`ClientAnonymizationService::anonymize(Klient $k): void`:
```php
$k->update([
    'nazwa' => 'Anonimowy #'.$k->id,
    'nip' => null,
    'email_main' => null,
    'telefon' => null,
    'adres_ulica' => null,
    'adres_kod' => null,
    'adres_miasto' => null,
    'anonymized_at' => now(),
]);
$k->osoby_kontaktowe()->update(['email' => null, 'telefon' => null, 'imie' => 'Anonim', 'nazwisko' => '']);
$k->watki_komunikacji()->update(['subject' => 'Anonimized']);
// Zamówienia zostają (wymóg podatkowy 5 lat), ale klient_id wskazuje anonimizowanego klienta
```

### Prawo do przenoszenia danych (portability)

`ClientExportService::export(Klient $k): string` — zwraca JSON ze wszystkimi danymi osobowymi klienta + zamówieniami + dokumentami. Klient pobiera z portalu.

### Rejestr czynności przetwarzania

Tabela `rodo_processing_activities` (Faza 2) — lista celów przetwarzania, podstaw prawnych, retencji. Widok admin Filament.

### Data retention

- Konta pracowników (nieaktywne 6 mies) → automatyczne `aktywny=false`.
- Dane klientów bez zamówień przez 3 lata → prompt do anonimizacji.
- Dokumenty księgowe — 5 lat (obowiązek).
- Logi aktywności — 2 lata rolling window.

---

## 7. Prawo polskie

### KSeF (Krajowy System e-Faktur)

MVP: przez Subiekt nexo. Faza 4: bezpośrednia integracja.

### MPP (Mechanizm Podzielonej Płatności)

Flag na fakturze gdy:
- `amount_brutto > 15 000 PLN` AND
- `kupujący.typ = B2B` AND
- Towar/usługa z załącznika 15 VAT (drukarnia: opakowania, folie, ew. papier hurtowo).

`MppEvaluator::isRequired(DokumentFinansowy $d): bool` w `app/Services/Finance/`.

### Rękojmia

Tabela `reklamacje` + SLA timery (48 h response, 14 dni resolution). Patrz [05_MODULY_BIZNESOWE.md#moduł-12--complaints-rma](./05_MODULY_BIZNESOWE.md).

### Ustawa konsumencka — 14 dni odstąpienia

Pole `zamowienia.cancellable_until` = `created_at + 14 dni` dla B2C.
**Wyjątek:** produkty personalizowane (większość druków!) — niecancelowalne. Flag `Product.is_personalized` BOOL DEFAULT TRUE dla druków; klient sklepu widzi wyraźny tekst „Produkt personalizowany — bez prawa odstąpienia".

### Omnibus (najniższa cena z 30 dni)

Tabela `produkty_historia_cen`. Przy każdej zmianie ceny w sklepie → snapshot. Frontend produktu pokazuje „najniższa cena z 30 dni: X PLN" pod aktualną.

---

## 8. Secrets management

### `.env` na VPS

- Plik chroniony `chmod 600`.
- Właściciel `www-data`.
- Nie wpuszczać do kontroli wersji.

### Vault (opcjonalne)

HashiCorp Vault lub AWS Secrets Manager w Fazie 4 dla enterprise setupów multi-server.

### Rotacja

Przy każdym odejściu pracownika z dostępem do produkcji:
- Rotacja `APP_KEY` (invaliduje sesje) — `php artisan key:generate --force`.
- Rotacja haseł do: Subiekt, GUS, Przelewy24, InPost, SMSAPI, SMTP.
- Regeneracja Sanctum tokenów admina (soft-delete, nowe).
- Obrócenie kluczy SSH (usunięcie publickey z `authorized_keys`).

---

## 9. Uploads security (patrz też 08)

- **Magic number check** — `finfo` dla każdego uploadu (nie `mime_content_type` z `$_FILES`).
- **Whitelist MIME** — z `config/uploads.php`.
- **Limit rozmiaru** — 5 GB per plik, plus per-client quota.
- **Filename sanitization** — `Str::slug()` + UUID prefix.
- **Nigdy nie wykonywać plików** — brak `/execute`, brak `include`, tylko download.

---

## 10. Penetration testing checklist (pre-prod)

- [ ] XSS: Reflected / Stored — wstrzyknąć `<script>` w każde pole input.
- [ ] SQL injection: `' OR 1=1--` w search.
- [ ] CSRF: formularz z innej domeny.
- [ ] File upload: `.php`, `.exe`, double extension.
- [ ] Path traversal: `../../../etc/passwd` w parametrach.
- [ ] Auth bypass: direct URL bez loginu.
- [ ] IDOR: zmienić `{id}` w URL na cudze zamówienie.
- [ ] Rate limit bypass: 1000 requestów w 1 min.
- [ ] Webhook forgery: wysłać nieprawidłowy CRC P24.
- [ ] Token replay: użyć tego samego payment webhook 100×.
- [ ] Sesja: hijack + fixation.
- [ ] Open redirect: `?redirect=https://evil.com` po logowaniu.
- [ ] HTTPS: brak mixed content, HSTS.

**Narzędzia:** OWASP ZAP, Burp Suite Community, `sqlmap`, `nikto`, `testssl.sh`.

**Bug bounty:** po Fazie 3 — rozważyć publiczny program (HackerOne).

---

## 11. Polityki sesji

- **Timeout:** 2 h idle, 8 h absolute (prod). Fortify `config/session.php`:
  ```php
  'lifetime' => 120,           // 2 h
  'expire_on_close' => false,
  ```
- **HttpOnly cookie** — `session.php: 'http_only' => true`.
- **Secure cookie** — `'secure' => true` (wymuszone w prod).
- **SameSite strict** — `'same_site' => 'lax'` (strict łamie OAuth flows).
- **Session store:** Redis (nie database) — szybsze + auto-expire.

---

## 12. Incident response plan

1. **Detect:** Sentry alert / Horizon alert / log monitor.
2. **Contain:** szybko — wyłączyć kompromised account (`aktywny=false`), rotacja APP_KEY jeśli wyciek.
3. **Eradicate:** ścieżka eksploitu, patch, test.
4. **Recover:** restore z backupu jeśli potrzebne.
5. **Post-mortem:** w ciągu 7 dni; report dla właściciela; update runbook.

### Breach notification (RODO)
- 72 h na zgłoszenie do UODO.
- Klienci poinformowani „bez zbędnej zwłoki" jeśli wysokie ryzyko.
- Template email gotowy w `resources/mail/breach-notification.blade.php`.
