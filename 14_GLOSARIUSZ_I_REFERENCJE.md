# 14 — GLOSARIUSZ I REFERENCJE

## 1. Glosariusz pojęć

### Drukarskie

| Termin | Definicja | Dokument |
|--------|-----------|----------|
| **Arkusz (sheet)** | Fizyczny nośnik druku o stałych wymiarach (np. SRA3 = 450×320 mm). Jednostka konsumpcji w `SheetEngine` i `MultipageEngine`. | 07 |
| **Bleed / Spad** | Dodatkowy margines na produkcie (zwykle 2 mm z każdej strony) zostawiany na cięcie — gwarantuje brak białych krawędzi po przycięciu. | 07 |
| **Cover / Okładka** | Zewnętrzna część produktu wielostronnego (zwykle grubszy papier). Rola: `PartRole::COVER`. | 07 |
| **CMYK** | Przestrzeń barw druku (Cyan, Magenta, Yellow, Black). PDF do druku musi być w CMYK + profil ICC (najczęściej ISO Coated v2 300%). | 08 |
| **DPI** | Dots per inch. 300 DPI dla druku offsetowego, 72 DPI dla web. Sprawdzane w preflight. | 01 |
| **Format (produktu)** | Rozmiar gotowego produktu po cięciu (A4, DL, wizytówka 90×50). Model `Format`. | 04, 07 |
| **Impozycja** | Ile sztuk produktu mieści się na jednym arkuszu z uwzględnieniem spadu i marginesów. Algorytm testuje 2 orientacje (normalna i obrócona 90°) — bierze lepszą. | 07 |
| **Interior / Wnętrze** | Wewnętrzna część produktu wielostronnego (zwykle cieńszy papier). Rola: `PartRole::INTERIOR`. | 07 |
| **Makulatura** | Odpad arkuszy / metrów potrzebny na przygotowanie do druku (rozgrzanie maszyny, próby, przyrząd). Konfigurowany per produkt. | 07 |
| **MaterialKind** | Rodzaj materiału: `sheet` (arkuszowy, papier) lub `roll` (rolkowy, folia/banner). | 07 |
| **MediaFormat** | Rozmiar fizycznego nośnika — arkusz (SRA3, A4) lub rolka (610 mm, 914 mm). Dla rolek: `height_mm = 0` (nieskończona). | 04, 07 |
| **MultipageType** | Typ wielostronności: `skladany` (szyty/klejony w jednym bloku) vs `rozdzielony` (spiralowany). **Tylko etykieta UI** — silnik liczy identycznie. | 07 |
| **Nakład** | Ilość sztuk do wydrukowania. Domyślnie 100 (kalkulowane) lub 1 (proste). | 07 |
| **Odpad (waste)** | Dodatkowe arkusze / metry na makulaturę. Dwa zestawy: arkuszowy (`waste_pct + waste_fixed`) i metrowy (`waste_meter_pct + waste_meter_fixed`). | 07 |
| **PageConfig** | Konfiguracja stron dla wielostronności: etykieta (np. "4+8"), `cover_pages + interior_pages`. | 04, 07 |
| **PartRole** | Rola w wielostronności: `common` (wspólne), `cover` (okładka), `interior` (wnętrze). Determinuje jakie `units_consumed` mnożą koszty parametru. | 07 |
| **Pattern / Wzór** | Wariant graficzny produktu (np. 3 różne projekty wizytówek w jednym nakładzie). Mnożnik: `1 + (count - 1) × multiplier`. Default: 0.70. | 07 |
| **Preflight** | Automatyczna walidacja pliku PDF przed drukiem (rozmiar, bleed, CMYK, fonty, overprint). W MVP manualna; automatyzacja Faza 4. | 01 |
| **Produkt kalkulowany** | `ProductType::CALCULATED` — korzysta z pełnego 12-krokowego pipeline'u + jednego z 3 silników. | 07 |
| **Produkt prosty** | `ProductType::SIMPLE` — prosty wzór: baza × qty + opcje + rabat wykładniczy z capem. | 07 |
| **Projekt** | Usługa graficzna (tworzenie projektu przez drukarnię). Cena = `base + per_page × pages`. Flag `has_project`. | 07 |
| **Proof** | Wydruk próbny / proof PDF wysyłany klientowi do akceptacji przed drukiem produkcyjnym. | 05 (Approvals) |
| **Rolka (roll)** | Nośnik druku w formie ciągłej rolki o określonej szerokości (np. 610 mm, 914 mm). | 04, 07 |
| **Unit type** | Typ jednostki konsumpcji: `sheet` (arkusz) lub `meter` (metr bieżący). Determinowany przez silnik. | 07 |
| **Zadruk** | Konfiguracja druku: kolorystyka i stronność (4/0 jednostronny, 4/4 dwustronny, sublimacja). Model `ZadrukOption` z `CostPriceTrait` + `project_page_count`. | 04, 07 |

### Finansowe / prawne

| Termin | Definicja |
|--------|-----------|
| **COGS (Cost of Goods Sold)** | Koszt sprzedanych towarów — faktyczny koszt własny produkcji. W ERP: `total_cost` z pipeline + RKW overlay. |
| **Faktura Zaliczkowa** | Faktura wystawiana po otrzymaniu zaliczki (np. 50% wpłaty). Część polskiego workflow księgowego. |
| **Faktura Korygująca** | Korekta wcześniej wystawionej faktury (zwrot, rabat, zmiana). Wymagane przy reklamacji ze zwrotem pieniędzy. |
| **KSeF** | Krajowy System e-Faktur (obowiązkowy dla B2B w Polsce od 2026). Faktury VAT wysyłane do centralnego rejestru. MVP: via Subiekt. |
| **MPP (Mechanizm Podzielonej Płatności)** | Split payment — VAT wpłacany na osobne konto (podatkowe). Obowiązkowy dla faktur > 15 000 PLN brutto B2B z załącznika 15 VAT. |
| **Paragon** | Dokument sprzedaży dla B2C (zamiast faktury VAT). Rejestrowany w kasie fiskalnej / online. |
| **Proforma** | Dokument wzorcowy (wycena) — podstawa płatności, nie podatkowy. Po opłaceniu zamienia się na Fakturę Zaliczkową / VAT. |
| **Rękojmia** | Ustawowe prawo kupującego do reklamacji wady towaru (2 lata PL). SLA: 14 dni na rozstrzygnięcie. |
| **RKW** | Rzeczywisty Koszt Wytworzenia — koszt faktyczny z uwzględnieniem czasu maszyn i operatorów. Liczony shadow equivalent (nie wpływa na cenę). |
| **RODO / GDPR** | Rozporządzenie o ochronie danych osobowych. Wymóg: audit log, prawo do bycia zapomnianym, portability, breach notification 72 h. |
| **Omnibus** | Dyrektywa UOKiK — obowiązek pokazywania najniższej ceny z 30 dni przy każdej promocji w sklepie. |

### Techniczne (Laravel / ERP)

| Termin | Definicja |
|--------|-----------|
| **BigDecimal** | `Brick\Math\BigDecimal` — bezpieczna arytmetyka dla pieniędzy, zamiast `float`. |
| **Bounded context** | Obszar domenowy z własnym modelem (DDD). 18 modułów ERP = 18 bounded contextów. |
| **Cap / Minimum** | Dolna granica ceny za sztukę w rabacie wykładniczym (`SimpleProductDiscount.minimum_unit_price`). |
| **CostPriceTrait / CostPriceMixin** | 8 pól kosztowo-cenowych (4 cost pre-margin + 4 price post-margin). Dziedziczą: `ZadrukOption`, `ParameterOption`, `Product`. |
| **DLQ (Dead Letter Queue)** | Kolejka dla jobów, które nie powiodły się N razy. Tutaj: wbudowana `failed_jobs` Laravela. |
| **EngineResult** | Value Object zwracany przez silnik obliczeniowy: unit_type, units_consumed, imposition, material_cost, print_cost, details. |
| **FSM (Finite State Machine)** | Skończony automat stanów. Dla zamówień: 16 stanów przez `spatie/laravel-model-states`. |
| **HasMany polymorphic** | Relacja Laravela łącząca wiele typów (np. `Plik` powiązany z `Zamowienie`, `Reklamacja`, `Wiadomosc`). |
| **IDOR** | Insecure Direct Object Reference — podatność, gdy użytkownik może zmienić `{id}` w URL i zobaczyć cudze dane. Ochrona: policies. |
| **Inertia v3** | Most Laravel ↔ Vue bez REST API. Kontroler → `Inertia::render(Component, props)`. |
| **Marża dwupoziomowa** | Baza (%) do progu (PLN) + inna (%) ponad progiem. Formuła: `(min(cost, threshold) × base%) + (max(0, cost-threshold) × above%)`. |
| **Mediawiki / Media Library** | `spatie/laravel-medialibrary` — paczka do wersjonowania plików + automatycznych konwersji (miniatury). |
| **Override / Nadpisanie** | `ProductOverride` — pola nullable; NULL = dziedzicz globalne. |
| **Pipeline (PricingPipeline)** | 12-krokowy algorytm kalkulacji ceny produktu kalkulowanego. |
| **PricingResult** | Value Object z pełnym wynikiem pipeline'u. Pole `finalPriceNet` = cena końcowa netto. |
| **Reverb** | Laravel-native WebSocket server (zamiennik Pusher). |
| **Resolver (SettingResolver)** | Funkcja kaskadowego dziedziczenia: `ProductOverride → GlobalSetting → Singleton`. 4 mapy fallbacków. |
| **Saloon** | Framework PHP dla HTTP API clients (`saloonphp/saloon`). Używany w adapterach integracji. |
| **Scout** | `laravel/scout` — search engine wrapper (Meilisearch w naszym case). |
| **Singleton (pk=1)** | Model z wymuszonym `id = 1`, zablokowanym `delete()`, z metodą `current()`. Dla: GlobalSetting, PatternSetting, ProjectSetting, CustomFormatSetting. |
| **shadcn-vue** | Biblioteka UI komponentów opartych na Reka UI + Tailwind. Jedyne źródło UI w tym projekcie. |
| **Signed URL** | URL z tokenem + timestamp + HMAC podpisem, wygasający po czasie. Laravel: `URL::temporarySignedRoute()`. |
| **State pattern (spatie/laravel-model-states)** | Implementacja FSM jako osobne klasy na stan + transition class. |
| **TUS / tus.io** | Protokół resumable upload (chunk-based). Pozwala wznowić upload po utracie sieci. |
| **UserSelection** | Value Object z bieżącym wyborem użytkownika w konfiguratorze — dla `ExclusionResolver::evaluate`. |
| **Value Object (VO)** | Readonly class PHP 8.2+; immutable, bez ID, porównywany po wartości. DTO pricingu. |
| **X-Accel-Redirect** | Nagłówek nginx do serwowania chronionych plików — Laravel weryfikuje uprawnienia, zwraca pusty response, nginx serwuje plik. |

---

## 2. Indeks krzyżowy — gdzie szukać czego

| Szukane | Dokument | Sekcja |
|---------|----------|--------|
| 12 kroków pipeline | 07 | 3 |
| 3 silniki (Sheet/LinearMeter/Multipage) | 07 | 4 |
| Kalkulatory (Imposition, Margin, Waste, RollSelector) | 07 | 5 |
| SettingResolver + 4 mapy fallbacków | 07 | 6 |
| CostPriceTrait (8 pól) | 07 | 7 |
| Produkty proste — wzór wykładniczy | 07 | 9 |
| Wykluczenia (ExclusionResolver) | 07 | 10 |
| Mapowanie Django → Laravel | 07 | 8 |
| API endpointy pricingu | 07 | 13 |
| 49 testów pricingu | 07 | 15, 12 |
| LoyaltyOverlay + RKW | 07 | 14 |
| Stałe domyślne (100%, 500 PLN, 0.70, …) | 07 | 17 |
| Storage plików — lokalnie | 08 | całość |
| Struktura `storage/app/private/` | 08 | 3 |
| tus.io + FileUploader | 08 | 4 |
| X-Accel-Redirect + FilePolicy | 08 | 5 |
| Backup / retencja plików | 08 | 10, 11 |
| Breeze + Filament scaffolding | 09 | 1 |
| Docker Compose dev | 09 | 1 (krok 4) |
| Filament resources | 09 | 1 (krok 5) |
| Subiekt / GUS / P24 / InPost | 10 | 4 |
| Idempotency + webhooki | 10 | 3 |
| FSM 16 stanów zamówienia | 06 | 1 |
| Katalog eventów | 06 | 2 |
| Orkiestracja Finance | 06 | 4 |
| Queue topology + DLQ | 06 | 5 |
| RBAC matrix 6 ról | 11 | 2 |
| 2FA (TOTP) | 11 | 3 |
| Audit log (activitylog) | 11 | 4 |
| OWASP Top 10 | 11 | 5 |
| RODO / KSeF / MPP / Omnibus | 11 | 6, 7 |
| Stack technologiczny (wersje) | 02 | całość |
| Modularny monolit | 03 | 1–3 |
| Cache + Redis | 03 | 8 |
| Meilisearch | 03 | 9 |
| Tabele SQL pełne | 04 | całość |
| Materialized views | 04 | 15 |
| Moduły biznesowe (18) | 05 | całość |
| Deploy Deployer + Nginx + Postgres config | 13 | 3, 5, 7 |
| Backup strategy | 13 | 9 |
| Runbooks / DR | 13 | 13, 14 |

---

## 3. Referencje — pliki źródłowe

### Dokumentacja architektoniczna (stara, do zachowania jako historia)

| Plik | Rozmiar | Zawartość | Status w nowej dokumentacji |
|------|---------|-----------|----------------------------|
| `_ERP_nowy/roadmap.md` | 40 KB | Szczegółowy plan kroków (ETAP 1.0–3.x) | Zastąpione przez 09 (scaffolding) i 13 (DevOps) |
| `_ERP_nowy/MASTER_PLAN.md` | 31 KB | 14 modułów, eventy, tech stack | Zastąpione przez 03, 05, 06. **MinIO → lokalne** (08). **MODULE 4 → silnik cenowy** (07) |
| `_ERP_nowy/ERP_WORKFLOW.md` | 34 KB | Crash-test, eventy, FSM | Zastąpione przez 06. |
| `_ERP_nowy/steady-hopping-aurora.md` | 190 KB | 18 modułów, schema DB, shop, editor | Zastąpione przez 01, 04, 05. **S3/MinIO → lokalne** (08). |

### Silnik cenowy (obowiązujące — nadal aktywna dokumentacja szczegółowa)

| Plik | Zawartość | Cytowane w |
|------|-----------|------------|
| `_nowy_SILNIK_CENOWY/01_PRZEGLAD_SILNIKA_CENOWEGO.md` | Architektura, typy produktów, dziedziczenie | 07, 14 |
| `_nowy_SILNIK_CENOWY/02_PIPELINE_CENOWY_12_KROKOW.md` | 12 kroków, CalculationConfig, PricingResult | 07 |
| `_nowy_SILNIK_CENOWY/03_SILNIKI_OBLICZENIOWE.md` | Sheet, LinearMeter, Multipage — algorytmy | 07 |
| `_nowy_SILNIK_CENOWY/04_MODEL_DANYCH.md` | Wszystkie encje, kolumny, ograniczenia | 04, 07 |
| `_nowy_SILNIK_CENOWY/05_PRODUKTY_PROSTE.md` | Rabat wykładniczy, cap, SimplePricingResult | 07 |
| `_nowy_SILNIK_CENOWY/06_WYKLUCZENIA_KONFIGURATOR_IMPORT.md` | Wykluczenia, konfigurator HTMX, import CSV | 07, 09 |
| `_nowy_SILNIK_CENOWY/07_PLAN_IMPLEMENTACJI_W_ERP.md` | Mapowanie Django → Laravel | 07 |
| `_nowy_SILNIK_CENOWY/08_SLOWNIK_I_INDEKS.md` | Słownik, indeks plików | 14 |

### Kod źródłowy referencyjny

- `iwp_calc_generator-main/apps/pricing/` — Python Django (do portowania).
- `iwp_calc_generator-main/apps/pricing/tests/` — **49 testów do przeniesienia 1:1**.
- `iwp_calc_generator-main/apps/core/management/commands/seed_demo.py` — dane demo.
- `iwp_calc_generator-main/apps/parameters/`, `apps/products/`, `apps/exclusions/` — modele.
- `iwp_calc_generator-main/apps/imports/services.py` — import CSV.

### Dokumentacja zewnętrzna (linki dla dev team)

| Temat | URL |
|-------|-----|
| Laravel 11 | https://laravel.com/docs/11.x |
| Inertia v3 | https://inertiajs.com/ |
| Vue 3 | https://vuejs.org/ |
| shadcn-vue | https://www.shadcn-vue.com/ |
| Reka UI | https://reka-ui.com/ |
| Tailwind CSS 3 | https://tailwindcss.com/docs |
| Pest | https://pestphp.com/ |
| PHPUnit | https://phpunit.de/ |
| Horizon | https://laravel.com/docs/horizon |
| Reverb | https://laravel.com/docs/reverb |
| spatie/laravel-permission | https://spatie.be/docs/laravel-permission |
| spatie/laravel-model-states | https://spatie.be/docs/laravel-model-states |
| spatie/laravel-activitylog | https://spatie.be/docs/laravel-activitylog |
| spatie/laravel-medialibrary | https://spatie.be/docs/laravel-medialibrary |
| Filament v3 | https://filamentphp.com/docs/3.x |
| Saloon | https://docs.saloon.dev/ |
| brick/math | https://github.com/brick/math |
| ankitpokhrel/tus-php | https://github.com/ankitpokhrel/tus-php |
| Konva.js | https://konvajs.org/ |
| Meilisearch | https://www.meilisearch.com/docs |
| PostgreSQL 16 | https://www.postgresql.org/docs/16/ |
| Deployer | https://deployer.org/ |
| OWASP Top 10 | https://owasp.org/www-project-top-ten/ |

### Dokumentacja prawna PL

| Temat | Źródło |
|-------|--------|
| KSeF | Ministerstwo Finansów — `ksef.mf.gov.pl` |
| MPP (split payment) | Ustawa VAT, art. 108a |
| RODO | EU 2016/679 + ustawa PL o ochronie danych osobowych |
| Rękojmia | Kodeks cywilny art. 556–576 |
| Prawo konsumenckie | Ustawa z 30 maja 2014 r. (14 dni odstąpienia) |
| Omnibus | Dyrektywa UE 2019/2161 + implementacja PL 2023 |

---

## 4. Historia zmian dokumentacji

| Data | Zmiana |
|------|--------|
| 2026-04-24 | Utworzenie nowej dokumentacji (00–14). Nadpisuje MinIO z legacy. Konsoliduje silnik cenowy `_nowy_SILNIK_CENOWY` w 07. Dodaje sekcję scaffolding (09) i lokalne DAMS (08). |

---

## 5. Kontakty

| Rola | Zakres |
|------|--------|
| Chief Software Architect | decyzje architektoniczne, ADR |
| Product Owner / właściciel drukarni | priorytetyzacja, feedback UX |
| Lead Developer | code review, deploy |
| DevOps / SysAdmin | infrastruktura, backupy, incident response |
| Compliance Officer (part-time) | RODO, podatki, KSeF |

---

## 6. Checklist: "Dokument jest aktualny gdy…"

Aktualizuj ten plik gdy:
- Zmienia się wersja któregoś z pakietów w `composer.json` / `package.json` (pole 02).
- Dodajesz / usuwasz moduł (01, 05).
- Zmienia się FSM zamówienia (06).
- Zmienia się macierz RBAC (11).
- Zmienia się lokalizacja backupu (08, 13).
- Nowa integracja zewnętrzna (10).
- Zmiana w pricingu (07 + zaktualizuj też `_nowy_SILNIK_CENOWY/*` gdy zmiana w kodzie Python).

Przy każdej zmiany: **commit message cytuje plik, który zmieniłeś** (np. `docs(07): update MultipageEngine validation rules`).
