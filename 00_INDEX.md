# NOWA DOKUMENTACJA ARCHITEKTONICZNA — `_ERP_nowy`

> **Status:** aktywna, nadrzędna wobec plików w `_ERP_nowy/*.md` i `_nowy_SILNIK_CENOWY/*.md`.
> **Cel:** jedno spójne źródło prawdy do implementacji drukarniowego ERP od zera.
> **Stack docelowy:** Laravel 11 · Vue 3 · Inertia.js v3 · PostgreSQL 16 · Redis 7 · Horizon 5 · Reverb 1 · shadcn-vue · **lokalne sklepowanie plików**.

---

## 1. Mapa plików

| Nr | Plik | Zawartość jednym zdaniem |
|----|------|--------------------------|
| 00 | `00_INDEX.md` | Ten plik — mapa, reguła pierwszeństwa, konwencje. |
| 01 | `01_WIZJA_I_ZALOZENIA.md` | Domena biznesowa, NFR, role, fazowanie, ograniczenia prawne PL. |
| 02 | `02_STACK_TECHNOLOGICZNY.md` | Wszystkie wersje pakietów, usunięte zależności, reguły UI. |
| 03 | `03_ARCHITEKTURA_SYSTEMU.md` | Modularny monolit, warstwy, event-bus, kolejki, WebSocket, cache. |
| 04 | `04_MODEL_DOMENOWY_I_BAZA_DANYCH.md` | Tabele (wszystkie 18 modułów) z kolumnami, FK, JSONB, materialized views. |
| 05 | `05_MODULY_BIZNESOWE.md` | 18 modułów — odpowiedzialność, serwisy, eventy, UX skrót. |
| 06 | `06_WORKFLOW_I_EVENTY.md` | FSM 16 stanów zamówienia, katalog eventów, orkiestracja Finance, DLQ, idempotencja. |
| 07 | `07_SILNIK_CENOWY_INTEGRACJA.md` | **🔥 Krytyczny rozdział — 100% pełnej specyfikacji silnika cenowego przeniesionej do Laravel.** |
| 08 | `08_DAMS_LOKALNE_PRZECHOWYWANIE_PLIKOW.md` | **🔥 Nowa architektura DAMS** wyłącznie na lokalnym dysku (zastępuje MinIO/S3). |
| 09 | `09_SZYBKI_START_MVP_SCAFFOLDING.md` | **🔥 Radykalne przyspieszenie MVP** — Breeze + Filament + shadcn-vue, Docker, CI. |
| 10 | `10_INTEGRACJE_ZEWNETRZNE.md` | Subiekt nexo, GUS, Przelewy24, InPost/DPD/DHL/GLS, SMSAPI, IMAP, KSeF. |
| 11 | `11_BEZPIECZENSTWO_I_RBAC.md` | Role × akcje, Fortify + 2FA, audit log, OWASP, RODO, KSeF, MPP. |
| 12 | `12_STRATEGIA_TESTOWA.md` | Pest/PHPUnit 11, 49 testów pricing 1:1, coverage ≥80% na `app/Services/Pricing`. |
| 13 | `13_PLAN_WDROZENIA_I_DEVOPS.md` | 1× VPS Ubuntu, Octane/FrankenPHP, supervisor, backup `pg_dump` + `rsync`. |
| 14 | `14_GLOSARIUSZ_I_REFERENCJE.md` | Słownik 30+ terminów, indeks krzyżowy, lista źródeł. |

---

## 2. Reguła pierwszeństwa (co nadpisuje co)

Nowa dokumentacja nadpisuje starsze pliki wszędzie, gdzie występuje konflikt. Dwa konflikty są kluczowe:

### 2.1. Storage plików
**Stare:** `MASTER_PLAN.md` i `steady-hopping-aurora.md` opisują MinIO/S3 jako backend.
**Nowe (obowiązujące):** **Wyłącznie lokalne sklepowanie na dysku serwera ERP.** Żadnego MinIO, S3, hmury. Patrz [08_DAMS_LOKALNE_PRZECHOWYWANIE_PLIKOW.md](./08_DAMS_LOKALNE_PRZECHOWYWANIE_PLIKOW.md).

### 2.2. Silnik cenowy
**Stare:** `MASTER_PLAN.md` → `MODULE 4 — PRICING & LOYALTY ENGINE` z tabelami `RegulaCenowa`, `IndywidualyCennik`, `RabatDefinicja`, `KalkulacjaCeny`, `KosztWlasny`.
**Nowe (obowiązujące):** **Silnik cenowy z `_nowy_SILNIK_CENOWY/`** — 12-krokowy `PricingPipeline` + 3 silniki (`SheetEngine`, `LinearMeterEngine`, `MultipageEngine`) + kaskadowy `SettingResolver` + `CostPriceTrait` (8 pól). Patrz [07_SILNIK_CENOWY_INTEGRACJA.md](./07_SILNIK_CENOWY_INTEGRACJA.md).

Tabele `RegulaCenowa`, `IndywidualyCennik`, `RabatDefinicja` z `MASTER_PLAN.md` **są usunięte**. Pozostaje jedynie `kalkulacje_ceny` jako snapshot wyniku `PricingPipeline::calculate()`.

---

## 3. Konwencje

| Zasięg | Konwencja |
|--------|-----------|
| Nazwy tabel | `snake_case` po polsku (np. `zamowienia`, `pozycje_zamowien`, `kalkulacje_ceny`). Wyjątek: warstwa pricingu zachowuje nazwy angielskie z pliku 04 `_nowy_SILNIK_CENOWY` (`pricing_rules`, `exclusion_rule`, `material_size`, `zadruk_option`…). |
| Nazwy kolumn | `snake_case` po polsku dla domen biznesowych; **ale** w warstwie pricingu zachowujemy oryginalne angielskie (`cost_per_sheet`, `margin_base_pct`, `pattern_multiplier`), bo są one 1:1 z kodem silnika. |
| FQCN modeli | `App\Models\{Domena}\{NazwaCamelCase}` — np. `App\Models\Orders\Order`. Pricingu: `App\Models\{Core,Parameters,Products,Exclusions}`. |
| Serwisy | `App\Services\{Domena}` — np. `App\Services\Pricing\PricingPipeline`. |
| Eventy | `App\Events\{NazwaZdarzenia}` — np. `OrderCreated`, `PriceCalculated`. |
| Listenery | `App\Listeners\{NazwaAkcji}` — np. `CreateProformaAfterPricing`. |
| Jobs | `App\Jobs\{NazwaJoba}` — np. `GenerateThumbnailsJob`. |
| Value Objects | `readonly class` PHP 8.2+, w `App\Services\{Domena}\ValueObjects\*`. |
| Traits | `App\Traits\{NazwaTrait}` — `CostPriceTrait`, `SingletonTrait`, `Auditable`. |
| Enums | `App\Enums\{NazwaEnum}` — PHP 8.1+ native enums. |
| Dependency injection | Kontrakty (interfejsy) wiążemy w `AppServiceProvider::register()` (Laravel standard). |
| Migracje | Data w nazwie pliku z sufiksem opisowym po angielsku (`2026_04_23_120000_create_orders_table.php`). |
| Arytmetyka pieniędzy | **Zawsze** `brick/math` (`BigDecimal`) lub `bcmath` z `bcscale(10)`. **Nigdy** `float`. |

---

## 4. Jak czytać tę dokumentację

- **Nowy inżynier w projekcie:** 01 → 02 → 03 → 09 (scaffolding) → 07 (pricing) → 04 → 05. Po tych plikach jesteś gotowy do kodowania MVP.
- **Architekt przed decyzją:** 03 + 07 + 08 + 11.
- **DevOps:** 02 + 13.
- **QA/Tester:** 06 (FSM i eventy) + 12 (strategia testowa) + 07 sekcja „Testy".
- **Product owner / menedżer:** 01 + 05 (bez sekcji technicznych).

---

## 5. Status dokumentu

- Data: 2026-04-23.
- Autor: Chief Software Architect (AI-assisted).
- Źródła: `_ERP_nowy/{roadmap,MASTER_PLAN,ERP_WORKFLOW,steady-hopping-aurora}.md` + `_nowy_SILNIK_CENOWY/01..08.md`.
- Przy zmianach źródeł: aktualizować **tu**, nie w starych plikach (które są zachowane jako historia).
