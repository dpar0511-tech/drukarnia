# Plan Naprawczy: Głęboki Audyt Wyszukiwarki i Konfiguracji (Krok 1.0.1)

## 1. Cel i Zakres
Celem tego planu jest wyeliminowanie 6 krytycznych błędów zidentyfikowanych podczas głębokiego audytu implementacji wyszukiwarki globalnej oraz struktury uprawnień w trasach (routingu). Poprawki dotyczą bezpieczeństwa danych (SoftDeletes), stabilności środowiska deweloperskiego (SQLite) oraz responsywności interfejsu użytkownika (Race Conditions).

## 2. Zidentyfikowane Problemy i Rozwiązania

### 2.1. Wyciek usuniętych rekordów (SoftDeletes) w wyszukiwarce
**Problem:** Kod SQL fallbacku w `GlobalSearchController` używa `orWhere` bez grupowania, co sprawia, że rekordy oznaczone jako usunięte (`deleted_at != null`) pojawiają się w wynikach wyszukiwania, omijając globalny zakres Eloquent.
**Rozwiązanie:** Zastosowanie grupowania warunków (closure wewnątrz metody `where`) w celu odizolowania logiki wyszukiwania od automatycznych filtrów `SoftDeletes`.

### 2.2. Niekompatybilność SQLite (`CONCAT` Bug)
**Problem:** Użycie surowego SQL `CONCAT(imie, ' ', nazwisko)` w fallbacku wyszukiwania użytkowników powoduje błędy w środowisku deweloperskim i testowym, ponieważ SQLite nie obsługuje tej funkcji.
**Rozwiązanie:** Zastąpienie `CONCAT` elastycznym filtrem `where/orWhere` operującym oddzielnie na kolumnach `imie` i `nazwisko`.

### 2.3. Podatność na Array Payload (Błąd 500)
**Problem:** Brak rzutowania typu dla parametru wejściowego sprawia, że przesłanie tablicy w zapytaniu (np. `?query[]=test`) powoduje błąd krytyczny serwera (Type Error).
**Rozwiązanie:** Wymuszenie typu string dla wejścia za pomocą `$request->string('query')`.

### 2.4. Wyścig zdarzeń (Race Condition) w UI
**Problem:** Brak mechanizmu przerywania (anulowania) nieaktualnych żądań Axios sprawia, że stare wyniki wyszukiwania mogą nadpisać aktualny stan UI (np. po wyczyszczeniu pola wyszukiwania).
**Rozwiązanie:** Implementacja `AbortController` w komponencie `AppLayout.vue` w celu unieważniania poprzednich żądań przy każdej zmianie zapytania.

### 2.5. Błędna struktura Middleware w `web.php`
**Problem:** Trasy edycji klientów i zamówień są zablokowane nadrzędnym uprawnieniem `manage_users`, co uniemożliwia dostęp użytkownikom (np. Operatorom), którzy posiadają tylko uprawnienia do klientów, a nie do zarządzania kontami pracowników.
**Rozwiązanie:** Reorganizacja grup middleware – wyciągnięcie tras `/klienci` i `/zamowienia` poza blok `manage_users` i nadanie im właściwego uprawnienia `manage_clients`.

### 2.6. Brak ochrony dla `roles.destroy`
**Problem:** Trasa usuwania roli znajduje się poza dedykowanym blokiem uprawnień `manage_roles`.
**Rozwiązanie:** Przeniesienie trasy `Route::delete('/roles/{role}', ...)` do grupy chronionej przez `permission:manage_roles`.

## 3. Kroki Implementacji

### Krok 1: Naprawa Kontrolera (`app/Http/Controllers/GlobalSearchController.php`)
- Zabezpieczenie odczytu parametru: `$query = (string) $request->string('query');`.
- W bloku `catch` (SQL Fallback) dla `User`:
  - Usunięcie `orWhereRaw("CONCAT...")`.
  - Wprowadzenie closure: `where(function($q) { ... })`.
  - Rozbicie `$query` na terminy i wyszukiwanie po `imie` LUB `nazwisko`.
- W bloku `catch` dla `Klient`:
  - Wprowadzenie closure: `where(function($q) { ... })` dla grupowania warunków `imie_nazwa` i `email_glowny`.

### Krok 2: Naprawa UI (`resources/js/Layouts/AppLayout.vue`)
- Dodanie zmiennej `let abortController = null;`.
- Aktualizacja `performSearch`:
  - Dodanie `abortController?.abort();`.
  - Inicjalizacja `abortController = new AbortController();`.
  - Przekazanie `signal: abortController.signal` do `axios.get`.
  - Dodanie obsługi `axios.isCancel(error)`.

### Krok 3: Naprawa Routingu (`routes/web.php`)
- Przeniesienie metody `destroy` dla ról do bloku `manage_roles`.
- Utworzenie nowej grupy tras:
  ```php
  Route::prefix('admin')->name('admin.')->middleware(['auth', 'permission:manage_clients'])->group(function () {
      Route::get('/klienci/create', ...);
      Route::get('/klienci/{klient}/edit', ...);
      Route::get('/zamowienia/create', ...);
  });
  ```

## 4. Weryfikacja
1. **Interakcja UI:** Wpisz tekst, a następnie szybko go skasuj. Upewnij się, że lista wyników zostaje wyczyszczona i żadne opóźnione zapytanie jej nie przywróci.
2. **Uprawnienia:** Zaloguj się jako użytkownik z prawem `manage_clients`, ale bez `manage_users`. Spróbuj otworzyć klienta z wyszukiwarki – operacja musi zakończyć się sukcesem (brak błędu 403).
3. **SoftDeletes:** Usuń testowego klienta, a następnie spróbuj go wyszukać. Rekord nie powinien się pojawić w wynikach.
4. **Zgodność SQL:** Wyłącz Meilisearch w `.env` i zweryfikuj, czy wyszukiwanie użytkowników działa poprawnie na bazie SQLite (brak błędu o funkcji `CONCAT`).
