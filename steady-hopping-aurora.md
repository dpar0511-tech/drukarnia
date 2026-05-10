# steady-hopping-aurora.md — ARCHIWUM

> ⚠️ **PLIK HISTORYCZNY.** Ten dokument został zachowany wyłącznie jako odniesienie archiwalne.
> **Aktualnym źródłem prawdy architektonicznej jest folder [`nowa dokumentacja/`](./nowa%20dokumentacja/00_INDEX.md).**
>
> Pełna oryginalna treść (4433 linie) została przeniesiona do [`steady-hopping-aurora.ARCHIVE.md`](./steady-hopping-aurora.ARCHIVE.md).
> W razie konfliktu między tym plikiem a `nowa dokumentacja/` — **wygrywa `nowa dokumentacja/`**.

---

## Aktualna dokumentacja (źródło prawdy, stan 2026-04-23)

| Nr | Plik | Zakres |
|----|------|--------|
| 00 | [00_INDEX.md](./nowa%20dokumentacja/00_INDEX.md) | Mapa, reguła pierwszeństwa, konwencje |
| 01 | [01_WIZJA_I_ZALOZENIA.md](./nowa%20dokumentacja/01_WIZJA_I_ZALOZENIA.md) | Domena, NFR, role, fazowanie, ograniczenia PL |
| 02 | [02_STACK_TECHNOLOGICZNY.md](./nowa%20dokumentacja/02_STACK_TECHNOLOGICZNY.md) | Wersje pakietów, polityka UI (shadcn-vue only) |
| 03 | [03_ARCHITEKTURA_SYSTEMU.md](./nowa%20dokumentacja/03_ARCHITEKTURA_SYSTEMU.md) | Modularny monolit, event-bus, kolejki, WS, cache |
| 04 | [04_MODEL_DOMENOWY_I_BAZA_DANYCH.md](./nowa%20dokumentacja/04_MODEL_DOMENOWY_I_BAZA_DANYCH.md) | Tabele 18 modułów, JSONB, materialized views |
| 05 | [05_MODULY_BIZNESOWE.md](./nowa%20dokumentacja/05_MODULY_BIZNESOWE.md) | 18 modułów domenowych (bounded contexts) |
| 06 | [06_WORKFLOW_I_EVENTY.md](./nowa%20dokumentacja/06_WORKFLOW_I_EVENTY.md) | FSM 19 stanów + katalog eventów + DLQ + idempotencja |
| 07 | [07_SILNIK_CENOWY_INTEGRACJA.md](./nowa%20dokumentacja/07_SILNIK_CENOWY_INTEGRACJA.md) | 🔥 **PricingPipeline 12-krokowy** (3 silniki, 49 testów 1:1) |
| 08 | [08_DAMS_LOKALNE_PRZECHOWYWANIE_PLIKOW.md](./nowa%20dokumentacja/08_DAMS_LOKALNE_PRZECHOWYWANIE_PLIKOW.md) | 🔥 **DAMS wyłącznie na lokalnym dysku** (bez S3/MinIO) |
| 09 | [09_SZYBKI_START_MVP_SCAFFOLDING.md](./nowa%20dokumentacja/09_SZYBKI_START_MVP_SCAFFOLDING.md) | 🔥 **Breeze + Filament + shadcn-vue** (setup w 3 dni) |
| 10 | [10_INTEGRACJE_ZEWNETRZNE.md](./nowa%20dokumentacja/10_INTEGRACJE_ZEWNETRZNE.md) | Subiekt, GUS, P24, InPost/DPD/DHL/GLS, SMSAPI, IMAP, KSeF |
| 11 | [11_BEZPIECZENSTWO_I_RBAC.md](./nowa%20dokumentacja/11_BEZPIECZENSTWO_I_RBAC.md) | Role × akcje, Fortify + 2FA, audit log, OWASP, RODO, KSeF, MPP |
| 12 | [12_STRATEGIA_TESTOWA.md](./nowa%20dokumentacja/12_STRATEGIA_TESTOWA.md) | Pest/PHPUnit 11, 49 testów pricing 1:1, coverage ≥80% |
| 13 | [13_PLAN_WDROZENIA_I_DEVOPS.md](./nowa%20dokumentacja/13_PLAN_WDROZENIA_I_DEVOPS.md) | 1× VPS Ubuntu, Octane/FrankenPHP, supervisor, backup |
| 14 | [14_GLOSARIUSZ_I_REFERENCJE.md](./nowa%20dokumentacja/14_GLOSARIUSZ_I_REFERENCJE.md) | Słownik 30+ terminów, indeks krzyżowy |

Dodatkowo zaktualizowane:
- [`roadmap.md`](./roadmap.md) — pokrokowy plan implementacji v2 (zgodny z `nowa dokumentacja/`).
- [`MASTER_PLAN.md`](./MASTER_PLAN.md) — zaktualizowany master plan (stary, historyczny, z linkami do nowej dokumentacji).
- [`ERP_WORKFLOW.md`](./ERP_WORKFLOW.md) — zaktualizowany workflow A→Z (zgodny z FSM 19 stanów).

---

## Czym ten dokument (archiwalny) różni się od aktualnej dokumentacji

| Obszar | `steady-hopping-aurora.ARCHIVE.md` (stare) | `nowa dokumentacja/` (aktualne) |
|--------|--------------------------------------------|----------------------------------|
| **Storage plików** | MinIO / S3 (AWS) jako główny backend | ⚠️ **Wyłącznie lokalny dysk** (`storage/app/private/`) + `spatie/laravel-medialibrary` + `tus-php`. Zakaz MinIO/S3. |
| **Silnik cenowy** | Ad-hoc: `RegulaCenowa`, `IndywidualyCennik`, `RabatDefinicja`, `KosztWlasny` | ⚠️ **12-krokowy `PricingPipeline`** z `_nowy_SILNIK_CENOWY/` — 3 silniki (Sheet, LinearMeter, Multipage), `SettingResolver`, `CostPriceTrait`, 49 testów 1:1. Stare tabele usunięte (zostaje `kalkulacje_ceny` jako snapshot). |
| **Liczba modułów** | 15–16 (Preflight, FinanceOrchestrator, IntegrationLayer itd.) | **18 bounded contexts** — dodane: `Quotes` (5), `Shop` (17), `Design Editor` (18); rozdzielone `Products` (3) od `Pricing` (4). Preflight odłożony do Fazy 4. |
| **FSM zamówienia** | 16 statusów | **19 statusów** (+`PROJEKTOWANIE`, `OCZEKUJE_NA_AKCEPTACJE`, `REWIZJA`, `ODRZUCONE`, `PRODUCTION_HOLD`, `ODBIOR_OSOBISTY`, `DOSTARCZONE`) |
| **Scaffolding** | Ręczny `composer create-project` + manualny setup Inertia/Vite | **Breeze (`breeze:install vue --inertia --ssr --typescript --pest --dark`) + Filament v3 w `/admin` + `npx shadcn-vue@latest init`** — cały setup w **3 dni** |
| **Stack UI** | Ręczne komponenty + Tailwind surowy | **shadcn-vue only** + `lucide-vue-next` ikony + kolory **semantyczne** (`bg-primary`, nie `bg-blue-500`); `flex gap-*` (nie `space-x/y-*`); `size-*` (nie `w-* h-*`) |
| **Runtime PHP** | php-fpm | php-fpm lub **Octane + FrankenPHP** (3× RPS, hot-reload) |
| **Integracje HTTP** | Custom `BaseAdapter` | **`saloonphp/saloon` v3** — Connectory + Requesty + middleware |
| **Pieniądze** | `float` / casting Eloquent | **wyłącznie `brick/math` `BigDecimal`** (scale 10) lub `bcmath`; zakaz `float` |
| **Fazowanie** | 3 fazy (MVP / Portal / Analytics) | **4 fazy**: Faza 0 (Scaffolding 3 dni), Faza 1 (MVP 10t), Faza 2 (Portal 8–10t), Faza 3 (Shop + Design Editor 10–12t), Faza 4 (Preflight + multi-tenant + JDF/JMF + AI full — otwarte) |
| **Backend search** | Laravel Scout + Meilisearch (tak samo) | Bez zmian |
| **WebSocket** | Laravel Reverb (tak samo) | Bez zmian |
| **Deploy** | Docker Compose prod | **1× VPS Ubuntu 24.04 LTS + Deployer v7 + supervisor + Nginx `X-Accel-Redirect`** (backup VPS opcjonalny) |

---

## Co zrobić, jeśli szukasz konkretnej informacji

- Decyzje architektoniczne → [`01_WIZJA_I_ZALOZENIA.md`](./nowa%20dokumentacja/01_WIZJA_I_ZALOZENIA.md) sekcja 8.
- Wersje pakietów → [`02_STACK_TECHNOLOGICZNY.md`](./nowa%20dokumentacja/02_STACK_TECHNOLOGICZNY.md) sekcje 5, 6.
- Tabele bazy → [`04_MODEL_DOMENOWY_I_BAZA_DANYCH.md`](./nowa%20dokumentacja/04_MODEL_DOMENOWY_I_BAZA_DANYCH.md).
- Workflow od zapytania do dostawy → [`06_WORKFLOW_I_EVENTY.md`](./nowa%20dokumentacja/06_WORKFLOW_I_EVENTY.md) + [`ERP_WORKFLOW.md`](./ERP_WORKFLOW.md).
- Setup deweloperski (3 dni → produkcyjny MVP) → [`09_SZYBKI_START_MVP_SCAFFOLDING.md`](./nowa%20dokumentacja/09_SZYBKI_START_MVP_SCAFFOLDING.md).
- Plan tygodniowy implementacji → [`roadmap.md`](./roadmap.md).
- Kod silnika cenowego → [`07_SILNIK_CENOWY_INTEGRACJA.md`](./nowa%20dokumentacja/07_SILNIK_CENOWY_INTEGRACJA.md) + źródło `_nowy_SILNIK_CENOWY/01..08.md`.

---

**Data archiwizacji:** 2026-04-24.
**Autor zmiany:** Chief Software Architect (AI-assisted) w ramach synchronizacji z nową dokumentacją.
