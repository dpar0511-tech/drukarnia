# Krok 1.0.2 FIX — Naprawa systemu ról i uprawnień (Spatie vs Custom Role)

## Cel
Ujednolicenie systemu ról i uprawnień w projekcie. Obecnie występuje krytyczny konflikt architektoniczny (Dual Source of Truth) pomiędzy ręczną implementacją ról (`role_id` w tabeli `users`, własna tabela `roles` zamieniana później na `old_roles`) a oficjalnym pakietem `spatie/laravel-permission` zdefiniowanym w `00_MASTER_PLAN_CONSOLIDATED.md`. 

## Problemy do rozwiązania
1. **Zduplikowany stan (Dual State):** Migracje tworzą własną tabelę `roles`, dodają `role_id` do `users`, a następnie instalowany jest pakiet Spatie, który używa polimorficznych relacji `model_has_roles`. Prowadzi to do "spaghetti migrations" (np. zmiany nazwy na `old_roles`, fixowanie kluczy obcych w locie).
2. **Model `User`:** Posiada zarówno trait `HasRoles` od Spatie, jak i fizyczną kolumnę `role_id`. Zawiera "hack" w postaci metody `syncRoleById()`, która próbuje synchronizować oba zduplikowane stany.
3. **Błędne Seedery:** `AdminUserSeeder` przypisuje jedynie fizyczne `role_id`, przez co główny administrator nie otrzymuje uprawnień w systemie Spatie. `MigrateRolesSeeder` to zbędny śmieciowy kod w nowym projekcie (przeznaczony do migracji z innego systemu).
4. **Błędne Factory:** `UserFactory` próbuje manualnie synchronizować `role_id` w bazie po przypisaniu ról ze Spatie.

## Plan Implementacji (Checklist)

### Krok 1: Usunięcie nadmiarowych i konfliktowych migracji [DB]
Należy usunąć wszystkie migracje, które ręcznie zarządzają starą tabelą ról i kolumną `role_id` w tabeli `users`, pozostawiając jedynie tabelę `users` i oficjalne migracje od pakietu Spatie.

- [ ] Usuń plik `database/migrations/2026_04_20_180934_create_roles_table.php`
- [ ] Usuń plik `database/migrations/2026_04_20_180935_add_role_id_to_users_table.php`
- [ ] Usuń plik `database/migrations/2026_04_20_183240_rename_roles_to_old_roles.php`
- [ ] Usuń plik `database/migrations/2026_04_20_183951_fix_user_role_foreign_key.php`
- [ ] Sprawdź `database/migrations/0001_01_01_000000_create_users_table.php` i upewnij się, że nie zawiera ręcznych definicji `role_id`.

### Krok 2: Oczyszczenie modelu `User` [BE]
Oparcie autoryzacji w 100% o wbudowany trait `HasRoles` z pakietu Spatie, z poszanowaniem standardów Laravel Best Practices.

**W pliku `app/Models/User.php`:**
- [ ] Usuń `'role_id'` z atrybutu `#[Fillable]`.
- [ ] Usuń publiczną metodę `role(): BelongsTo`.
- [ ] Usuń publiczną metodę `syncRoleById()`.

### Krok 3: Naprawa Seederów i Factory [BE] [DB]

**W pliku `database/seeders/AdminUserSeeder.php`:**
- [ ] Usuń przypisanie `'role_id' => $adminRole->id` podczas tworzenia lub aktualizowania konta administratora.
- [ ] Po wykonaniu `User::updateOrCreate(...)` przypisz rolę bezpośrednio przez API Spatie: `$user->assignRole(SystemRole::Admin->value);`

**W pliku `database/seeders/MigrateRolesSeeder.php`:**
- [ ] Całkowicie usuń plik `MigrateRolesSeeder.php`, to martwy kod z zaszłości.

**W pliku `database/factories/UserFactory.php`:**
- [ ] W metodzie `withRole()`, usuń z callbacku `afterCreating` cały fragment ("Sync legacy role_id column") próbujący robić `update(['role_id' => ...])` na tabeli `users`.

### Krok 4: Weryfikacja spójności bazy danych [DB]
Po wprowadzeniu powyższych zmian, konieczny jest pełen reset bazy z zasileniem danymi.

- [ ] Wykonaj komendę `php artisan migrate:fresh --seed`.
- [ ] Oczekiwany wynik: brak błędów kluczy obcych z `role_id` czy relacji pomiędzy zduplikowanymi tabelami `old_roles` i `roles`. Admin znajduje się poprawnie w tabeli `model_has_roles`.
- [ ] Przetestuj działanie logowania jako administrator lokalnie (wymagane 100% pewności, że logowanie ma podpiętą autoryzację via pakiet Spatie).
