# Plan Naprawczy — ETAP 1.1: ACCESS CONTROL

## 1. Wykryte rozbieżności i błędy

Podczas głębokiego audytu Modułu 13 (Access Control) zidentyfikowano następujące braki w porównaniu z dokumentacją `01_ROADMAP_CONSOLIDATED.md` oraz standardami projektu:

1.  **Brak `AuditLogObserver`**: Plik observera nie istnieje, mimo że jest importowany w `AppServiceProvider`. Powoduje to, że automatyczne logi (np. aktualizacja użytkownika) nie zawierają adresu IP ani User Agent, co jest wymagane przez UI (`AuditLogResource` i `Edit.vue`).
2.  **Błędna konfiguracja `activitylog.php`**: System używa domyślnego modelu `Spatie\Activitylog\Models\Activity` zamiast dedykowanego `App\Models\AuditLog`. Przez to niestandardowe atrybuty (jak `ip_address`) zdefiniowane w modelu nie są poprawnie obsługiwane.
3.  **Braki w nawigacji (`AppLayout.vue`)**: Sidebar nie zawiera sekcji administracyjnej (Użytkownicy, Role), co uniemożliwia menedżerom i adminom dostęp do CRUD-ów z poziomu interfejsu.
4.  **Błąd w `useAuth.js`**: Funkcja `canAny` jest zdefiniowana, ale nie została wyeksportowana w zwracanym obiekcie, przez co jest niedostępna w komponentach Vue.
5.  **Niespójność rejestracji**: `AppServiceProvider` posiada martwy import observera, który nie jest zarejestrowany w metodzie `boot()`.

## 2. Kroki Implementacji (Remedy)

### Krok 2.1: Naprawa systemu Audit Log (Backend)
- [ ] Utworzyć plik `app/Observers/AuditLogObserver.php`.
- [ ] Zaimplementować metodę `creating(Activity $activity)`, która doda `ip_address` i `user_agent` do właściwości logu.
- [ ] Zarejestrować observer w `AppServiceProvider::boot()`.
- [ ] Zmienić `'activity_model'` na `App\Models\AuditLog::class` w `config/activitylog.php`.

### Krok 2.2: Poprawa nawigacji i Composables (Frontend)
- [ ] W `AppLayout.vue` dodać sekcję "Administracja" z linkami do `/admin/users` i `/admin/roles`.
- [ ] Użyć ikon `Users` oraz `ShieldCheck` z `lucide-vue-next`.
- [ ] W `resources/js/Composables/useAuth.js` dodać `canAny` do listy zwracanych wartości.

### Krok 2.3: Weryfikacja i Testy
- [ ] Dodać przypadek testowy w `tests/Feature/UserRoleTest.php` sprawdzający, czy adres IP jest zapisywany w `activity_log` podczas edycji profilu.
- [ ] Uruchomić pełną suitę testów dla modułu Admin: `php artisan test --filter=Admin`.

## 3. Akceptacja Planu
Proszę o potwierdzenie przejścia do fazy realizacji powyższych punktów.
