# Plan implementacji: Krok 1.0.1 (Poprawki po audycie konfiguracji)

## Cel
Naprawa błędów konfiguracyjnych i składniowych zidentyfikowanych podczas audytu etapu **Krok 1.0.1 — Inicjalizacja projektu [CFG]**. Zmiany mają na celu przywrócenie spójności środowiska, poprawne działanie systemu plików MinIO oraz umożliwienie pomyślnej kompilacji zasobów front-endowych (Vite).

## Zidentyfikowane problemy

1.  **Błąd kompilacji Vite/Vue (Invalid end tag)**:
    - **Lokalizacja**: `resources/js/Pages/Admin/Users/Create.vue`.
    - **Opis**: Użycie złożonej logiki ze strzałkową funkcją `r => r.name === 'Klient'` bezpośrednio w szablonie (atrybut `:disabled`) powoduje błąd parsera Vue (`RolldownError: Invalid end tag`) i przerywa proces `npm run build`.
    - **Rozwiązanie**: Przeniesienie logiki do właściwości `computed` w sekcji `<script setup>`.

2.  **Niepoprawny import i brak aliasu Ziggy**:
    - **Lokalizacja**: `vite.config.js`, `resources/js/app.js`.
    - **Opis**: Import Ziggy w `app.js` odwołuje się bezpośrednio do ścieżki względnej w `vendor/`, co bez aliasu w `vite.config.js` jest niepewne i może powodować błędy podczas resolwowania modułów w środowisku produkcyjnym.
    - **Rozwiązanie**: Dodanie aliasu `ziggy-js` w konfiguracji Vite i ujednolicenie importu w `app.js`.

3.  **Brak zdefiniowanego Bucketa MinIO w konfiguracji**:
    - **Lokalizacja**: `.env.example`.
    - **Opis**: Zmienna `AWS_BUCKET` jest pusta, co sprawia, że po inicjalizacji projektu system plików S3/MinIO nie wie, z którego kontenera korzystać, mimo że Docker przygotowuje bucket `drukarnia-files`.
    - **Rozwiązanie**: Ustawienie domyślnej wartości `AWS_BUCKET=drukarnia-files` w `.env.example`.

## Kroki do wykonania

### 1. Konfiguracja Vite (`vite.config.js`)
- Dodać alias: `'ziggy-js': path.resolve('vendor/tightenco/ziggy')`.

### 2. Aktualizacja Inicjalizacji Vue (`resources/js/app.js`)
- Zmienić import `ZiggyVue` na użycie aliasu: `import { ZiggyVue } from 'ziggy-js';`.
- Upewnić się, że wtyczka poprawnie korzysta z globalnego obiektu `Ziggy`.

### 3. Korekta Środowiska (`.env.example`)
- Ustawić `AWS_BUCKET=drukarnia-files`.

### 4. Naprawa Syntax Error (`resources/js/Pages/Admin/Users/Create.vue`)
- Wyciągnąć logikę wykrywania roli "Klient" do `computed`.
- Zaktualizować powiązanie `:disabled` w szablonie, aby korzystało z nowej właściwości.

## Weryfikacja
- Uruchomienie `npm run build` w celu potwierdzenia wyeliminowania błędu `Invalid end tag`.
- Sprawdzenie poprawności generowania adresów URL przez Ziggy w konsoli przeglądarki.
