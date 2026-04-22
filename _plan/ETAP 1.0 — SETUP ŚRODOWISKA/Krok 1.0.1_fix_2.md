## Plan: Audyt Krok 1.0.1 — Poprawki Architektury i Konfiguracji

Rozszerzony audyt konfiguracji (Krok 1.0.1) wykazał krytyczne niespójności pomiędzy definicją w środowisku Docker a środowiskiem lokalnym (.env), duplikację procesów kolejkowych, brak integracji zainstalowanego silnika wyszukiwania (Meilisearch) oraz nowszą wersję Tailwind CSS niż zakładała roadmapa. Plan ma na celu stabilizację infrastruktury.

**Steps**
1. **Rozwiązanie konfliktu sieciowego Docker vs Host**
   - Aktualizacja pliku `docker-compose.yml` w sekcji `app` oraz `horizon` poprzez dopisanie jawnych zmiennych środowiskowych (DB_HOST, REDIS_HOST, AWS_ENDPOINT) celujących w nazwy serwisów, zamiast polegania na lokalnych wartościach `127.0.0.1` z pliku `.env`.
2. **Usunięcie duplikacji procesów Horizon**
   - Modyfikacja skryptu `dev` w `composer.json` poprzez usunięcie komendy `php artisan horizon` ze składni `concurrently`. Skrypt powinien odtąd uruchamiać jedynie serwer PHP, Pail oraz Vite, powierzając zadania kolejek wyłącznie kontenerowi Dockera.
3. **Akceptacja Tailwind CSS v4 w projekcie (ADR)**
   - Aktualizacja pliku `_docs/03_ARCHITECTURE_ADR.md` (lub `01_ROADMAP_CONSOLIDATED.md`) potwierdzająca zatwierdzenie i użycie Tailwind CSS w wersji v4, zgodnie z najnowszym standardem pakietu shadcn-vue.
4. **Integracja silnika Meilisearch**
   - Instalacja pakietu `laravel/scout` (oraz zależności HTTP dla meilisearch) celem połączenia aplikacji z kontenerem Meilisearch, który został zadeklarowany, ale pozostawał nieużywany.
   - Uzupełnienie pliku `.env.example` oraz `.env` o zmienne konfiguracyjne dla usługi Scout.
5. **Konfiguracja Persistent Layout (Inertia v3)**
   - Aktualizacja konfiguracji frontendu w `app.js` poprzez dodanie mechanizmu automatycznego aplikowania globalnego komponentu `AppLayout.vue` dla każdej renderowanej strony (persistent layout z Inertia.js).

**Relevant files**
- `docker-compose.yml` — sekcje `app` i `horizon` (nadpisanie zmiennych `DB_HOST`, `REDIS_HOST`, `AWS_ENDPOINT`).
- `composer.json` — klucz `scripts.dev` (usunięcie `"php artisan horizon"`).
- `_docs/01_ROADMAP_CONSOLIDATED.md` — zmiana wzmianki o Tailwind CSS z v3 na v4.
- `resources/js/app.js` — nadpisanie domyślnego layoutu w metodzie `resolvePageComponent`.
- `.env` / `.env.example` — dodanie zmiennych `SCOUT_DRIVER` oraz `MEILISEARCH_HOST`.

**Verification**
1. Uruchomienie `docker-compose up -d` i weryfikacja logów kontenerów `app` i `horizon` (czy potrafią połączyć się z DB i Redis).
2. Wykonanie `npm run dev` i sprawdzenie, czy nie ma kolizji wokół procesu Horizon.
3. Wykonanie komendy instalującej `laravel/scout` oraz konfiguracja modelu testowego z użyciem Searchable.
4. Nawigacja w przeglądarce celem upewnienia się, że `AppLayout` nadal owija komponenty Vue, ale bez redundancji importów.

**Decisions**
- Zgodnie z ustaleniami, rezygnujemy z lokalnego odpalania Horizon na rzecz pełnego wykorzystania środowiska Docker.
- Akceptujemy wdrożenie Tailwind CSS v4, ponieważ zapewnia wyższą wydajność i natywne wsparcie shadcn-vue, co wymagało aktualizacji dokumentów projektowych.

**Further Considerations**
1. Czy wdrożenie uwierzytelnienia (Laravel Fortify), które znalazło się na środowisku przedwcześnie, powinno zostać zachowane i udokumentowane jako wykonane w przód, czy usunięte na czas dokończenia etapu 1.0? (Rekomendacja: zachować, ale odpowiednio oznaczyć status postępu w dokumentacji).