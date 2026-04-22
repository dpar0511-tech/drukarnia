# Krok 1.0.1 — Fix 7: Głęboki audyt wyszukiwarki (Meilisearch) i konfiguracji

> **Cel:** Naprawa problemów z niedziałającym wyszukiwaniem, ukrytą nawigacją oraz niestabilną integracją aplikacji z instancją Meilisearch i Laravel Scout (błędy w Kroku 1.0.1).

## 1. Zależności i konfiguracja

**Problem:** Pakiet `meilisearch/meilisearch-php` wymaga klienta HTTP PSR-18, ale w projekcie może nie być wyraźnej zależności (np. `guzzlehttp/guzzle`), co powoduje ciche błędy wyszukiwania Scout.
W środowisku testowym (Faza 1) włączenie asynchronicznej obsługi Scout (`SCOUT_QUEUE=true`) sprawia, że bez uruchomionego serwisu deweloperskiego `horizon` na środowisku lokalnym tabele z danymi nigdy nie zostaną spushowane do silnika Meilisearch, a wyszukiwanie wciąż pozostanie puste. Opcja domyślna dla konfiguracji `driver => collection` w `config/scout.php` (zamiast fallbacku bazodanowego `database`) jest bardzo mało optymalna w przypadku błędu.

**Zmiany:**
- Uruchomić `composer require guzzlehttp/guzzle`, aby upewnić się, że Laravel Scout bezproblemowo obsłuży strzały do API Meilisearch.
- Zmodyfikować plik `.env.example`, zmieniając wartość `SCOUT_QUEUE=true` na `SCOUT_QUEUE=false` dla bezpiecznego testowania MVP z synchronicznym uzupełnianiem indeksu.
- Zmodyfikować `config/scout.php`, by w razie braku wpisu domyślnym silnikiem był `database`, a nie `collection`:
  `'driver' => env('SCOUT_DRIVER', 'database'),`

## 2. Uodpornienie aplikacji na awarię Meilisearch (Try-Catch)

**Problem:** Brak bezpiecznika w pliku `GlobalSearchController`. Jeśli serwer Meilisearch padnie (np. z powodu niepoprawnej konfiguracji Dockera), cała aplikacja zwraca błąd 500 przy każdej wpisanej literce w wyszukiwarkę. Dodatkowo model User pozwala na nulla dla `imie` i `nazwisko`, co zwraca puste wyniki na layoucie frontendowym.

**Zmiany:**
- Edycja pliku `app/Http/Controllers/GlobalSearchController.php`:
  - Owinięcie zapytań `User::search($query)->take(5)->get()` w blok `try-catch`.
  - W bloku `catch (\Exception $e)` zwrócenie pustej kolekcji lub fallback z bezpośredniego zapytania SQL po strukturze bazy (`where('email', 'like')`).
  - Rozwiązanie problemu formatowania braku nazwy w wynikach (tzw. map na tablicę UI):
    `'title' => trim($user->imie . ' ' . $user->nazwisko) ?: $user->email,`
  - W adresie URL klienta `route('admin.klienci.edit')` poprawić odnośnik `#` jeśli to możliwe lub zapewnić poprawne wstrzykiwanie.

## 3. Poprawka nawigacji w Vue i błędów z uprawnieniami

**Problem:** Frontend oczekiwał w `Layouts/AppLayout.vue` uprawnień takich jak `view_clients` czy `view_orders`, które nie istnieją w zaplanowanej bazie ról i wygaszają menu nawigacyjne nawet użytkownikom uprzywilejowanym.

**Zmiany:**
- W pliku `resources/js/Layouts/AppLayout.vue` naprawić `v-if`:
  - Link do Klientów: Zmienić `can('view_clients')` na `can('manage_clients')`.
  - Link do Zamówień: Zmienić `can('view_orders')` na `can('manage_orders') || can('view_own_orders')`.
  - Link do Wyszukiwarki: Wyniki zależą od `$request->user()->can('manage_users')` w backendzie – to zostaje bez zmian.

## 4. Reindeksacja i uruchomienie po poprawkach

Aby zaktualizować dane po wprowadzonych modyfikacjach bazy i modeli, należy zsynchronizować brakujące rekordy (z uruchomionym asynchronicznym wpychaniem do Redisa po ustawieniu w `.env` `SCOUT_QUEUE=false` na dev).

**Zmiany (Komendy do wykonania po implementacji):**
```bash
php artisan scout:flush "App\Models\User"
php artisan scout:import "App\Models\User"
php artisan scout:flush "App\Models\Klient"
php artisan scout:import "App\Models\Klient"
```
