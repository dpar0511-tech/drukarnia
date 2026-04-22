# Krok 1.2.6 — In-App Notifications [BE] [FE]

## 🎯 Cel
Wdrożenie globalnego systemu powiadomień "in-app" w czasie rzeczywistym. Moduł zapewni błyskawiczną komunikację pomiędzy zdarzeniami systemu (takimi jak nowe wiadomości od klienta, zmiana statusu zamówienia) a interfejsem użytkownika. Wykorzystamy mechanizm Laravel Reverb + Echo oraz komponenty interfejsu z rodziny shadcn-vue.

## 👥 UX / UI Workflow & Kros-modułowa interakcja

1. **Źródło zdarzenia (Cross-Module):** Moduł np. Komunikacji (odebranie e-maila) używa metody `NotificationService::send()`. System rejestruje notyfikację w tabeli `powiadomienia` i asynchronicznie emituje event `NewNotification` (`ShouldBroadcast`).
2. **Odbiór Real-time:** Podłączony na froncie klient za pomocą Vue composable `useNotifications()` odbiera event z kanału `private-user.{id}` bez odświeżania strony. 
3. **Akcja UI (Toaster):** Zostaje wywołany toast (shadcn `Sonner`), informujący użytkownika o np. "Nowa wiadomość od Jan Kowalski". 
4. **Aktualizacja licznika:** Licznik na dzwonku w Topbarze automatycznie zostaje zinkrementowany (+1) bez konieczności pobierania pełnej listy. Ikona Bell w topbarze dostaje "kropkę" powiadomień.
5. **Panel powiadomień (Popover):** Po kliknięciu na ikonę Dzwonka otwiera się elegancki Popover (z użyciem shadcn `ScrollArea`), w którym użytkownik widzi do np. 10 ostatnich powiadomień bez przechodzenia na dedykowaną stronę (co mogłoby zepsuć aktualny kontekst pracy - np. podczas ustawiania cen).
6. **Oznaczanie jako przeczytane:** Kliknięcie w dane powiadomienie oznacza je jako przeczytane (żądanie asynchroniczne `/api/notifications/{id}/mark-as-read`) i przekierowuje użytkownika pod wyznaczony `link` za pomocą `router.visit()`.
7. **Skrót akcji:** Możliwość oznaczenia wszystkich jako przeczytane przyciskiem "Oznacz wszystkie" na liście Popover.

## 🛠️ Tuning wyglądu i logiki biznesowej (Best Practices)
* Użycie shadcn-vue `Popover` do wyświetlania notyfikacji zamiast wymuszania nawigacji pod adres `/notifications`. Zwiększa to produktywność użytkowników.
* Stylizacja: Użycie `ScrollArea` do dłuższego contentu, pogrubiona czcionka (wspomagana odcieniem tła np. `bg-muted/50`) dla nieprzeczytanych powiadomień.
* Podpięcie globalnego zapytania o początkowy stan nieprzeczytanych elementów w `HandleInertiaRequests` (żeby od razu podczas loadu strony UI wiedziało czy dzwonek ma pokazywać cyfrę).

---

## 👨‍💻 Implementacja: Backend (Laravel 11)

### 1. `App\Events\NewNotification` (Nowy event dla Broadscastu)
Wykorzystanie wiedzy ze skilla `echo-development` - tworzymy dedykowany event używając kolejki by nie blokować response:
```php
namespace App\Events;

use App\Models\Powiadomienie;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class NewNotification implements ShouldBroadcast
{
    use InteractsWithSockets, SerializesModels;

    public function __construct(public Powiadomienie $powiadomienie) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('user.'.$this->powiadomienie->user_id)];
    }
}
```

### 2. Konfiguracja Autoryzacji Kanału
W pliku `routes/channels.php` upewniamy się, że użytkownik może nasłuchiwać swoich notyfikacji:
```php
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('user.{id}', function (User $user, int $id) {
    return (int) $user->id === $id;
});
```

### 3. Poprawa i Ustandaryzowanie `NotificationService`
Modyfikujemy `App\Services\Communication\NotificationService`, by był bardziej uniwersalny i od razu wysyłał event `NewNotification` (zamiast polegać na ubocznych eventach).
```php
public function send(User $user, string $typ, string $tytul, string $tresc, ?string $link = null): Powiadomienie
{
    $powiadomienie = Powiadomienie::create([
        'user_id' => $user->id,
        'typ' => $typ,
        'tytul' => $tytul,
        'tresc' => $tresc,
        'link' => $link,
        'przeczytane' => false,
    ]);

    // Wysłanie eventu po stronie Queue (Laravel Reverb / Echo)
    NewNotification::dispatch($powiadomienie);

    return $powiadomienie;
}
```

### 4. Globalny prop w `HandleInertiaRequests`
W `app/Http/Middleware/HandleInertiaRequests.php` wrzucamy inicjalną wartość i krótką listę:
```php
public function share(Request $request): array
{
    return array_merge(parent::share($request), [
        'auth' => [
            'user' => $request->user(),
        ],
        'notificationsCount' => $request->user() ? $request->user()->powiadomienia()->unread()->count() : 0,
        // Ewentualnie top 5 do dropdowna by nie robić oddzielnego requestu
    ]);
}
```

---

## 🎨 Implementacja: Frontend (Vue 3 + Tailwind + shadcn)

### 1. Composable `useNotifications.js`
Plik `resources/js/Composables/useNotifications.js` odpowiedzialny za nasłuchiwanie Echo i zarządzanie lokalnym stanem.
```javascript
import { ref, onMounted, onUnmounted } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { toast } from 'vue-sonner'

export function useNotifications() {
  const page = usePage()
  const unreadCount = ref(page.props.notificationsCount || 0)

  // Metoda nasłuchiwania w cyklu życia (onMounted)
  const initEcho = () => {
    if (!page.props.auth?.user) return;
    
    window.Echo.private(`user.${page.props.auth.user.id}`)
      .listen('NewNotification', (e) => {
          // Toast Notification (real-time feedback)
          toast(e.powiadomienie.tytul, {
              description: e.powiadomienie.tresc,
              action: e.powiadomienie.link ? {
                  label: 'Zobacz',
                  onClick: () => {
                     // Wykorzystanie inercji
                     router.visit(e.powiadomienie.link)
                  }
              } : undefined
          })
          
          // Zwiększ licznik bez przeładowywania
          unreadCount.value++;
      });
  }

  return {
    unreadCount,
    initEcho
  }
}
```

### 2. Utworzenie komponentu `NotificationBell.vue`
W pliku `resources/js/Components/NotificationBell.vue`:
Zastąpimy nim dotychczasowy przycisk powiadomień w `AppLayout.vue`.
Zależności z shadcn-vue (zapewne będą musiały być zainstalowane `popover`, `scroll-area` z cli: `npx shadcn-vue@latest add popover scroll-area`).
```vue
<script setup>
import { Bell, Check } from 'lucide-vue-next'
import { Popover, PopoverContent, PopoverTrigger } from '@/Components/ui/popover'
import { ScrollArea } from '@/Components/ui/scroll-area'
import { Button } from '@/Components/ui/button'
import { useNotifications } from '@/Composables/useNotifications'
import { onMounted } from 'vue'

const { unreadCount, initEcho } = useNotifications()

onMounted(() => {
    initEcho()
})
// ... W tym komponencie logika do asynchronicznego pobierania / odczytywania Axios-em list i zmiany stanu
</script>

<template>
  <Popover>
    <PopoverTrigger as-child>
      <Button variant="ghost" size="icon" class="relative rounded-full">
        <Bell class="size-5 text-muted-foreground" />
        <span v-if="unreadCount > 0" class="absolute top-1.5 right-1.5 size-2 bg-destructive rounded-full border-2 border-background"></span>
      </Button>
    </PopoverTrigger>
    <PopoverContent class="w-80 p-0" align="end">
        <div class="flex items-center justify-between px-4 py-2 border-b">
            <h4 class="font-semibold text-sm">Powiadomienia</h4>
            <Button variant="ghost" size="sm" class="h-auto p-1 text-xs" @click="markAllRead">
                <Check class="size-3 mr-1"/> Oznacz jako przeczytane
            </Button>
        </div>
        <ScrollArea class="h-72">
            <!-- Pętla na notyfikacje v-for... -->
        </ScrollArea>
        <div class="border-t p-2 text-center">
            <!-- Opcjonalny link do archiwum / wszystich notyfikacji -->
        </div>
    </PopoverContent>
  </Popover>
</template>
```

### 3. Wpięcie w `AppLayout.vue`
Aktualizujemy `resources/js/Layouts/AppLayout.vue` wymieniając link na nasz nowy widget:
```vue
<!-- Przed zmianą -->
<Link href="/notifications" class="relative p-2 rounded-full hover:bg-muted transition-colors">
   <Bell class="size-5 text-muted-foreground" />
   <span v-if="notificationsCount > 0" class="absolute top-1.5 right-1.5 size-2 bg-destructive rounded-full border-2 border-background"></span>
</Link>

<!-- Po zmianie -->
<NotificationBell />
```

## 🧪 Wymagania testowania
1. Zakończyć działanie serwerów po implementacji, a następnie uruchomić:
   - `php artisan queue:work`
   - `php artisan reverb:start`
   - Otworzyć aplikację na dwóch oknach/przeglądarkach, aby potwierdzić działanie Echo.
2. Zastosować w kontrolerze z modułu 8 (np. przy otrzymywaniu wiadomości) wywołanie klasy serwisu do wysłania notyfikacji i sprawdzić widok toasta w UI.
3. Wykonać unit/feature testy asercji `Event::assertDispatched(NewNotification::class)`.