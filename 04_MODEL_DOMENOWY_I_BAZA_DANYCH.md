# 04 — MODEL DOMENOWY I BAZA DANYCH

Schemat PostgreSQL 16. Wszystkie tabele z kluczami głównymi `BIGSERIAL PRIMARY KEY` chyba że inaczej zaznaczono. Timestampy `created_at`/`updated_at` (TIMESTAMPTZ) dla każdej tabeli domyślnie. Soft-delete (`deleted_at TIMESTAMPTZ NULL`) tam gdzie trzeba retencji (zamówienia, pliki, klienci, dokumenty finansowe).

Indeksowanie kluczowych kolumn FK i wyszukiwalnych (np. `number`, `nip`, `email_main`) jawnie w migracji `->index()`.

---

## 1. RBAC i audyt

### `uzytkownicy`
| Kolumna | Typ | Ograniczenia |
|---------|-----|--------------|
| `id` | BIGSERIAL PK | |
| `imie` | VARCHAR(64) | NOT NULL |
| `nazwisko` | VARCHAR(64) | NOT NULL |
| `email` | VARCHAR(128) | UNIQUE, NOT NULL |
| `email_verified_at` | TIMESTAMPTZ | |
| `password_hash` | VARCHAR(255) | NOT NULL |
| `two_factor_secret` | TEXT | encrypted |
| `two_factor_recovery_codes` | TEXT | encrypted |
| `remember_token` | VARCHAR(100) | |
| `aktywny` | BOOLEAN | DEFAULT TRUE |
| `last_login_at` | TIMESTAMPTZ | |

### `role`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`
Tabele generowane przez `spatie/laravel-permission`. **Nie modyfikować** — używać API paczki.

### `audit_logs`
Tabela generowana przez `spatie/laravel-activitylog`:
```
log_name, description, subject_type, subject_id, event,
causer_type, causer_id, properties JSONB, batch_uuid, created_at
```

Polimorficzny (`subject_*`, `causer_*`). Indeks na `(subject_type, subject_id)` i `(causer_type, causer_id)`.

---

## 2. CRM

### `klienci`
| Kolumna | Typ | Uwagi |
|---------|-----|-------|
| `id` | BIGSERIAL PK | |
| `typ` | VARCHAR(8) | CHECK IN ('B2B','B2C') |
| `nazwa` | VARCHAR(255) | NOT NULL |
| `nip` | VARCHAR(15) | UNIQUE gdy B2B, NULL dla B2C |
| `regon` | VARCHAR(14) | |
| `email_main` | VARCHAR(128) | NOT NULL, INDEX |
| `telefon` | VARCHAR(32) | |
| `adres_ulica` | VARCHAR(255) | |
| `adres_kod` | VARCHAR(10) | |
| `adres_miasto` | VARCHAR(100) | |
| `adres_kraj` | CHAR(2) | DEFAULT 'PL' |
| `prog_lojalnosciowy_id` | BIGINT | FK |
| `blocked` | BOOLEAN | DEFAULT FALSE |
| `annual_revenue_net` | DECIMAL(14,2) | cache denormalizowany — aktualizowany eventami |
| `deleted_at` | TIMESTAMPTZ | soft-delete |
| `anonymized_at` | TIMESTAMPTZ | RODO — po usunięciu pól osobowych |

### `osoby_kontaktowe`
| Kolumna | Typ |
|---------|-----|
| `id`, `klient_id` FK CASCADE, `imie`, `nazwisko`, `email`, `telefon`, `stanowisko`, `is_primary` BOOLEAN |

### `tagi` i `klient_tagi`
Pivot `klient_tagi(klient_id, tag_id)` UNIQUE. Użycie: segmentacja marketingowa.

### `progi_lojalnosciowe`
| Kolumna | Typ |
|---------|-----|
| `id`, `nazwa`, `annual_revenue_threshold` DECIMAL(14,2), `discount_percent` DECIMAL(5,2), `priority_leadtime_days` INT |

---

## 3. Products i Pricing — nazwy angielskie zachowane z `_nowy_SILNIK_CENOWY/04_MODEL_DANYCH.md`

> **Reguła:** nazwy tabel, pól i ograniczeń w tej sekcji zachowują oryginał (np. `material_size`, `pricing_rules`, `cost_per_sheet`) dla 1:1 mapowania z testami i implementacją silnika.

### `global_setting` (singleton pk=1)
```
default_margin_base_pct     DECIMAL(7,2)   DEFAULT 100.00
default_margin_threshold    DECIMAL(10,2)  DEFAULT 500.00
default_margin_above_pct    DECIMAL(7,2)   DEFAULT 30.00
default_waste_pct           DECIMAL(5,1)   DEFAULT 5.0
default_waste_fixed         INT            DEFAULT 10
default_waste_meter_pct     DECIMAL(5,1)   DEFAULT 5.0
default_waste_meter_fixed   DECIMAL(7,1)   DEFAULT 0.0
bleed_mm                    DECIMAL(6,2)   DEFAULT 2.00
sheet_margin_mm             DECIMAL(6,2)   DEFAULT 5.00
updated_at                  TIMESTAMPTZ
```

Trait `SingletonTrait` w modelu: `save()` → `id = 1`, `delete()` → throw.

### `pattern_setting`, `project_setting`, `custom_format_setting`
Również singletony (pk=1). Kolumny z pliku 04 `_nowy_SILNIK_CENOWY`. Wartości domyślne:
- `pattern_setting.default_multiplier = 0.70`
- `project_setting.default_base_price = 50.00`, `default_price_per_page = 10.00`, etykiety „Posiadam własny projekt" / „Zamawiam projekt"
- `custom_format_setting.default_unit = 'mm'`, pozostałe nullable

### `media_format`, `format`, `material`, `material_size`, `zadruk_option`, `time_option`, `page_config`, `parameter`, `parameter_option`, `simple_parameter`, `simple_parameter_option`

Pełne kolumny — patrz [07_SILNIK_CENOWY_INTEGRACJA.md](./07_SILNIK_CENOWY_INTEGRACJA.md) sekcja „Model danych".

Kluczowe:
- `material_size` — UNIQUE `(material_id, media_format_id)`.
- `zadruk_option`, `parameter_option`, `product` — **8 pól CostPriceTrait** (DECIMAL(10,2) NULL): `cost_total`, `cost_per_sheet`, `cost_per_meter`, `cost_per_piece`, `price_total`, `price_per_sheet`, `price_per_meter`, `price_per_piece`.
- `zadruk_option.project_page_count` SMALLINT DEFAULT 0.
- `page_config.label` UNIQUE; accessor `total_pages = cover_pages + interior_pages`.
- `simple_parameter_option.price` DECIMAL(10,2) DEFAULT 0 (**bez CostPriceTrait**).

### `product`
Główna encja produktu + flagi funkcji + 8 pól CostPriceTrait. Szczegóły: plik 07.

### `product_override` (1:1)
Wszystkie pola NULL — NULL oznacza dziedzicz z `global_setting` (lub singletonów). Mapowanie fallback — patrz 07.

### Pivoty M2M z rolą
- `product_format`, `product_material`, `product_zadruk_option`, `product_parameter_option` — mają `role` (`common` | `cover` | `interior`), `is_default`, `sort_order`.
- `product_time_option` — dodatkowo `multiplier_override` DECIMAL(5,2) NULL i `business_days` INT NULL.
- `product_page_config`, `product_simple_parameter_option` — bez roli.
- UNIQUE — patrz 07.

### `simple_product_discount` (1:1 z Product)
```
base_unit_price             DECIMAL(10,2)
discount_start_qty          INT
discount_percent_per_unit   DECIMAL(5,1)
minimum_unit_price          DECIMAL(10,2)
```

### Wykluczenia
- `exclusion_rule(id, product_id, name, description, is_active, sort_order)`
- `exclusion_condition(id, rule_id, target, target_id, target_value)` — `target` z zestawu `format|material|zadruk|parameter_option|page_config|multipage_type`.
- `exclusion_action(id, rule_id, action_type, target_id, target_kind)` — `action_type` ∈ `hide_option|hide_parameter`.

### `kalkulacje_ceny` — snapshot wyniku pipeline'u
```sql
CREATE TABLE kalkulacje_ceny (
    id BIGSERIAL PRIMARY KEY,
    zamowienie_id BIGINT NOT NULL REFERENCES zamowienia(id) ON DELETE CASCADE,
    pozycja_id    BIGINT NOT NULL REFERENCES pozycje_zamowien(id) ON DELETE CASCADE,
    config_snapshot  JSONB NOT NULL,   -- CalculationConfig co wybrano
    engine_result    JSONB NOT NULL,   -- EngineResult
    extras_cost              DECIMAL(12,2) NOT NULL DEFAULT 0,
    product_extra_cost       DECIMAL(12,2) NOT NULL DEFAULT 0,
    total_cost               DECIMAL(12,2) NOT NULL DEFAULT 0,
    margin_amount            DECIMAL(12,2) NOT NULL DEFAULT 0,
    base_price               DECIMAL(12,2) NOT NULL DEFAULT 0,
    pattern_multiplied_price DECIMAL(12,2) NOT NULL DEFAULT 0,
    extras_price             DECIMAL(12,2) NOT NULL DEFAULT 0,
    product_extra_price      DECIMAL(12,2) NOT NULL DEFAULT 0,
    project_price            DECIMAL(12,2) NOT NULL DEFAULT 0,
    time_multiplier          DECIMAL(5,2)  NOT NULL DEFAULT 1.00,
    final_price_net          DECIMAL(12,2) NOT NULL DEFAULT 0,
    loyalty_discount_percent DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
    loyalty_discount_amount  DECIMAL(12,2) NOT NULL DEFAULT 0,
    final_price_net_after_loyalty DECIMAL(12,2) NOT NULL DEFAULT 0,
    vat_rate                 DECIMAL(5,2)  NOT NULL DEFAULT 0.23,
    vat_amount               DECIMAL(12,2) NOT NULL DEFAULT 0,
    final_price_gross        DECIMAL(12,2) NOT NULL DEFAULT 0,
    breakdown                JSONB,               -- full PricingResult dump
    created_by_id BIGINT REFERENCES uzytkownicy(id),
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX idx_kalk_zamowienie ON kalkulacje_ceny(zamowienie_id);
CREATE INDEX idx_kalk_pozycja    ON kalkulacje_ceny(pozycja_id);
CREATE INDEX idx_kalk_snapshot_gin ON kalkulacje_ceny USING GIN (config_snapshot);
```

**Audit trail:** każda re-kalkulacja = NOWY rekord; nigdy UPDATE.

---

## 4. Orders

### `zamowienia`
```
id               BIGSERIAL PK
number           VARCHAR(20) UNIQUE  -- DRK-YYYY-XXXXX
klient_id        BIGINT FK
menedzer_id      BIGINT FK uzytkownicy  -- manager przypisany
status           VARCHAR(32)  -- FSM 16 stanów; w praktyce zarządzane przez spatie/model-states
priority         SMALLINT DEFAULT 5  -- 1..10
source           VARCHAR(16) -- email | shop_guest | shop_user | portal | admin
deadline         DATE
cancellable_until DATE  -- 14 dni konsumenckie B2C
cancelled_at     TIMESTAMPTZ
notatki          TEXT
created_at, updated_at, deleted_at
```

### `pozycje_zamowien`
```
id, zamowienie_id FK CASCADE, product_id FK, quantity INT, format_width_mm DECIMAL(8,2),
format_height_mm DECIMAL(8,2), material_id FK NULL, finish VARCHAR(64) NULL,
special_effects TEXT[] NULL, calculated_price_id BIGINT FK kalkulacje_ceny NULL,
specyfikacja JSONB, notes TEXT
```

### `specyfikacje_produktowe`
Tabela dla trudnych specjalnych pól (zazwyczaj JSONB w `pozycje_zamowien.specyfikacja` wystarcza — odroczyć tę tabelę, chyba że potrzebne relacyjne zapytania).

### `historia_statusow_zamowien`
```
id, zamowienie_id FK CASCADE, status_from VARCHAR(32), status_to VARCHAR(32),
user_id FK NULL, comment TEXT, created_at
```

### Indeksy
- `zamowienia(number)` UNIQUE, `(status)`, `(klient_id)`, `(deadline)`, `(created_at)`
- `pozycje_zamowien(zamowienie_id)`, JSONB GIN na `specyfikacja`

---

## 5. DAMS (pliki) — lokalne!

### `pliki`
```
id                BIGSERIAL PK
uuid              UUID UNIQUE DEFAULT gen_random_uuid()
original_name     VARCHAR(255)
mime_type         VARCHAR(64)
size_bytes        BIGINT
checksum_sha256   CHAR(64)
local_path        VARCHAR(512)    -- np. orders/2026/04/123/source/file.pdf
disk              VARCHAR(32)     -- 'local_private' | 'local_public'
scan_status       VARCHAR(16)     -- pending | clean | infected | skipped
scan_at           TIMESTAMPTZ
uploaded_by_id    BIGINT FK uzytkownicy
deleted_at        TIMESTAMPTZ     -- soft-delete 90 dni
created_at
```

### `wersje_plikow`
```
id, plik_id FK, version_number INT, local_path, size_bytes, checksum_sha256,
comment TEXT, is_active BOOLEAN, uploaded_by_id FK, created_at
UNIQUE(plik_id, version_number)
```

### `powiazania_plikow` (polimorficzne)
```
id, plik_id FK, fileable_type VARCHAR(64), fileable_id BIGINT,
rola VARCHAR(32),  -- artwork | proof | production | evidence | invoice_pdf
created_at
INDEX (fileable_type, fileable_id)
```

### `miniatury`
```
id, plik_id FK, rozmiar VARCHAR(4) -- sm|md|lg, local_path, width_px INT, height_px INT
UNIQUE(plik_id, rozmiar)
```

**Uwaga:** gdy używamy `spatie/laravel-medialibrary`, tabela media jest generowana przez paczkę — powyższa schema jest konceptualna. Konwersje (`sm`/`md`/`lg`) konfigurowane w `HasMedia::registerMediaConversions()`.

Pełna architektura: [08_DAMS_LOKALNE_PRZECHOWYWANIE_PLIKOW.md](./08_DAMS_LOKALNE_PRZECHOWYWANIE_PLIKOW.md).

---

## 6. Approvals / Designs

### `zadania_akceptacji`
```
id, zamowienie_id FK, wersja_pliku_id FK,
status VARCHAR(16) -- pending|approved|revision_requested|rejected|expired
link_token UUID UNIQUE,
round_number INT DEFAULT 1,
sent_at, responded_at, expires_at  -- default: sent_at + 7 dni
notes TEXT
```

### `komentarze_akceptacji`
```
id, zadanie_id FK, text TEXT, position_x DECIMAL(6,2), position_y DECIMAL(6,2),
page_number INT, author_type VARCHAR(8) -- client|staff
author_id BIGINT NULL  -- NULL dla komentarzy klienta (anonimowy przez link)
created_at
```

### `projekty_graficzne` (moduł 18)
```
id, user_id FK, klient_id FK NULL, product_id FK, name, template_id FK NULL,
scene_state JSONB, -- Konva scene
status VARCHAR(24) -- draft|submitted|approved|in_production|archived
last_edited_at
```

### `wersje_projektow`
```
id, projekt_id FK, version_number INT, scene_state JSONB, exported_pdf_file_id FK NULL
```

### `komentarze_projektow`
```
id, projekt_id FK, user_id FK, text, resolved BOOLEAN, created_at
```

---

## 7. Production

### `zadania_produkcyjne`
```
id, zamowienie_id FK, status, priority SMALLINT, planned_date DATE, notes
```

### `etapy_produkcji`
```
id, zadanie_id FK, typ VARCHAR(32) -- prepress|print|postpress_cut|postpress_laminate|packaging
sequence SMALLINT, status VARCHAR(16), maszyna_id FK NULL, operator_id FK NULL,
time_start, time_stop, norm_time_minutes INT, actual_time_minutes INT
```

### `maszyny`
```
id, name, typ VARCHAR(32), status VARCHAR(16) -- available|busy|service|offline
cost_per_hour DECIMAL(10,2), description
```

### `operatorzy`
```
id, user_id FK UNIQUE, specjalizacje TEXT[], is_available BOOLEAN
```

---

## 8. Inventory (MVP: uproszczone)

### `pozycje_magazynu`
```
id, name, mime_type VARCHAR(32), unit VARCHAR(16) -- szt|ark|m|kg
current_qty DECIMAL(12,2), reorder_point DECIMAL(12,2), material_id FK NULL
```

### `transakcje_magazynowe`
```
id, pozycja_id FK, qty DECIMAL(12,2), typ VARCHAR(8) -- in|out
zadanie_id FK NULL, user_id FK, reason, created_at
```

---

## 9. Finance

### `dokumenty_finansowe`
```
id, zamowienie_id FK, typ VARCHAR(24) -- proforma|invoice_advance|invoice_vat|paragon|correction
number_in_subiekt VARCHAR(64),
number_ksef VARCHAR(64),
amount_net DECIMAL(12,2), amount_brutto DECIMAL(12,2), vat_percent DECIMAL(5,2),
mpp_flag BOOLEAN DEFAULT FALSE,  -- true gdy brutto > 15000 i B2B
status VARCHAR(16) -- draft|issued|paid|overdue|cancelled
payment_link VARCHAR(512),
due_date DATE, issued_at, paid_at,
pdf_file_id BIGINT FK pliki NULL
```

### `platnosci`
```
id, dokument_id FK, amount DECIMAL(12,2), method VARCHAR(16) -- transfer|blik|card|cash|delayed
date DATE, transaction_id_provider VARCHAR(128) UNIQUE,
source VARCHAR(16) -- przelewy24|subiekt_import|manual
```

### `metody_platnosci`
Słownik. `id, name, is_active, sort_order`.

---

## 10. Logistics

### `przesylki`
```
id, zamowienie_id FK, method VARCHAR(32),
status VARCHAR(16), tracking_number, tracking_url, label_file_id FK pliki NULL,
cost_net DECIMAL(10,2), shipped_at, delivered_at
```

### `adresy_wysylki`
```
id, przesylka_id FK, imie_nazwisko, company, street, house_number, apt_number,
postal_code, city, country CHAR(2), phone, email, locker_id  -- InPost
```

---

## 11. Complaints

### `reklamacje`
```
id, zamowienie_id FK, klient_id FK, number VARCHAR(20) UNIQUE -- RKL-YYYY-XXXXX
reason VARCHAR(32), description TEXT, status VARCHAR(16),
decision VARCHAR(16) -- reprint|partial_refund|full_refund|rejection
investigator_id FK, submitted_at, responded_at, closed_at,
parent_reprint_order_id FK zamowienia NULL
```

### `dowody_reklamacji`
```
id, reklamacja_id FK, plik_id FK, typ VARCHAR(16), description
```

---

## 12. CommHub

### `watki_komunikacji`
```
id, zamowienie_id FK NULL, klient_id FK NULL, subject VARCHAR(255),
status VARCHAR(16) -- open|closed|archived
last_message_at
```

### `wiadomosci`
```
id, watek_id FK, direction VARCHAR(8) -- inbound|outbound
channel VARCHAR(8) -- email|sms|system|note
text_html TEXT, text_plain TEXT,
sender_id FK uzytkownicy NULL, sender_email, recipient_email, recipient_phone,
external_message_id VARCHAR(255) UNIQUE NULL,  -- Message-ID z IMAP (dedup)
delivery_status VARCHAR(16), is_read BOOLEAN, created_at
```

### `zalaczniki_wiadomosci`
```
id, wiadomosc_id FK, plik_id FK
```

### `szablony_wiadomosci`
```
id, name, channel, subject, text_html, text_plain, variables TEXT[], is_active
```

### `powiadomienia`
```
id, user_id FK, type, title, body, read_at, data JSONB, created_at
```

---

## 13. Shop (Faza 3)

### `koszyki`
```
id, user_id FK NULL, session_id VARCHAR(64), expires_at, created_at
```

### `pozycje_koszykow`
```
id, koszyk_id FK, product_id FK, parameters JSONB, quantity, unit_price DECIMAL(10,2)
```

### `kody_promocyjne`
```
id, code VARCHAR(32) UNIQUE, typ VARCHAR(16) -- fixed|percentage|free_shipping
value DECIMAL(10,2), valid_from, valid_to, max_uses INT, current_uses INT DEFAULT 0,
is_active
```

### `produkty_historia_cen` (Omnibus UOKiK)
```
id, product_id FK, config_hash VARCHAR(64), price_net DECIMAL(10,2), valid_on DATE
INDEX (product_id, valid_on)
```

---

## 14. Indeksy JSONB

```sql
CREATE INDEX idx_spec_dpi ON pozycje_zamowien USING GIN ((specyfikacja -> 'dpi'));
CREATE INDEX idx_spec_full ON pozycje_zamowien USING GIN (specyfikacja jsonb_path_ops);
CREATE INDEX idx_kalk_breakdown ON kalkulacje_ceny USING GIN (breakdown jsonb_path_ops);
CREATE INDEX idx_kalk_config ON kalkulacje_ceny USING GIN (config_snapshot);
```

---

## 15. Materialized views (raporty, Faza 3)

```sql
CREATE MATERIALIZED VIEW mv_dashboard_daily AS
SELECT DATE(z.created_at) AS dzien,
       COUNT(*) AS liczba_zamowien,
       SUM(kc.final_price_net) AS obrot_net,
       SUM(kc.margin_amount) AS marza,
       AVG(kc.final_price_net) AS srednia
FROM zamowienia z
LEFT JOIN pozycje_zamowien pz ON pz.zamowienie_id = z.id
LEFT JOIN kalkulacje_ceny kc ON kc.pozycja_id = pz.id
WHERE z.deleted_at IS NULL
GROUP BY DATE(z.created_at)
WITH NO DATA;
CREATE UNIQUE INDEX ON mv_dashboard_daily (dzien);

CREATE MATERIALIZED VIEW mv_orders_by_status AS ...;
CREATE MATERIALIZED VIEW mv_machine_utilization AS ...;
CREATE MATERIALIZED VIEW mv_operator_efficiency AS ...;
CREATE MATERIALIZED VIEW mv_client_loyalty AS ...;
CREATE MATERIALIZED VIEW mv_complaint_rate AS ...;
CREATE MATERIALIZED VIEW mv_revenue_monthly AS ...;
```

**Refresh:** `app/Console/Commands/RefreshMaterializedViewsCommand` wywoływany przez `app/Console/Kernel.php` co 15 min:
```php
$schedule->command('reports:refresh-views')->everyFifteenMinutes();
```
Użyj `REFRESH MATERIALIZED VIEW CONCURRENTLY` aby nie blokować odczytu.

---

## 16. Generated columns (przykład)

```sql
ALTER TABLE page_config ADD COLUMN total_pages INT
  GENERATED ALWAYS AS (cover_pages + interior_pages) STORED;

ALTER TABLE dokumenty_finansowe ADD COLUMN days_overdue INT
  GENERATED ALWAYS AS (
    CASE
      WHEN status='issued' AND due_date < CURRENT_DATE
      THEN CURRENT_DATE - due_date
      ELSE NULL
    END
  ) STORED;
```

---

## 17. Konwencje migracyjne

Laravel 11:
```bash
php artisan make:migration create_zamowienia_table
```

```php
Schema::create('zamowienia', function (Blueprint $t) {
    $t->id();
    $t->string('number', 20)->unique();
    $t->foreignId('klient_id')->constrained('klienci')->restrictOnDelete();
    $t->foreignId('menedzer_id')->nullable()->constrained('uzytkownicy')->nullOnDelete();
    $t->string('status', 32)->index();
    $t->smallInteger('priority')->default(5);
    $t->string('source', 16);
    $t->date('deadline')->nullable();
    $t->date('cancellable_until')->nullable();
    $t->timestampTz('cancelled_at')->nullable();
    $t->text('notatki')->nullable();
    $t->timestampsTz();
    $t->softDeletesTz();
    $t->index(['status','deadline']);
});
```

**Modyfikacja kolumn** (Laravel 11 wymóg): zawsze repetuj wszystkie wcześniejsze atrybuty:
```php
$t->string('status', 32)->default('NOWE')->index()->change();  // NIE gubić indexu i default!
```
