# SZCZEGÓŁOWY PLAN IMPLEMENTACJI - Krok 1.1.2: Frontend Auth [FE]

## 1. Kontekst i Cel (Zgodnie z Architekturą i Master Planem)
Krok 1.1.2 odpowiada za zbudowanie interfejsu użytkownika dla systemu uwierzytelniania (Logowanie, Layout, Dashboard) zgodnie ze stosowanym standardem w projekcie: **Vue 3 + Inertia.js (v3) + Tailwind CSS + shadcn-vue**. 
Należy zapewnić obsługę widoków specyficznych dla ról i uprawnień (RBAC) zdefiniowanych w module Access Control (Moduł 13).

## 2. Cross-Modułowa Wzajemodzia (Workflow UX/UI)

### A. Przepływ Logowania (Login Workflow)
1. **Wejście:** Użytkownik otwiera `/login` (lub jest tam przekierowany przez middleware z chronionej trasy).
2. **Akcja:** Użytkownik wprowadza e-mail i hasło. System wykorzystuje Inertia `useForm` (lub `<Form>` z `@inertiajs/vue3`).
3. **Walidacja:** Jeśli są błędy (np. złe hasło), wyświetlane są natychmiast pod polami za pomocą komponentów formularza `shadcn-vue`.
4. **Sukces:** Po udanym uwierzytelnieniu za pomocą Laravel Fortify / Sanctum, serwer wysyła odpowiedź i Inertia przekierowuje użytkownika na `/dashboard`.
5. **Rozpoznanie Roli (Routing):** Backend przesyła do frontendu obiekt `user` wraz z jego rolą (`role_id` / nazwa) oraz listą uprawnień (`permissions`), za pomocą współdzielonych propsów w Middleware `HandleInertiaRequests`.

### B. Spersonalizowany Dashboard (Role-Based UX)
Po wejściu na `/dashboard`, interfejs dostosowuje się dynamicznie. Zamiast wielu różnych kontrolerów, mamy jeden punkt wejścia, ale komponenty na dashboardzie reagują na uprawnienia:
- **Admin / Menedżer:** Widzi kafelki KPI (Oczekujące zamówienia, Przychód) oraz skróty do zarządzania wycenami.
- **Projektant:** Widzi powiadomienia o nowych plikach do preflightu i weryfikacji (Moduły 6 i 7).
- **Operator Produkcji:** Widzi gigantyczny przycisk "Przejdź do Kanban" (Moduł 9) i swoje aktualne zadanie produkcyjne.
- **Księgowość:** Widzi powiadomienia o fakturach do wystawienia i potwierdzonych płatnościach (Moduł 14).
- **Klient (w przyszłości):** Widzi uproszczony podgląd swoich zamówień.

### C. Wzajemodzia z Modułem 8 (Communication Hub)
W `AppLayout.vue` znajduje się dzwonek powiadomień. Frontend subskrybuje kanały z użyciem Laravel Echo (Reverb). W przypadku zdarzenia (`NotificationSent`), licznik na dzwonku i w pasku bocznym (Sidebar) rośnie w czasie rzeczywistym, nie wymagając odświeżenia strony.

---

## 3. UI / UX Kreślenie (Blueprints)

### Blueprint: AppLayout.vue
- **Sidebar (Lewa strona, stały):**
  - **Logo / Nazwa firmy** na samej górze.
  - **Nawigacja (Menu):** Elementy menu filtrowane przez `can()`. Jeśli nie masz uprawnień do produkcji, zakładka "Produkcja" znika. Elementy nawigacji korzystają z ikon `lucide-vue-next`.
- **Topbar (Góra, lepki - sticky):**
  - Wyszukiwarka globalna (Commmand Palette / `Ctrl+K`).
  - **Dzwonek Powiadomień (`Bell`)** z licznikiem i menu dropdown.
  - **Menu Użytkownika (`DropdownMenu` z shadcn-vue):** Avatar -> Dropdown: Profil, Ustawienia, **Wyloguj (POST request przez Inertia `<Link as="button" method="post">`)**.
- **Content Area:** Główna strefa renderująca stronę z użyciem tła `bg-background` i kart `bg-card`.

### Blueprint: Dashboard.vue
- **Header:** Nagłówek "Cześć, [Imię]!" + ewentualnie data.
- **Siatka KPI (`Grid`):** Komponenty `Card` z shadcn-vue (np. `grid-cols-1 md:grid-cols-2 lg:grid-cols-4`).
- **Szybkie akcje:** W zależności od roli np. "Nowe Zamówienie" (`Button` z wariantem `default` lub `outline`).

---

## 4. Architektura i Konkretne Pliki do Wdrożenia

### A. Poprawki i Tuning Komponentu Logowania (`resources/js/Pages/Auth/Login.vue`)
Zgodnie z audytem kod musi zostać dopasowany do standardów `shadcn-vue` i `inertia-vue-development`:
- Zastosowanie natywnych dyrektyw dla komponentów (`v-model:checked="form.remember"` dla `Checkbox` zamiast łamanego eventu `@update:checked`).
- Użycie odpowiedniego `Label` połączonego semantycznie z Inputem.

### B. Stworzenie `resources/js/Composables/useAuth.js`
**Cel:** Reużywalny composable w Vue do autoryzacji RBAC po stronie frontendu.
```javascript
import { usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

export function useAuth() {
    const page = usePage()
    // Założenie: Backend przekazuje auth.user z polem 'role' lub listą 'permissions'
    const user = computed(() => page.props.auth?.user)
    
    const can = (permission) => {
        if (!user.value) return false
        if (user.value.role?.nazwa === 'Admin') return true
        return user.value.permissions?.includes(permission)
    }

    const hasRole = (roleName) => {
        if (!user.value) return false
        return user.value.role?.nazwa === roleName
    }

    return { user, can, hasRole }
}
```

### C. Refaktoryzacja `resources/js/Layouts/AppLayout.vue`
- Wdrożenie zintegrowanego `DropdownMenu` z pakietu `shadcn-vue` dla awatara użytkownika (do bezpiecznego wylogowywania w Inertii).
- Usunięcie hardcodowanych `size` przy ikonach (typu `w-5 h-5`), a zastosowanie `size-5` zgodnie ze stylem ewoluującym w Tailwind.
- Ukrycie odpowiednich linków nawigacyjnych (Produkcja, Klienci itp.) stosując funkcję `can()` (np. `v-if="can('view_production')"`).

### D. Wykonanie `resources/js/Pages/Dashboard.vue`
```vue
<script setup>
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Calculator } from 'lucide-vue-next'
import { useAuth } from '@/Composables/useAuth'

const { hasRole, user } = useAuth()
</script>

<template>
  <AppLayout>
    <Head title="Dashboard" />
    <div class="flex flex-col gap-6">
      <h1 class="text-3xl font-bold tracking-tight">Cześć, {{ user?.imie || user?.name }}!</h1>
      
      <div class="grid gap-4 grid-cols-1 md:grid-cols-2 lg:grid-cols-4">
        <!-- Karta KPI - widoczna dla Menedżerów -->
        <Card v-if="hasRole('Admin') || hasRole('Menedżer')">
          <CardHeader class="flex flex-row items-center justify-between pb-2 space-y-0">
            <CardTitle class="text-sm font-medium">Oczekujące wyceny</CardTitle>
            <Calculator class="size-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div class="text-2xl font-bold">5 zapytań</div>
          </CardContent>
        </Card>
      </div>
    </div>
  </AppLayout>
</template>
```

---

## 5. Podsumowanie Tuningów i Wymogów Biznesowych (Best Practices)

1. **Inertia.js POST Logout:** W `DropdownMenu` w `AppLayout.vue`, link wylogowujący **musi** korzystać z POST: 
   `<Link href="/logout" method="post" as="button" class="w-full text-left">Wyloguj się</Link>`. Zapobiega to omijaniu ochrony CSRF (Skill `inertia-vue-development`).
2. **Tailwind Flex & Grid Spacing:** W layoutach zamiast starych klas `space-x-` i `space-y-` stosujemy nowoczesne podejście z `flex` + `gap-*` lub `grid` + `gap-*` (Skill `tailwindcss-development`).
3. **Prawidłowe `v-model` dla shadcn-vue:** Stosowanie propów `v-model` (w tym specyficznie nazwanego `v-model:checked` np. dla switchy/checkboxów, które są natywnymi komponentami Vue), bez kombinowania z `@update:checked` i mutowania własnego stanu ręcznie.
4. **Rozszerzenie Middleware'u Backendowego:** W celu poprawnego działania frontendu należy zadbać o to, aby `HandleInertiaRequests` (po stronie Laravela) przekazywał poprawnie uprawnienia i obiekty `role` dla obiektu `user`. (Część Kroku 1.1.1, do zintegrowania).
5. **Lucide Icons:** Ikonki muszą posiadać zdefiniowane spójne rozmiary `size-*` oraz dla układów wewnątrz przycisków używamy `class="gap-2"` na `Button` komponentu, bez `mr-2` na samej ikonie.

**Gotowe.**
