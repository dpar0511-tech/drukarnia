# Plan Implementacji: Krok 1.2.7 — Frontend Inbox [FE]

Ten dokument przedstawia szczegółowy plan implementacji dla interfejsu użytkownika (Frontend) modułu Inbox w aplikacji Drukarnia ERP (Moduł 8: Communication Hub).

## 1. Założenia UI/UX (Blueprint) i Workflow Użytkowników

### Cel
Stworzenie scentralizowanego huba komunikacyjnego, który przypomina nowoczesne klienty poczty (np. Superhuman, Linear Inbox), co pozwoli menedżerom na błyskawiczne procesowanie wiadomości bez przeładowania kontekstu.

### Główne Role
- **Menedżer / Admin**: Szybka obsługa zapytań klientów, zamiana zapytań mailowych na zamówienia, komunikacja w trakcie realizacji (np. braki w plikach).

### Klasyczny Dwukolumnowy Układ (Master-Detail)
Zamiast tradycyjnego przełączania się między widokami `Index.vue` a `Show.vue`, zastosujemy hybrydowe rozwiązanie przypominające układ nowoczesnej skrzynki email:
1. **Lewa Kolumna (Lista)**: Zawsze widoczna, przewijana lista wątków (komponent `ScrollArea` z shadcn-vue).
2. **Prawa Kolumna (Szczegóły)**: Konkretny, otwarty wątek z historią konwersacji (czat/email thread) oraz polem szybkiej odpowiedzi przypiętym na dole. W przypadku braku wybranego wątku (np. na `/inbox`) – elegancki "Empty State".

*Technikalia:* Aby uzyskać ten układ w sposób wydajny w Inertia.js, upewnimy się, że stan z listą wątków (`$threads`) jest przekazywany zarówno w akcji `index`, jak i `show` kontrolera (lub wykorzystamy komponenty współdzielone/Persistent Layouts), tak by przełączanie się między szczegółami aktualizowało prawą część, bez gubienia listy z lewej.

### Przepływ Użytkownika (Workflow)
1. **Odbiór (Triage)**: Użytkownik wchodzi na `/inbox`. Z lewej strony posiada listę aktualnych wiadomości (posortowanych od najnowszych). Wątki nieprzeczytane są wyróżnione.
2. **Podgląd (Context)**: Kliknięcie na dany wątek korzysta z opcji `prefetch` z Inertii (`<Link prefetch>`). Prawa strona błyskawicznie wypełnia się zawartością wątku, pokazując u góry podsumowanie dotyczące klienta i/lub zamówienia.
3. **Akcja i Decyzja (Kros-modułowa)**: 
   - Jeśli mail to zapytanie o wycenę → Menedżer widzi przycisk "Utwórz zamówienie" obok klienta.
   - Jeśli to aktualizacja plików → Menedżer przechodzi z linku prosto do DAMS zamówienia.
4. **Odpowiedź**: W celu dopytania szczegółów (np. "proszę o poprawny plik w CMYK"), menedżer używa formularza `MessageForm.vue` z obsługą drag-and-drop dla załączników.

---

## 2. Kros-Modułowa Interakcja

1. **Client Management (CRM - Moduł 2)**:
   - Część prawa konwersacji wyświetla wizytówkę klienta (Imię, Nazwisko, NIP, tagi oraz Poziom Lojalności).
   - Jeśli system nie zmapuje maila do istniejącego klienta ("Unknown Sender"), obok widnieje szybki przycisk "Utwórz klienta" wprost z widoku wiadomości.
2. **Order Management (Moduł 3)**:
   - Wyświetlenie statusu i numeru powiązanego zamówienia w nagłówku wątku (np. jako badge: `DRK-2026-00001` - kolor badge'a zależny od stanu, np. zielony `PRODUCTION`).
   - Szybki link: "Przejdź do karty zamówienia".
3. **DAMS (Document & Asset Management - Moduł 5)**:
   - Załączniki od klienta w ramach maila renderują się jako zgrabne `FileCard` zawierające rozmiar, nazwę i wygenerowaną miniaturkę z S3 MinIO.
   - Komponent dodawania nowych załączników jest bezpośrednio połączony z głównym modułem UploadWidget (wspierającym m.in. tus.io dla wznawiania wysyłania dużych projektów graficznych).
4. **Communication Hub (WebSockets / Polling - Moduł 8)**:
   - Użycie kompozytowego hook'a Inertii `usePoll(10000, { only: ['threads'] })` do lekkiego, okresowego sprawdzania czy nie nadeszły nowe wiadomości, bez pełnego przeładowywania interfejsu (to sprawi wrażenie działania Real-Time jeszcze przed docelową implementacją Laravel Reverb).

---

## 3. Tuning Wyglądu i Biznes Logiki (Frontend Design / Tailwind)

- **Shadcn-vue jako Serce UI**:
  - `ScrollArea` do estetycznego zarządzania długimi rozmowami i listami.
  - `Badge` (często własnie wariantu `outline` lub `secondary`) do dyskretnego komunikowania liczników powiadomień lub statusów.
  - `Avatar` przy każdej wiadomości (dla klienta oraz dla pracownika odpowiadającego z systemu).
  - `Card` z łagodnymi zaokrągleniami (`rounded-xl` lub `rounded-2xl`) chroniące bloki tekstu.
- **Komponent Odpowiedzi (`MessageForm.vue`)**:
  - Wykorzystanie samo-rozszerzającego się `Textarea`.
  - Spójne umiejscowienie narzędzi (Ikona "Spinacza" do dodawania załączników po lewej, "Wyślij" po prawej) wewnątrz samego boxa wpisywania (wygląd zbliżony do iMessage lub nowszych systemów ticketowych). Przycisk wysyłania wykorzysta `processing` state z Inertia `<Form>`.
- **Elegancja i Dark Mode**:
  - Pełne wsparcie ciemnego trybu (dark mode). Użycie zmiennych CSS np. `bg-background`, `bg-card`, `text-muted-foreground`.
  - Rezygnacja z ostrych krawędzi. Stawiamy na "Soft" wygląd pasujący do profesjonalnych a jednocześnie nowoczesnych narzędzi SaaS. Tło strony nieco szare (`bg-muted/30`), a elementy czytelne białe (`bg-card`). Odstępy kontrolowane za pomocą `gap-*` (nigdy `space-y-*`).

---

## 4. Szczegółowe Kroki Techniczne (Wymagane Pliki)

### Etap A: Przygotowanie Struktury Layoutu (Inertia + Vue)
1. **Stworzenie/Modyfikacja `resources/js/Pages/Inbox/Index.vue`**:
   - Skonstruowanie układu siatki (np. `grid grid-cols-12 h-[calc(100vh-4rem)]`).
   - Lewa kolumna (span 4): komponent z listą.
   - Prawa kolumna (span 8): grafika informująca "Wybierz wątek po lewej aby przeczytać".
2. **Stworzenie `resources/js/Pages/Inbox/Show.vue`**:
   - Układ identyczny jak wyżej, lecz po prawej stronie wypełniony otwartą konwersacją i polem do odpisywania. Zapewnia to perspektywę płynnego działania aplikacji typu "PWA/SPA".
3. **Optymalizacja `InboxController.php` (Backend)**:
   - Aby uzyskać bezstratne przejścia między `Index` a `Show` przy jednoczesnym braku stałego Layoutu (persistent layout), modyfikujemy metodę `show()`, aby również przekazywała zmienną `$threads` (listę) wykorzystywaną po lewej stronie, oraz `$watek` po prawej.

### Etap B: Budowa Komponentów (Vue 3 + shadcn-vue)
4. **`resources/js/Components/Inbox/InboxThreadList.vue`**:
   - Komponent izolujący pętlę listy wątków.
   - Wykorzystanie `<Link prefetch>` ułatwiające wstępne ładowanie zawartości tuż po wejściu kursorem myszy na link. Oznaczenie nieprzeczytanych ("wytłuszczenie" + indykator punktowy, kolor `bg-primary/5`).
5. **`resources/js/Components/Inbox/MessageBubble.vue`**:
   - Pojedynczy "bąbel" wypowiedzi. Zrozumienie pola `kierunek` (`przychodzacy`/`wychodzacy`).
   - Prawa flanka (nasze odpowiedzi), lewa flanka (wiadomości z zewnątrz).
   - Renderowanie bezpiecznego HTML dla `tresc_html` z zachowaniem formatowań.
6. **`resources/js/Components/Inbox/MessageForm.vue`**:
   - Skomponowanie z komponentem formularza z Inertii (`<Form>`), wykorzystanie `resetOnSuccess` z Inertia, by błyskawicznie "wyczyścić" pole po poprawnym dodaniu.
   - Walidacja wpisywania (wyłączenie przycisku, gdy brak tekstu/załącznika).

### Etap C: Real-Time Polling (UX/Reverb)
7. **`usePoll()` w komponencie nadrzędnym**:
   - Wdrożenie asynchronicznego sprawdzania w tle nowych maili z skrzynki (z wykorzystaniem Inertia `usePoll(10000)` dla zmiennej `threads`), ułatwiające sprawne podejmowanie decyzji o akcjach bez odświeżania strony.

## 5. Następne Działania (Podsumowanie Executable)
1. **(Wykonawczo)**: Edycja `InboxController.php` po stronie back-endowej.
2. **(Wykonawczo)**: Uruchomienie z CLI instalacji komponentów bazowych (`npx shadcn-vue@latest add scroll-area avatar separator badge`).
3. **(Wykonawczo)**: Wdrożenie kodu trzech kluczowych komponentów i dwóch stron.