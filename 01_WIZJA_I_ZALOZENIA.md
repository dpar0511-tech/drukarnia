# 01 — WIZJA I ZAŁOŻENIA

## 1. Domena biznesowa

System ERP dla **drukarni poligraficznej** obsługującej klientów **B2B** (agencje, korporacje, instytucje) i **B2C** (osoby prywatne). Zakres obejmuje pełen cykl zamówienia:

```
Zapytanie (email / sklep / portal)
 → Wycena (12-krokowy silnik cenowy)
 → Akceptacja klienta (proforma / payment link)
 → Upload plików (DAMS, resumable)
 → Akceptacja projektu (PDF markup)
 → Produkcja (Kanban, czas pracy, maszyny)
 → Wysyłka (InPost / DPD / DHL / GLS / odbiór osobisty)
 → Dokumenty (Proforma → Zaliczkowa → VAT/Paragon, KSeF, MPP)
 → Obsługa posprzedażowa (reklamacje, lojalność)
```

System eliminuje papier i rozproszone maile, ujednolicając obieg w jednej aplikacji dla pracowników i klientów.

---

## 2. 18 modułów domenowych

| # | Moduł | Rola |
|---|-------|------|
| 1 | CRM | Klienci B2B/B2C, osoby kontaktowe, lojalność, tagi, walidacja NIP przez GUS |
| 2 | Orders | Zamówienia, pozycje, specyfikacje JSON, historia statusów |
| 3 | Products | Katalog produktów, kategorie, parametry |
| 4 | **Pricing** | **Silnik cenowy z `_nowy_SILNIK_CENOWY/`** — pełny 12-krokowy pipeline + produkty proste |
| 5 | Quotes | Wyceny, wygaśnięcia, konwersja do zamówień |
| 6 | Designs / Approvals | Akceptacja projektów, markup na PDF, runda rewizji, eskalacja |
| 7 | DAMS | **Pliki klientów i systemowe — LOKALNIE na dysku serwera** (patrz 08) |
| 8 | Production | Zadania produkcyjne, Kanban, etapy (prepress/print/postpress), czas operatora |
| 9 | Inventory | Papier, materiały, alerty low-stock |
| 10 | Finance | Orkiestracja dokumentów (Proforma → Zaliczkowa → VAT/Paragon → Korekta), Subiekt nexo, KSeF, MPP |
| 11 | Logistics | InPost (ShipX), DPD, DHL, GLS, odbiór osobisty; etykiety, tracking |
| 12 | Complaints | RMA, SLA 48 h / 14 dni (Rękojmia), dowody, decyzje (przedruk / zwrot) |
| 13 | CommHub | IMAP (poll co 2 min), email/SMS outbound, templates, notifications, inbox |
| 14 | Reports / BI | Dashboardy, materialized views, eksport CSV/XLSX |
| 15 | RBAC / Settings | Role, uprawnienia, Fortify + 2FA, audit log |
| 16 | Integrations | Adaptery zewnętrzne (Subiekt, GUS, P24, kurierzy, SMSAPI) |
| 17 | Shop | Sklep internetowy B2C/B2B, cart, guest checkout, Przelewy24 |
| 18 | Design Editor | Online edytor szablonów (Konva.js), autosave, eksport PDF z preflight |

Szczegóły każdego modułu: [05_MODULY_BIZNESOWE.md](./05_MODULY_BIZNESOWE.md).

---

## 3. Cele niefunkcjonalne (NFR)

| Wymaganie | Target | Powód |
|-----------|--------|-------|
| Kalkulacja cenowa (12-krokowy pipeline) | **< 500 ms** (99p) | Konfigurator musi być responsywny w sklepie i adminie |
| Grid cenowy (qty × time) | **< 1500 ms** | 9 progów × 2 kolumny = 18 kalkulacji per request; optymalizacja przez cache + batch |
| Homepage sklepu | **< 2 s** (TTFB < 500 ms, FCP < 1.5 s) | UX e-commerce |
| Upload pliku | **resumable, 5 GB max** | Graficy uploadują duże TIFFy, ZIP z plikami InDesign |
| Kalkulator dashboardu | **< 500 ms** | Materialized views, nie live agregacje |
| Reakcja WebSocket (Reverb) | **< 200 ms** | Kanban na produkcji działa w czasie rzeczywistym |
| SLA reklamacji — pierwsza odpowiedź | **48 h** | Obowiązek prawny i reputacja |
| SLA reklamacji — rozstrzygnięcie | **14 dni** | Ustawowa Rękojmia (PL) |
| Dostępność (MVP, 1× VPS) | **99.5%** | Akceptowalne dla jednej drukarni; redundancja dopiero w Fazie 4 |
| Backup — RPO | **24 h** | `pg_dump` + `rsync` storage o 03:00 |
| Backup — RTO | **4 h** | Odtworzenie z backupu + weryfikacja |

---

## 4. Ograniczenia prawne (Polska)

| Ograniczenie | Konsekwencja architektoniczna |
|--------------|-------------------------------|
| **KSeF** (Krajowy System e-Faktur) | Faktury VAT B2B muszą trafić do KSeF. **MVP:** przez Subiekt nexo (który ma gotową integrację). Własna integracja KSeF — Faza 4. |
| **MPP** (Mechanizm Podzielonej Płatności) | Flag MPP na fakturze gdy `brutto > 15 000 PLN` i `kupujący = B2B` + towar z załącznika 15 VAT. Pole `mpp_flag` w `dokumenty_finansowe`. |
| **RODO / GDPR** | `spatie/laravel-activitylog`, prawo do bycia zapomnianym (`ClientAnonymizationService`), eksport JSON (`ClientExportService`), rejestr czynności przetwarzania. |
| **Retencja dokumentów księgowych** | **5 lat** dla faktur i dokumentów podatkowych. Soft-delete z twardym `purge` po retencji (cron). |
| **Rękojmia** | Tabela `reklamacje` z SLA 48 h response + 14 dni resolution, automatyczne timery. |
| **UOKiK — informacja o cenie** | W sklepie (moduł 17): cena netto + brutto + **najniższa cena z 30 dni** (Omnibus). Pole `historical_min_price_last_30d` w tabeli `produkty_historia_cen`. |
| **Ustawa o prawach konsumenta** | B2C: **14 dni na odstąpienie** — flaga `cancellable_until` w `zamowienia`. Wyjątek: produkty personalizowane (większość druków!) — info musi być jawne w flow zamówienia. |

---

## 5. Role użytkowników (6 ról fiksowanych)

| Rola | Dostęp |
|------|--------|
| **Admin** | Pełne — konfiguracja systemu, zarządzanie użytkownikami, pricing settings, integracje |
| **Menedżer** | Operacyjne — zamówienia, klienci, produkcja, finanse, raporty, akceptacja wycen; bez zmiany uprawnień i konfiguracji |
| **Projektant** | DAMS, moduł akceptacji projektu, CommHub (własne wątki), edytor |
| **Operator** | Moduł produkcji (Kanban) — tylko etapy przypisane; logowanie czasu; **bez** widoczności kosztów i marż |
| **Księgowość** | Finance, raporty, dokumenty; odczyt zamówień |
| **Klient** (portal) | Własne zamówienia, akceptacje, reklamacje, płatności; portal sklepu |

Macierz uprawnień w [11_BEZPIECZENSTWO_I_RBAC.md](./11_BEZPIECZENSTWO_I_RBAC.md).

**Ważna zasada:** Operator **nie widzi** pól `cost_*` ani `margin_*` — ma widoczność tylko `time_start`/`time_stop`/etap. `PricingPolicy::viewCost()` kontroluje, co Vue component dostaje w propsach.

---

## 6. Fazowanie (roadmap skrótem)

### Faza 1 — MVP (~10–12 tygodni)
**Cel:** Email → Wycena → Pliki → Produkcja → Wysyłka. Drukarnia obsługuje zamówienia end-to-end bez papieru.

**Moduły aktywne:** RBAC (13), CRM (1), Orders (2), Products (3), Pricing (4) uproszczony, DAMS (7), Production (8), Finance (10) podstawa, Logistics (11), CommHub (13), Integrations (16).

**Co zostaje do Fazy 2+:** Portal klienta, Approvals (6), Quotes (5), Complaints (12), Reports (14), Shop (17), Design Editor (18).

**Uproszczenia Fazy 1:**
- Pricing: wycena ręcznie przez menedżera w Filamencie (pełny `PricingPipeline` działa, ale bez frontendu konfiguratora dla klienta).
- Finance: tylko Proforma (bez VAT/Paragon/Korekta).
- Notifications: in-app + email; bez SMS.
- Reports: prosty dashboard on-the-fly, bez materialized views.

### Faza 2 — Portal + Approvals + Pełny pricing (~8–10 tygodni)
**Cel:** Portal klienta, online approvals z markup, pełny 12-krokowy pipeline w konfiguratorze, Express shipping, pełne dokumenty VAT/Paragon/Korekta.

### Faza 3 — Shop + Design Editor + Analytics (~10–12 tygodni)
**Cel:** Sklep e-commerce, Konva.js edytor, pełne reporty z materialized views, Complaints w pełni, opcjonalna warstwa AI (GPT-4o do sugestii odpowiedzi email).

### Faza 4 — Enterprise
Preflight engine (automatyczna walidacja PDF), multi-tenant, native mobile app, integracje maszynowe (JDF/JMF), forecasting.

Szczegółowy rozkład zadań: [09_SZYBKI_START_MVP_SCAFFOLDING.md](./09_SZYBKI_START_MVP_SCAFFOLDING.md) (Faza 1), [13_PLAN_WDROZENIA_I_DEVOPS.md](./13_PLAN_WDROZENIA_I_DEVOPS.md) (DevOps).

---

## 7. Z czego rezygnujemy świadomie

| Decyzja | Dlaczego |
|---------|----------|
| **Brak MinIO/S3** | Drukarnia ma 1 serwer; dane wrażliwe klientów; wymóg, żeby wszystko było lokalne (patrz 08) |
| **Brak mikrousług** | Jeden programista buduje MVP; operational overhead mikrousług byłby paraliżujący |
| **Brak multi-tenant w Fazie 1–3** | Jedna drukarnia = jedna instancja. Multi-tenant dopiero gdy model biznesowy tego wymaga |
| **Brak native mobile app** | PWA wystarcza operatorom i klientom do Fazy 3 |
| **Brak własnej integracji KSeF** | Subiekt nexo ma gotową; budowa własnej = 2-3 miesiące dodatkowe |
| **Brak Jetstream** | Teams + Livewire kolidują z Inertia+Vue+shadcn. Breeze wystarcza (patrz 09) |
| **Brak zewnętrznych bibliotek UI** (Vuetify/PrimeVue/Tailwind UI) | Polityka: **tylko shadcn-vue**, spójność wizualna, ciemny motyw z semantycznymi kolorami |

---

## 8. Kluczowe decyzje architektoniczne (ADR w skrócie)

1. **Modularny monolit, nie mikrousługi** — 18 modułów jako bounded contexts w jednej bazie kodu.
2. **Inertia.js v3 jako jedyny most FE/BE** — brak równoległego REST API; Filament jest oddzielną aplikacją Livewire w `/admin`.
3. **PostgreSQL 16 z JSONB** — breakdowny pricingu, specyfikacje produktowe, scena Konva — wszystko jako JSONB.
4. **Redis + Horizon** — kolejki, cache, locks; brak Supervisor/Beanstalkd.
5. **Reverb (nie Pusher)** — Laravel-native WebSocket bez zewnętrznej usługi.
6. **Meilisearch** — full-text search po produktach, klientach, zamówieniach; polski tokenizer.
7. **Lokalne storage** — `storage/app/private/` + tus-php uploader + `spatie/laravel-medialibrary` (patrz 08).
8. **Silnik cenowy z `_nowy_SILNIK_CENOWY`** — nie własny ad-hoc; z pełnym przeniesieniem 49 testów jednostkowych (patrz 07).
9. **`spatie/laravel-model-states`** — FSM 16 stanów zamówienia zamiast ręcznej kolumny `status` + if/else.
10. **`brick/math` lub `bcmath`** — nigdy float dla pieniędzy.
