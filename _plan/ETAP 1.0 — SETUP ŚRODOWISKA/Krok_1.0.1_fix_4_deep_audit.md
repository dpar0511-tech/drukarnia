# Plan Implementacji: Głęboki Audyt i Naprawa Kroku 1.0.1 (Krok_1.0.1_fix_deep_audit.md)

## Cel
Naprawa krytycznych błędów zidentyfikowanych po ponownym głębokim audycie etapu inicjalizacji projektu (Krok 1.0.1). Skupiamy się na błędnej implementacji wyszukiwarki (zarówno backend, jak i frontend), brakujących zabezpieczeniach danych oraz problemach z konfiguracją infrastruktury (Docker: Meilisearch i MinIO).

## Zidentyfikowane Problemy (Bugi)

1. **Frontend Search Bug (Niewłaściwe API `useHttp`)**: W komponencie `AppLayout.vue` funkcja `useHttp` dostarczana przez Inertia jest używana w sposób błędny jako funkcja asynchroniczna zwracająca Promesę (tj. `await http.get(...)` i przypisanie z `response.data`). Zgodnie z Inertia v3 API, `useHttp` opiera się na cyklu życia Inertii. Z racji tego, że globalna wyszukiwarka typu "live search" nie powinna ingerować w stan wizyt Inertii, należy tu użyć standardowej biblioteki `axios`, która jest już obecna w projekcie.
2. **Security Bug (Wyciek danych w GlobalSearchController)**: Trasa `/search` jest chroniona jedynie middlewarem `auth`. Kontroler wywołuje `User::search()` oraz `Klient::search()` i zwraca wyniki bez weryfikowania, czy aktualnie zalogowany użytkownik ma uprawnienia do podglądu tych zasobów. Każdy pracownik lub klient na portalu (jeśli miałby dostęp) mógłby odpytywać całą bazę klientów i użytkowników systemu.
3. **Infrastructure Bug (Meilisearch w Dockerze)**: W pliku `docker-compose.yml` w usłudze `meilisearch` brakuje przekazania zmiennej autoryzacyjnej `MEILI_MASTER_KEY`. Aplikacja w `.env.example` spodziewa się, że instancja Meilisearch będzie chroniona kluczem (`MEILISEARCH_KEY=masterKey`), co w konsekwencji prowadzi do odrzucania połączeń autoryzacyjnych Laravela (Scout).
4. **Infrastructure Bug (Inicjalizacja MinIO)**: Skrypt `minio-setup` korzysta w pliku `docker-compose.yml` ze zmiennych `$AWS_ACCESS_KEY_ID` i `$AWS_SECRET_ACCESS_KEY` do ustawienia aliasu `mc`. Jednak w `.env.example` są to puste wartości, przez co skrypt ulega awarii podczas instalacji Dockera, co zapobiega automatycznemu stworzeniu bucketa na pliki.
5. **Backend Bug (Nullability/Formatowanie Danych)**: Łączenie `imie` oraz `nazwisko` przy transformacji modeli użytkowników może skutkować podwójnymi spacjami, gdy któraś ze zmiennych z bazy jest nullem.

## Kroki Implementacji (Plan Naprawczy)

### 1. Naprawa Infrastruktury (Docker & .env.example)
- **`docker-compose.yml`**: Dodać `MEILI_MASTER_KEY: '${MEILISEARCH_KEY}'` w sekcji `environment` dla usługi `meilisearch`.
- **`.env.example`**: Ustawić bezpieczne lokalne domyślne wartości `AWS_ACCESS_KEY_ID=minioadmin` oraz `AWS_SECRET_ACCESS_KEY=minioadmin`, aby instancja MinIO działała poprawnie out-of-the-box i pomyślnie konfigurowała bucket w usłudze `minio-setup`.

### 2. Załatanie Luki Bezpieczeństwa i Poprawa Logiki (Backend)
- **`app/Http/Controllers/GlobalSearchController.php`**:
  - Wykonać zapytanie `User::search()` i zwrócić jego rezultaty **tylko wtedy**, gdy zachodzi `$request->user()->can('manage_users')`.
  - Wykonać zapytanie `Klient::search()` i zwrócić rezultaty **tylko wtedy**, gdy `$request->user()->can('view_clients')`.
  - Użyć funkcji pomocniczej `trim()` podczas formatowania pełnego imienia i nazwiska w odpowiedzi z `User::search()`, co wykluczy dublowanie spacji w wynikach.

### 3. Poprawa Interfejsu i Funkcjonalności Wyszukiwania (Frontend)
- **`resources/js/Layouts/AppLayout.vue`**:
  - Usunąć inicjalizację `const http = useHttp()` używaną do wyszukiwania.
  - Wewnątrz funkcji `performSearch` użyć `axios.get(route('global.search'), { params: { query } })`.
  - Obsłużyć odpowiedź i przypisać `response.data` do zmiennej reaktywnej `searchResults.value` we wbudowanym `then()`. Złapać błędy przez `.catch()` oraz ustawić flagę ładowania `isSearching` na false we fragmencie `finally()`.
  - Upewnić się o poprawnym `import axios from 'axios'` na szczycie `<script setup>`.

### 4. Weryfikacja Poprawności Działania i Integracji
- Zresetować środowisko dockerowe (`docker-compose down -v && docker-compose up -d -V`), by zweryfikować poprawne działanie inicjalizacyjne MinIO i Meilisearch.
- Uruchomić komendy `php artisan scout:import "App\Models\User"` oraz `Klient` aby potwierdzić udaną łączność z autoryzowanym serwerem Meilisearch.
- Przetestować wyciek danych autoryzacji z nowego konta operatora, upewniając się, że wyszukiwarka globalna bezpiecznie nie renderuje chronionych modeli (jeśli nie ma on wymaganych uprawnień).
