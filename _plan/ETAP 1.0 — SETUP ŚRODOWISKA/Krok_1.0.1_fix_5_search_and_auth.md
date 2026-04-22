# Plan: Naprawa i Dokończenie Kroku 1.0.1 (Search & Auth Fix)

## Cel
Naprawa błędów zidentyfikowanych podczas audytu inicjalizacji projektu (Krok 1.0.1). Skupienie na poprawie przekazywania ról do Inertia oraz uruchomieniu globalnej wyszukiwarki opartej na Meilisearch.

## Problemy do rozwiązania
1. **HandleInertiaRequests**: Błąd `auth.user.role` - próba dostępu do nieistniejącej relacji `$user->role`. System korzysta z `spatie/laravel-permission`, więc role są dostępne przez relację `roles`.
2. **Global Search (UI)**: Pole wyszukiwania w `AppLayout.vue` jest statycznym placeholderem bez logiki.
3. **Global Search (Backend)**: Brak kontrolera i tras obsługujących zapytania wyszukiwania.
4. **Meilisearch**: Model `Klient` posiada błędne mapowanie pól w `toSearchableArray` (odwołania do nieistniejących kolumn), co uniemożliwia poprawne indeksowanie.

## Kroki implementacji

### 1. Poprawa Inertia Shared Data
* Edycja `app/Http/Middleware/HandleInertiaRequests.php`.
* Zmiana mapowania pola `role` w tablicy `auth` na: `$request->user()->roles->first()?->only(['id', 'name'])`.
* Upewnienie się, że `permissions` są przekazywane jako prosta tablica nazw.

### 2. Implementacja Backend Search
* Stworzenie kontrolera `app/Http/Controllers/GlobalSearchController.php`.
* Implementacja logiki przeszukującej modele `User` oraz `Klient` przy użyciu `Model::search($query)->take(5)->get()`.
* Przygotowanie odpowiedzi JSON grupującej wyniki według typu (np. "Użytkownicy", "Klienci").
* Rejestracja trasy `GET /search` w `routes/web.php` (zabezpieczona middleware `auth`).

### 3. Implementacja Frontend Search (Inertia v3)
* Modyfikacja `resources/js/Layouts/AppLayout.vue`.
* Dodanie reaktywnej zmiennej `searchQuery` powiązanej z `Input` przez `v-model`.
* Użycie `watch` z `lodash.debounce` do wyzwalania zapytań.
* Wykorzystanie `useHttp` z Inertia v3 do pobierania wyników bez przeładowania strony.
* Stworzenie interfejsu dropdown pod polem wyszukiwania wyświetlającego wyniki i linki do zasobów.

### 4. Optymalizacja Scout i Meilisearch
* Korekta `app/Models/Klient.php`: aktualizacja `toSearchableArray` (użycie `imie_nazwa` zamiast `name` oraz `email_glowny`).
* Wykonanie ponownego indeksowania: `php artisan scout:import "App\Models\User"` oraz `php artisan scout:import "App\Models\Klient"`.

## Weryfikacja
1. **Roles Check**: Sprawdzenie w Vue DevTools czy `auth.user.role` zawiera poprawne dane po zalogowaniu.
2. **Search Test**: Wpisanie nazwy klienta lub adresu email użytkownika w topbarze i weryfikacja czy dropdown wyświetla poprawne wyniki.
3. **Redirect Test**: Sprawdzenie czy kliknięcie w wynik wyszukiwania poprawnie przenosi do widoku szczegółów (np. `/clients/{id}`).
4. **Resilience**: Weryfikacja działania wyszukiwarki przy pustym zapytaniu lub błędach połączenia z Meilisearch.
