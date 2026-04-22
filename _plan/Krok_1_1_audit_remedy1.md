# PLAN REMEDIACJI: ETAP 1.1 — MODULE 13: ACCESS CONTROL

## 1. Stan obecny i zdiagnozowane problemy (Audit Findings)

### A. Krytyczna niespójność uprawnień (Permission Mismatch)
Istnieją trzy różne źródła prawdy o uprawnieniach, które nie są ze sobą zsynchronizowane:
1.  **RoleAndPermissionSeeder**: Definiuje uprawnienia takie jak `manage_orders`, `manage_clients`.
2.  **PermissionService**: Definiuje uprawnienia takie jak `view_orders`, `create_orders`, `view_klienci`.
3.  **Komponenty Frontendowe**:
    - `Dashboard.vue` sprawdza `can('view_orders')`, `can('create_orders')`, `can('create_clients')`.
    - `AppLayout.vue` (Sidebar) sprawdza `can('manage_orders')`, `can('manage_clients')`, `can('view_production')`.
**Skutek**: Nawet Admin nie widzi większości opcji w Dashboardzie, ponieważ posiada `manage_orders`, a UI oczekuje `view_orders`.

### B. Niespójność struktury roli w Auth Props
1.  **HandleInertiaRequests**: Przekazuje rolę jako obiekt: `role: { id: X, name: 'Admin' }`.
2.  **UserResource**: Przekazuje rolę jako string: `role: 'Admin'`.
**Skutek**: Kompozybel `useAuth.js` oczekuje `user.value.role?.name`. Działa to dla zalogowanego użytkownika (z HandleInertia), ale zawodzi w innych miejscach systemu, gdzie użyty jest UserResource.

### C. Problemy z Globalnym Wyszukiwaniem
1.  **Scout vs SQL**: `GlobalSearchController` używa Scouta, ale listy w panelu Admina używają tradycyjnego `LIKE`.
2.  **Brak indeksowania**: Przy `SCOUT_DRIVER=meilisearch` i braku uruchomionego serwisu, wyszukiwanie zwraca puste wyniki mimo istnienia fallbacku (ponieważ wyjątek może nie zostać rzucony na etapie budowania query).
3.  **Placeholdery w routingu**: Linki z wyszukiwania do Klientów prowadzą do tras-placeholderów, które nie mają jeszcze pełnej logiki kontrolerów.

### D. System Role Enums
1.  Użycie polskiego znaku w `SystemRole::Menedzer -> 'Menedżer'`. Jest to poprawne językowo, ale wymaga rygorystycznej konsekwencji w kodzie (sprawdzanie `hasRole('Menedżer')` zamiast `hasRole('Menedzer')`).

---

## 2. Plan Implementacji Poprawek (Implementation Plan)

### Krok 1: Unifikacja Uprawnień (Single Source of Truth)
- [ ] Zmodyfikować `PermissionService`, aby stał się jedynym źródłem nazw uprawnień.
- [ ] Zaktualizować `RoleAndPermissionSeeder`, aby pobierał listę uprawnień z `PermissionService::getFlatPermissions()`.
- [ ] Ujednolicić nazewnictwo: wprowadzić hierarchię (np. `orders.view`, `orders.manage`, `clients.view`) zamiast mieszania `manage_orders` i `view_orders`.

### Krok 2: Poprawa struktury danych (Data Consistency)
- [ ] Zaktualizować `UserResource`, aby zawsze zwracał obiekt roli: `role: { id, name }`, dla zachowania spójności z `HandleInertiaRequests`.
- [ ] Zaktualizować `useAuth.js`, aby bezpiecznie obsługiwał oba formaty (string/object) jako fallback.

### Krok 3: Naprawa Wyszukiwania (Search Fix)
- [ ] Dodać sprawdzenie połączenia z Meilisearch przed wywołaniem `search()` w `GlobalSearchController`.
- [ ] Zapewnić, że `UserController@index` również może korzystać ze Scouta, jeśli jest dostępny.
- [ ] Dodać polecenie `php artisan scout:import` do skryptów startowych w dokumentacji.

### Krok 4: Uszczelnienie Access Control
- [ ] Przegląd `AppLayout.vue` i `Dashboard.vue` pod kątem użycia nowych, zunifikowanych nazw uprawnień.
- [ ] Implementacja brakujących widoków dla `manage_clients` i `manage_orders` (nawet jako proste listy), aby linki w Sidebarze i Wyszukiwarce nie prowadziły do pustych stron.

### Krok 5: Walidacja i Testy
- [ ] Napisać testy Feature sprawdzające dostęp do Dashboardu dla każdej z ról zdefiniowanych w `SystemRole`.
- [ ] Zweryfikować działanie `AuditLogObserver` przy masowych operacjach (np. import).

---

## 3. Harmonogram Pracy (Action Items)

1.  **Refaktoryzacja PermissionService & Seeder** (Klucz do odblokowania UI).
2.  **Aktualizacja UserResource & useAuth**.
3.  **Korekta Dashboard.vue & AppLayout.vue** (Dostosowanie do nowych uprawnień).
4.  **Naprawa GlobalSearchController**.
5.  **Uruchomienie testów (Pest/PHPUnit)**.
