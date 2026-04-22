# Plan Naprawczy: Audyt Konfiguracji i Wyszukiwarki (Krok 1.0.1)

## 1. Cel i Tło
Głęboki audyt konfiguracji projektu zainicjowanej w Kroku 1.0.1 wykazał kilka krytycznych błędów, w szczególności niedziałający system stylów dla plików Vue (Tailwind v4) oraz błędy logiczne i nawigacyjne w globalnej wyszukiwarce (`GlobalSearchController` i `AppLayout.vue`). Ten plan ma na celu wyeliminowanie wszystkich znalezionych niezgodności.

## 2. Zidentyfikowane błędy i Instrukcje Implementacji

### 2.1. Brak kompilacji Tailwind CSS dla plików Vue
**Problem:** W pliku `resources/css/app.css` nowa dyrektywa `@source` wskazuje na pliki `.js` oraz `.blade.php`, ale całkowicie pomija pliki `.vue`. Skutkuje to brakiem klas Tailwind w komponentach UI.
**Rozwiązanie:** 
- W `resources/css/app.css` dodać nową linię: `@source '../**/*.vue';`.

### 2.2. Błędne adresy URL i logika fallback w `GlobalSearchController`
**Problem:** Zwracane wyniki zapytań wyszukiwania dla `Klient` przekierowują do `route('admin.klienci.create')`, co kieruje na formularz tworzenia zamiast do edycji/podglądu. Ponadto fallback SQL dla `User` nie radzi sobie ze spacjami (np. "Jan Kowalski"), szukając rozłącznie w kolumnach `imie` i `nazwisko`.
**Rozwiązanie:**
- W `app/Http/Controllers/GlobalSearchController.php` poprawić kod `url` dla klienta z `route('admin.klienci.create')` na `route('admin.klienci.edit', $klient->id)` (zarówno w bloku `try`, jak i `catch`).
- Zmodyfikować kwerendę SQL dla User w bloku `catch`, wykorzystując łączenie kolumn: `->orWhereRaw("CONCAT(imie, ' ', nazwisko) LIKE ?", ["%{$query}%"])`.
- W pliku `routes/web.php` dodać placeholder dla braku routingu edycji klientów.

### 2.3. Błędy nawigacyjne i UI w `AppLayout.vue`
**Problem:** Brakujący import `route` z `ziggy-js` w `<script setup>`. Nawigacja używa "twardych" ścieżek (np. `href="/dashboard"`). Dropdown wyników wyszukiwania nie zamyka się po kliknięciu poza obszarem (brak obsługi "click-outside").
**Rozwiązanie:**
- W `resources/js/Layouts/AppLayout.vue` zaimportować `route`: `import { route } from 'ziggy-js'`.
- Zamienić atrybuty `href` w komponentach `<Link>` z twardych na wygenerowane przez ziggy, np.: `:href="route('dashboard')"`.
- Dodać referencję do kontenera wyszukiwarki i zaimplementować ukrywanie `searchResults` za pomocą `onClickOutside` z pakietu `@vueuse/core`.

## 3. Weryfikacja (Verification Steps)
1. **Kompilacja stylów:** Uruchomić `npm run build` i sprawdzić, czy klasy Tailwind są widoczne w przeglądarce w komponentach Vue.
2. **Wyszukiwanie SQL:** Wyłączyć Meilisearch i wpisać "Jan Kowalski" w wyszukiwarkę. Sprawdzić, czy system poprawnie znajduje użytkownika.
3. **Nawigacja:** Kliknąć w wynik wyszukiwania klienta i zweryfikować, czy adres URL prowadzi do `/admin/klienci/{id}/edit`.
4. **Interakcja UI:** Sprawdzić, czy kliknięcie poza dropdownem wyszukiwania poprawnie go zamyka.
