# Plan Implementacji: Krok 1.2.3 — Email Parser [BE]

## 1. Cel Kroku (Objective)
Zaimplementowanie parsera przychodzących e-maili (przekazywanych np. przez Job z kroku 1.2.2), który automatycznie przypisuje wiadomości do odpowiednich wątków, rozpoznaje lub tworzy "draft" klienta, zapisuje załączniki oraz powiadamia użytkowników o nowej aktywności.

## 2. Architektura i Logika Biznesowa (Business Logic)

### 2.1 Struktura Klas (Zgodnie z Laravel Best Practices)
W celu odciążenia głównej klasy Joba, logikę podzielono na wąsko wyspecjalizowane serwisy (Single Responsibility):

- **`app/Jobs/Communication/ProcessIncomingEmail.php`**: Główny job w kolejce. Orkiestruje przepływ danych, transakcje bazodanowe i powiadomienia.
- **`app/Services/Communication/EmailParserService.php`**: Czyści treść maila. (np. wykorzystując paczki do analizy tagów HTML i usuwania historii konwersacji cytowanej poniżej nowej odpowiedzi).
- **`app/Services/Communication/ClientMatcherService.php`**: Odpowiada za dopasowanie klienta na podstawie e-maila:
  1. Szukaj adresu e-mail w tabeli `klienci` oraz powiązanych `osoby_kontaktowe`.
  2. Jeśli brak bezpośredniego dopasowania, weryfikuj temat (np. Regex na numer zamówienia `DRK-\d{4}-\d{5}`).
  3. Jeśli nadal brak: tworzy w locie Klienta ze statusem "oczekujący/draft" lub generuje powiadomienie do Menedżera w celu ręcznej weryfikacji (eliminacja zaginionych leadów).
- **`app/Services/Communication/ThreadManagerService.php`**: Szuka `WatekKomunikacji` na podstawie np. nagłówków `In-Reply-To` lub numeru zamówienia. Jeśli nie ma, otwiera nowy `Watek`.
- **`app/Services/DAMS/AttachmentHandlerService.php`**: Wyciąga załączniki i przekierowuje je do systemu plików zdefiniowanego w Module 5. Rejestruje rekord `Plik` i relację polimorficzną do nowej encji `Wiadomosc`.

### 2.2 Kros-Modulna Interakcja
- **Moduł 2 (CRM)**: Tworzenie klientów, odpytywanie bazy.
- **Moduł 5 (DAMS)**: Magazynowanie załączników na MinIO.
- **Moduł 1 (Core)**: Wyemitowanie zdarzenia domenowego `App\Events\Communication\EmailReceived`.
- **Moduł 8 (Communication Hub)**: Rozgłaszanie w czasie rzeczywistym zdarzeń z wykorzystaniem Laravel Reverb.
- **Moduł 12 (AI Layer)**: Hook. W Jobie pozostawiamy dedykowane miejsce (np. wywołanie pustego `ExtractBriefWithAI::dispatch()`), pod które podepniemy się w fazie 4.1 do analizy wiadomości przez OpenAI.

## 3. Workflow UX/UI (User Experience & Interface)

### 3.1 Natychmiastowe Powiadomienia (WebSocket)
- Zdarzenie `EmailReceived` jest zdefiniowane jako `ShouldBroadcast`.
- **UI (shadcn-vue)**: Komponent globalny (np. `AppLayout.vue`) subskrybuje prywatny kanał Reverb (np. dla menedżerów: `App.Models.User.{id}`). Kiedy wiadomość trafia do systemu, ikona "dzwonka" się aktualizuje, a na ekranie wyskakuje Toast (powiadomienie `vue-sonner`): "Nowa wiadomość od: [Nadawca] - [Temat]".

### 3.2 Zarządzanie Niesparowanymi Wątkami (Inbox)
- **Tuning Biznesowy i UI**: Jeżeli `ClientMatcherService` nie rozpoznał jednoznacznie klienta (utworzył draft), wiadomość wpada do skrzynki (Inbox) ze specjalnym tagiem/badge'em "Wymaga uwagi" (`Badge` z shadcn-vue w kolorze `destructive` lub `warning`).
- Użytkownik widzi po wejściu w wątek powiadomienie systemowe: "Nie odnaleziono klienta w bazie". UI daje dwa szybkie przyciski wywołujące modal (`Dialog` / `Command`): "Połącz z obecnym klientem" lub "Konwertuj na Nowego Klienta B2B/B2C".

### 3.3 Widok Konwersacji (Thread View)
- Ujednolicamy widok: struktura na wzór systemów biletowych/komunikatorów z zachowaniem konwencji projektowych (komponenty `Card`, `ScrollArea`, awatary z `Avatar`). Załączniki konwertują się na eleganckie karty plików (`FileCard`) odwołujące się do miniatur DAMS.

## 4. Etapy Implementacji

1. **Generowanie Zdarzeń i Listeners:**
   `php artisan make:event Communication/EmailReceived`
   Przygotowanie payload'u - obiekt z nowo wstawionym wpisem `Wiadomosc` (oraz z wgranymi relacjami).
2. **Implementacja Logiki Biznesowej (Serwisów):**
   Budowa klas w `app/Services/Communication/` z uwzględnieniem Solidnych interfejsów (DI).
3. **Stworzenie i Konfiguracja Joba `ProcessIncomingEmail`:**
   Przyjęcie struktury DTO (Data Transfer Object) reprezentującej przychodzący surowy mail.
   Użycie `DB::transaction()`, by proces budowy relacji (Wątek -> Wiadomość -> Załączniki -> Opcjonalny Draft Klienta) powodził się tylko w całości lub zgłaszał wyjątek do `Failed Job`.
4. **Uruchomienie Broadcastingu:**
   Dodanie `InteractsWithSockets` i `ShouldBroadcast` we wspomnianym evencie domenowym.
5. **Przygotowanie UI (Front-end Vue):**
   Przygotowanie Store/Composable do logiki WebSockets dla inboxa (`useNotifications.ts`). Dodanie layoutu do nieprzypisanych wątków.

## 5. Weryfikacja i Testy

**TDD / Weryfikacja programistyczna:**
- `ClientMatcherServiceTest`: Assercje dla zapytań (dopasowanie pełne, powiązane z numerem zamówienia, przypadek skrajny skutkujący draftem).
- `ProcessIncomingEmailTest` (Feature): Przekazanie sztucznego mocka maila -> weryfikacja poprzez `assertDatabaseHas` utworzenia wątku, wiadomości i poprawnego zakolejkowania testowego zrzutu do DAMS.
- Fake na zdarzenia: Sprawdzenie, czy po zakończeniu transakcji `Event::assertDispatched(EmailReceived::class)` zostaje wysłany.
- Testowanie Queue: Oznaczenie joba atrybutem unikalności, by przypadkowy podwójny call tego samego message ID nie dublował rekordów w bazie.