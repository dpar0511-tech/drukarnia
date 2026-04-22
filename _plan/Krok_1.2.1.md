# Plan Implementacji: Krok 1.2.1 — Baza danych [DB] (ZAKOŃCZONO - FIX)

**Status:** ZAKOŃCZONO (Refaktoryzacja naprawcza zrealizowana 2026-04-20)
**Moduł:** 8 (Communication Hub)

---

## 1. Zmiany w Bazie Danych (Migracje - STAN DOCELOWY)

### 1.1. Rozszerzona tabela `zamowienia`
- **Kolumny:** 
  - `id`, `numer` (DRK-YYYY-XXXXX, UNIQUE), `klient_id`, `menedzer_id`, `status`, `priorytet`, `channel`, `termin_realizacji`, `parent_order_id`, `source_email`, `uwagi_wewnetrzne`, `uwagi_klienta`.

### 1.2. Tabela `watki_komunikacji`
- **Kolumny:**
  - `id`, `zamowienie_id`, `klient_id`, `temat`, `status` (nowy, otwarty, zamkniety).

### 1.3. Tabela `wiadomosci`
- **Kolumny:**
  - `id`, `watek_id`, `kierunek`, `kanal`, `tresc` (longtext), `nadawca_email`, `odbiorca_email`, `zewnetrzny_id` (**UNIQUE**), `status_dostarczenia`, `przeczytana`.

### 1.4. Tabela `szablony_wiadomosci`
- **Kolumny:**
  - `id`, `nazwa` (UNIQUE), `kanal`, `temat`, `tresc_template`, `zmienne` (JSON).

### 1.5. System Plików (DAMS) & Załączniki
- **Tabele:** `pliki`, `wersje_plikow`, `powiazania_plikow`, `zalaczniki_wiadomosci`.

---

## 2. Modele Eloquent

- Modele zostały zaktualizowane o rzutowanie (casts) Enums oraz relacje (HasMany, BelongsTo, MorphMany).
- Dodano scope `unread()` dla powiadomień.
- Implementacja Observera `AuditLog` dla kluczowych zmian.
