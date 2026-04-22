# Plan Implementacji: Krok 1.2.4 — Wysyłka emaili [BE] & UI/UX

## 1. Cel i Zakres
Zbudowanie kompletnego, asynchronicznego mechanizmu wysyłki wiadomości e-mail w oparciu o zdefiniowane szablony HTML (Moduł 8: Communication Hub). Wdrożenie warstwy backendowej (`MailSender`, Eventy) oraz zaplanowanie zaawansowanego interfejsu (UI/UX) z wykorzystaniem wysuwanej szuflady (Sheet) z podglądem na żywo (Live Preview).

## 2. Architektura Backend (BE)

### 2.1. Parser Szablonów (`TemplateParser`)
- **Koncept:** Mechanizm robust token replacement wspierający zagnieżdżone relacje (np. `{{ $klient->imie }}`, `{{ $zamowienie->numer }}`).
- **Technologia:** Wykorzystanie wbudowanego kompilatora Blade (renderowanie stringów za pomocą `Blade::render()`). Zapewnia to potężne możliwości formatowania tekstu, wykorzystania helperów Laravela oraz dodawania warunków logicznych bezpośrednio w szablonach HTML tworzonych przez administratorów.
- **Zmienne (Context):** DTO (Data Transfer Object) przekazujące modele `Klient`, `Zamowienie` i inne wstrzyknięte dane.

### 2.2. Serwis Głównej Wysyłki (`MailSender`)
- **Odpowiedzialność:** Pobrać szablon (`SzablonWiadomosci`), wyciągnąć kontekst, przepuścić przez parser Blade, wygenerować ostateczny HTML i temat, po czym zlecić wysyłkę e-maila.
- **Asynchroniczność (Queueing):** Zgodnie z najlepszymi praktykami zadeklarowanymi dla tego projektu, wysyłka e-maili będzie całkowicie w tle. Należy utworzyć job `SendOutboundEmail` lub mailable z interfejsem `ShouldQueue`, aby nie blokować procesu HTTP (zapobiega to zacięciu się interfejsu w panelu operatora).
- **Pliki (Załączniki):** Jeżeli do wiadomości podpięte są obiekty `Plik` z MinIO (DAMS), serwis zadba o przekazanie odpowiednich URL-i z pre-signed dostępem do Mailable'a.

### 2.3. Event `EmailSent` i Zapis w Bazie
- Zamiast od razu pisać do bazy podczas requestu, utworzymy i zachowamy rekord w tabeli `wiadomosci` ze statusem `pending` od razu, po czym Job podczas działania zmodyfikuje jego status na `sent` lub `error`.
- Można wyemitować event (np. `MessageDelivered`), który złapie odpowiedni słuchacz (Listener) aktualizujący logi (np. ActivityLog).
- **Powiadomienia Real-time:** Zastosowanie Laravel Reverb wywoła event w broadcast channel, przez co każdy pracownik mający otwarty dany wątek zobaczy, że nowa wiadomość zmieniła status na "Wysłano", a na tablicy zamówienia zostanie uaktualniony Timeline.

## 3. Workflow UX i UI (Tuning i Kreślenie)

### 3.1. Kreślenie UI (Slide-out Drawer + Live Preview)
Zamiast tradycyjnego przejścia na nową stronę, proces komponowania nowej wiadomości zamkniemy w interfejsie nieprzerywającym obecnej czynności operatora. Do tego posłuży nam `Sheet` z `shadcn-vue` pojawiający się z prawej strony ekranu. 

**Układ wewnątrz Drawera (Sheet):**
- **Lewa Kolumna (Konfiguracja i Edycja):**
  - Wybór z bazy dostępnych `SzablonWiadomosci`.
  - Pola nadawcy ("Od") i odbiorcy ("Do", domyślnie zaciągnięte z klienta).
  - Sekcja załączników umożliwiająca odhaczenie checkboxami plików już powiązanych z zamówieniem oraz wgranie nowych (via uppy/tus.io).
  - Edytor tekstu WYSIWYG (np. wbudowany i skonfigurowany pod rygorystyczne stylowanie Tailwind Typography), do którego ląduje treść szablonu wygenerowana przez parser. Pozwala to na personalizację przed kliknięciem "Wyślij".
- **Prawa Kolumna (Live Preview - Podgląd na żywo):**
  - Obszar prezentujący rzeczywisty wygląd końcowego e-maila jako pełny HTML. Zawiera makiety marginesów i logotypu, gwarantując spójny branding. Zmiany dokonywane po lewej stronie płynnie, w czasie rzeczywistym aktualizują widok po prawej.

### 3.2. Scenariusz UX Użytkownika (End-to-End):
1. **Trigger:** Operator będąc na karcie `/orders/{id}` klika "Wyślij Wiadomość". Otwiera się Drawer (Sheet).
2. **Rozpoczęcie:** Operator wybiera z dropdowna "Faktura Zaliczkowa". Poniżej wyboru następuje natychmiastowe zaciągnięcie treści via API z wstrzyknięciem odpowiednich wartości (`Blade::render`).
3. **Personalizacja:** Edytor po lewej wypełnia się wstępnie zredagowaną wiadomością. Operator dodaje linijkę "Pozdrawiam serdecznie, Jan". Podgląd w czasie rzeczywistym po prawej renderuje dokładnie taki wynik, jaki zobaczy klient.
4. **Wysyłka:** Kliknięcie klawisza "Wyślij".
5. **Wynik:** Szuflada zjeżdża, wyświetla się powiadomienie (Toast): "Wiadomość została zakolejkowana do wysyłki". W oknie "Timeline" dla zamówienia ląduje kafelek o trwającej operacji.
6. Po kilkunastu sekundach (kiedy zrealizuje się worker), za pomocą Reverba status przeskakuje na "Dostarczono", uaktualniając kolory w UI z szarego na zielony.

## 4. Kros-modułowa Interakcja 
- **Orders (Moduł 3) i CRM (Moduł 2):** Modele te (z pełnym eager loadingiem stosując `with()`) będą rzutowane na zmienne środowiska szablonów, z których parser wyciągnie dane niezbędne do komunikacji. Należy unikać problemów N+1 dostarczając odpowiednio dograne złączenia przed renderowaniem.
- **Communication Hub (Moduł 8) a Workflow:** Możliwość utworzenia w module wysyłki dedykowanych triggerów, np. po wysyłce "Wiadomości o brakujących plikach" następuje automatyczna dyspozycja zmiany statusu z `PRICING` na `WAITING_FILES` poprzez wywołanie Transition modelu stanu zamówienia z pakietu `spatie/laravel-model-states`.

## 5. Implementacja Krok po Kroku

### Backend (Laravel 11.x, PHP 8.3)
1. Stworzyć kontroler typu Single-Action wywołujący serwis generujący Preview.
2. Zaimplementować klasę `app/Services/MailSender.php` posiadającą metodę `composeAndSend(WatekKomunikacji $watek, SzablonWiadomosci $szablon, array $dodatkowyKontekst)`.
3. Utworzyć klasę Job: `php artisan make:job SendOutboundEmail` zdefiniowaną na próbę odświeżania (`$tries = 3`, tryby retry exponential). 
4. Dostosować mailable `app/Mail/OutboundCommunication.php` na bazie pliku Blade i powiązać z zaktualizowaniem rekordu bazy `wiadomosci` poprzez callback `failed` lub listener.

### Frontend (Vue 3, Inertia, shadcn-vue)
1. Utworzyć komponent `resources/js/Components/Messages/ComposeMessageSheet.vue`. Pamiętać o regułach shadcn-vue: unikać zbędnych `<div>` oraz używać `v-model` zgodnych ze stylem vue3.
2. Wykorzystać `useForm()` Inertii do zebrania edytowanych treści i zarządzania cyklem żądania `form.post()`.
3. Upewnić się o poprawnej dekonstrukcji błędu - renderowanie wszelkich problemów (np. puste pola wymagane) bezpośrednio przez `FormMessage` z pakietu formularzy shadcn.

## 6. Tuning Biznesowy
- **Logika Niezawodności:** Wdrożenie zasady, wg której Job obsługujący wysyłkę w przypadku `failed()` musi zawsze zmienić status wiadomości na `error` (dzięki temu użytkownicy wiedzą, że mail nie wyszedł) oraz zgłosić błąd na konsoli z logiem (`logger()->error(...)`).
- **Zapobieganie Dublowaniu:** Implementacja w obiekcie Mailable lub Job klucza prewencji do zapobiegnięcia wielokrotnym wysyłkom tego samego żądania.
- **Zabezpieczenie Danych:** Szablony konfigurowalne przez administratora korzystające z `Blade::render()` są potężne, w związku z czym dostęp do ich modyfikacji w UI panelu administracyjnego musi być pod ścisłym RBAC (`CheckPermission` z `spatie/laravel-permission`).