# Plan Naprawy: Krok 1.0.2 — Baza danych: fundament [DB]

Ten plan ma na celu naprawę wszystkich rozbieżności w schemacie bazy danych zidentyfikowanych podczas głębokiego audytu, zgodnie z dokumentacją `_docs/`.

## Cel
Doprowadzenie struktury bazy danych do pełnej zgodności z `04_DATABASE_SCHEMA.md` oraz `00_MASTER_PLAN_CONSOLIDATED.md`, naprawiając braki w modułach Orders, CRM, Pricing, DAMS i Production.

## Kluczowe Braki i Problemy (Audyt)
1.  **Brak tabel Modułu Zamówień (Step 1.4):** Brak `pozycje_zamowienia`, `specyfikacje_druku`, `historia_statusow`.
2.  **Brak tabel Modułu Cennika (Step 1.5):** Brak `reguly_cenowe`, `indywidualne_cenniki`, `rabaty_definicje`, `kalkulacje_cen`, `koszty_wlasne`.
3.  **Brak tabel Modułu Produkcji (Step 1.7):** Brak `zlecenia_produkcyjne`, `etapy_produkcji`, `maszyny`, `operatorzy_produkcji`.
4.  **Brak tabel CRM (Step 1.3):** Brak `tagi`, `klient_tag`.
5.  **Brak tabeli Statusów (Moduł 1):** Brak `status_definitions`.
6.  **Brak tabeli DAMS (Step 1.6):** Brak `temporary_uploads`.
7.  **Brak krytycznych indeksów:** Brak indeksów na polach status, klient_id, numer w `zamowienia`.
8.  **Brak integracji State Machine:** Model `Zamowienie` nie używa `spatie/laravel-model-states`.

## Kroki Implementacji

### 1. Migracje Naprawcze
Utworzenie brakujących tabel w logicznych paczkach:

*   **Paczka A (CRM & Core):**
    *   Tabela `tagi` i pivot `klient_tag`.
    *   Tabela `status_definitions` (zgodnie z Modułem 1 w Master Planie).
    *   Indeksy dla `zamowienia` i `klienci`.
*   **Paczka B (Orders & Specs):**
    *   Tabela `pozycje_zamowienia` (nazwa, naklad, format, material, kolorystyka, cena_netto).
    *   Tabela `specyfikacje_druku` (pozycja_id, parametry JSONB).
    *   Tabela `historia_statusow`.
*   **Paczka C (Pricing Engine):**
    *   Tabele: `reguly_cenowe`, `indywidualne_cenniki`, `rabaty_definicje`, `kalkulacje_cen`, `koszty_wlasne`.
*   **Paczka D (DAMS & Production):**
    *   Tabela `temporary_uploads` (dla tus.io).
    *   Tabele: `maszyny`, `operatorzy_produkcji`, `zlecenia_produkcyjne`, `etapy_produkcji`.

### 2. Aktualizacja Modeli (App/Models)
*   Dodanie relacji do `Zamowienie` (`pozycje`, `historiaStatusow`).
*   Utworzenie brakujących modeli: `PozycjaZamowienia`, `SpecyfikacjaDruku`, `Tag`, `RegulaCenowa`, `Maszyna` itd.
*   Implementacja `spatie/laravel-model-states` w modelu `Zamowienie`.

### 3. Seedery
*   `StatusDefinitionSeeder`: Wypełnienie 16 statusów (kod, nazwa_pl, kolor, ikona).
*   `PoziomLojalnosciSeeder`: Podstawowe progi lojalności.

## Weryfikacja
1.  Uruchomienie `php artisan migrate`.
2.  Sprawdzenie struktury bazy danych przez `php artisan mcp:start app` (database_schema).
3.  Testy relacji w Tinkerze (np. `$order->pozycje()->create(...)`).
