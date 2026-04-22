# Plan Implementacji - Krok 1.1.3: Admin Panel (Użytkownicy i Role)

> **Wersja:** 1.0
> **Dotyczy:** Drukarnia ERP 2026

## 1. Background & Motivation (Cel i Kontekst)
Cel: Zbudowanie fundamentalnego panelu administracyjnego do zarządzania użytkownikami (w tym pracownikami drukarni) i ich uprawnieniami w oparciu o architekturę Laravel 11, Vue 3, Inertia.js i framework komponentów shadcn-vue. Moduł wykorzystuje paczkę `spatie/laravel-permission` oraz modele `User` i `Role` w celu centralizacji uprawnień systemu.

## 2. Scope & Impact (Zakres)
- Implementacja struktury CRUD Użytkowników (strony: lista, dodawanie, edycja, blokowanie (soft-delete)).
- Implementacja struktury CRUD Ról (przypisywanie uprawnień do poszczególnych ról).
- Integracja logiki dostępu oparta o `UserPolicy`.
- Wdrożenie podejścia **Strukturalnego Multi-Page (Master-Detail)** i grupowania widoków uprawnień (**Kategoryzowane Sekcje - Accordion**) bazując na wbudowanych komponentach shadcn-vue.

## 3. Proposed Solution (Proponowane Rozwiązanie)

### A. Workflow UI/UX (Projekt Interakcji)
Wybrano klasyczny model nawigacji z oddzielnymi, przestrzennymi stronami edycji, idealny dla przejrzystości pracy (Master-Detail).
- **Lista Użytkowników (`/admin/users`)**: 
  - Główny ekran z wykorzystaniem `DataTable` (shadcn-vue). Zapewni szybkie wyszukiwanie, filtrowanie (np. status "Aktywni / Zablokowani", "Role") i paginację.
- **Zarządzanie Użytkownikiem (`/admin/users/{user}/edit`)**:
  - Widok podzielony na zakładki (`Tabs` od shadcn-vue).
  - *Zakładka Profil*: Podstawowe informacje, opcje resetu hasła (tuning zabezpieczeń).
  - *Zakładka Role*: Moduł bezpiecznego przypisywania pojedynczej lub wielu ról.
  - *Zakładka Aktywność (Integracja Cross-module)*: Wylistowanie najważniejszych akcji z logów `AuditLog` podpiętych pod użytkownika.
- **Lista Ról (`/admin/roles`)** oraz **Zarządzanie Rolami (`/admin/roles/{role}/edit`)**:
  - Zarządzanie dostępem na dedykowanym widoku z czytelnym podziałem uprawnień (`Permissions`).
  - Aby zapobiec przytłoczeniu dużą macierzą uprawnień, wszystkie dostępne permisje systemowe zostaną skategoryzowane po modułach (np. "Sprzedaż", "Logistyka", "Klienci"). Następnie zostaną wyrenderowane jako wbudowane rozwijane listy **Accordion**, z szybkimi włącznikami `Switch`/`Checkbox` dla pojedynczych pól.

### B. Business Logic Tuning (Tuning Biznesowy - Laravel Best Practices & Specialist)
- **Ochrona Wydajności Bazy**: W przypadku list pobieranych do `DataTable` wykorzystane zostaną dyrektywy wykluczające (Eager-Loading) `User::with('roles')->paginate()`, zapobiegając problemom obciążeń rzędu N+1.
- **Data Transfer Objects & Form Requests**: W celu zachowania czystości kontrolerów powstaną wydzielone FormRequests (`StoreUserRequest`, `UpdateUserRequest`, `StoreRoleRequest`). Pozwala to na zastosowanie rygorystycznej walidacji przed modyfikacją struktury.
- **Odpowiedzi API (Resources)**: Użycie wbudowanych Eloquent `JsonResource` (`UserResource`, `RoleResource`), ukrywające kluczowe metadane i optymalizujące zwracane payloady Inertii. Zabezpiecza przed wyciekami pól hashujących.
- **Ochrona Integralności Historycznej (Soft-Delete)**: Próba usunięcia pracownika nie zniszczy historii powiązanej w Order Management. Pracownik stanie się jedynie niewidoczny z systemową restrykcją logowania z poziomu `CheckIfUserIsActive` middleware. Dodano wymóg potwierdzania nieodwracalnych działań na `AlertDialog`.

## 4. Implementation Steps (Kroki Realizacji)

### Etap 1: Backend Setup & Kontrolery
1. Wygenerowanie `Admin\UserController` oraz `Admin\RoleController`.
2. Konstrukcja klas `UserPolicy` i `RolePolicy` pilnujących aby administrator bez odpowiedniej flagi `manage-users` nie wykonał edycji innej osoby lub usunięcia konta "Super-Admina".
3. Wdrożenie klas API Resources oraz formularzy Request. Konfiguracja logiki `syncRoles` i modyfikacji `spatie/laravel-permission` w kontrolerach.
4. Utworzenie zadeklarowanego rutingu pod protekcją mechanizmów middleware `auth`, `verified` w pliku `web.php` (sekcja admina).

### Etap 2: Konstrukcja Widoków Vue (Users)
1. Inicjalizacja stron Vue w katalogu `resources/js/Pages/Admin/Users/`.
2. Opracowanie `Index.vue` wraz z kolumnami systemowymi z shadcn-vue.
3. Wytworzenie struktury `Create.vue` i `Edit.vue` opartej o nawigację Inertia, obsługę akcji `$inertia.form()` i formularze kompatybilne z komponentami FormField z shadcn-vue.
4. Uruchomienie Toasts (shadcn-vue `useToast`) informujących o sukcesie wykonanej akcji oraz błędach walidacji. Zabezpieczenie buttona "Usuń" za pomocą `AlertDialog`.

### Etap 3: Konstrukcja Widoków Vue (Roles i Permissions)
1. Utworzenie mapy uprawnień w backendzie (tzw. słownika pogrupowanych ról do celów iteracji we frontendzie) przekazywanej do Inertii.
2. Inicjalizacja stron dla Role Management z kategoryzacją poprzez komponent `Accordion` i `Checkbox`.
3. Zastosowanie odpowiednich zdarzeń z Vue (emit) do wyłapywania pełnych złożeń przypisanych uprawnień przed uderzeniem `PUT`.

## 5. Verification & Testing (Testowanie)
- **Feature Tests (Pest/PHPUnit)**: Napisanie ścisłych testów jednostkowych w `tests/Feature/Admin/UserCrudTest.php` sprawdzających wszystkie ścieżki i powstrzymujących nieautoryzowane intencje oraz autoryzację soft-deletes dla pracownika (test N+1 z wbudowanym zapobieganiem Laravel 11 `Model::preventLazyLoading()`).
- **Test widoków shadcn**: Sprawdzenie integracji z `asChild` prop do kompozycji guzików (według dokumentacji globalnej projektu).
- **Static Analysis**: Kontrola `vendor/bin/pint --test` na zmodyfikowanych plikach PHP.

## 6. Migration & Rollback strategies
Z racji operowania na stabilnych migracjach dodanych w etapach 1.0.1, jedynym potencjalnym rollbakiem będzie usunięcie przypisań i plików routingu. Żadne fizyczne rekordy z `zamowienia` czy `pliki` nie ulegają bezpowrotnej transformacji dzięki wbudowanemu Soft Deletion. Awaryjne błędy logowania do admin panela po błędnym ustawieniu Roli naprawione będą komendami `tinker` na maszynie deweloperskiej.
