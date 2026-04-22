# Krok 1.2.8 — Testy Modułu Komunikacji (Communication Hub)

## 1. Cel i Zakres
Zapewnienie niezawodności importu e-maili (IMAP), parsowania wiadomości, deduplikacji, a także poprawnego działania mechanizmów powiadomień (WebSocket) i wyświetlania ich w interfejsie użytkownika. Jest to zwieńczenie Etapu 1.2, zapewniające jakość fundamentalnego procesu komunikacji z klientem.

## 2. Architektura Testów (Zgodnie z Laravel Best Practices)
- **Baza danych**: Wykorzystanie traitu `LazilyRefreshDatabase` zamiast `RefreshDatabase` dla znacznej optymalizacji czasu wykonywania testów.
- **Mockowanie i Faking**:
  - Utworzenie dedykowanego interfejsu `ImapClientInterface` i użycie bindowania w kontenerze IoC. W testach jednostkowych wykorzystamy mock, aby nie nawiązywać prawdziwych połączeń z serwerem poczty.
  - Faking eventów i kolejek (`Event::fake()`, `Queue::fake()`, `Notification::fake()`), aby sprawdzić logikę dyspozycji zadań bez ich fizycznego wykonywania.
  - Mockowanie dysku S3 (MinIO) za pomocą `Storage::fake('minio')` przy testowaniu załączników.
- **Fabryki (Factories)**: Wykorzystanie klas fabryk z metodą `recycle()` (współdzielenie rekordu klienta i wątku) oraz stanów (`states`), np. `withAttachments`, `unread`, `htmlBody`.

## 3. Szczegółowy Plan Implementacji Testów

### 3.1. Testy Jednostkowe (Unit): `ImapMailImporterTest`
- **Cel**: Weryfikacja samej warstwy komunikacji z serwerem i parsowania surowych maili do struktury DTO (Data Transfer Object).
- **Przypadki testowe**:
  1. **Success Flow**: Metoda `fetchUnread()` zwraca tablicę DTO (nadawca, temat, html, plain text, ID zewnętrze).
  2. **Błędy połączeń**: Symulacja timeoutu serwera (rzucenie `ImapConnectionException`) – upewnienie się, że Job loguje błąd i korzysta z polityki "Exponential Backoff" do ponowień.
  3. **Empty Box**: Poprawna obsługa braku nowych wiadomości.
  4. **Odrzucanie duplikatów**: Weryfikacja czy w locie sprawdzany jest `zewnetrzny_message_id` by zapobiec podwójnemu procesowaniu.

### 3.2. Testy Funkcjonalne (Feature): Workflow "New email → Create wiadomosc"
- **Cel**: Symulacja End-to-End procesu odbierania poczty i integrowania jej w systemie ERP.
- **Przypadki testowe**:
  1. **Nowy Klient**: E-mail z adresu, którego nie ma w tabeli `klienci` → Automatyczne utworzenie rekordu ze statusem "draft", stworzenie nowego wątku (`watek_komunikacji`) i wiadomości. Powiadomienie Menedżera.
  2. **Rozpoznanie Klienta**: E-mail ze znanego adresu → Podpięcie do istniejącego klienta.
  3. **Wiązanie Wątków i Zamówień**: Jeśli w temacie lub nagłówku e-maila `In-Reply-To` znajduje się identyfikator (np. numer zamówienia DRK-YYYY-XXXXX) → przypisanie wiadomości do istniejącego wątku i/lub zamówienia.
  4. **Real-time Eventy (Reverb)**: Assert, że po zapisaniu zdispatchowano event `MessageReceived`, na który frontend nasłuchuje przez WebSockety.
  5. **Przetwarzanie Załączników**: Zmockowanie `ProcessUploadedFile` i sprawdzenie, czy powiązano poprawnie tabelę `pliki` (polimorficzna relacja `powiazania_plikow` jako `rola = dokument/artwork`).

## 4. Kros-modulna Wzajemność (Cross-module interaction)
- **Moduł 8 (Comm Hub) ↔ Moduł 2 (CRM)**: Testy muszą weryfikować poprawną kreację (lub aktualizację) klienta. Jeśli mail przyjdzie od klienta o statusie `zablokowany`, wątek powinien zostać oflagowany i nie generować powiadomień priorytetowych.
- **Moduł 8 (Comm Hub) ↔ Moduł 3 (Order Management)**: Parser musi skanować temat przy użyciu wyrażeń regularnych szukając wzorca `DRK-\d{4}-\d{5}`. Po jego znalezieniu wiąże e-mail prosto z kartą danego zamówienia.
- **Moduł 8 (Comm Hub) ↔ Moduł 5 (DAMS)**: Przekazywanie strumieni załączników z e-maila wprost do menedżera S3 z pominięciem lokalnego dysku (streaming chunkowy).

## 5. Workflow Użytkowników (UX/UI) & Kreslenie UI
Choć to etap tworzenia backendowych testów, upewniają one nas w tym, że procesy wspierające front-end będą stabilne i wyzwalane bez lagów.

### User Experience (UX) Flow
1. **Początek**: Menedżer pracuje w panelu (jakakolwiek zakładka ERP).
2. **Action w Tle**: System w tle (co 2 minuty cron job) odbiera maila od agencji reklamowej.
3. **Reakcja Systemu (Push)**: Za pomocą eventu `MessageReceived` wypychanego do Laravel Reverb (WebSocket), przeglądarka menedżera odbiera sygnał.
4. **Wizualizacja UI**:
   - Ikonka "Dzwoneczka" (Bell Icon) w górnym Navbarze delikatnie animuje się (pulsowanie) z powiadomieniem +1.
   - Jeśli menedżer ma otwartą zakładkę `/inbox`, nowy wątek przeskakuje na samą górę listy płynną animacją (Vue TransitionGroup), podświetla się na krótko (efekt flash - `bg-primary/10`).

### Szkic UI (Mental Model / Wireframe dla Modułu Inbox)
- **Layout**: Układ dwóch kolumn (Sidebar po lewej, Treść wiadomości po prawej).
- **Komponenty shadcn-vue**:
  - Lista: Użycie `<ScrollArea>` z kartami wiadomości.
  - Ikony: Z wykorzystaniem paczki `lucide-vue-next` (np. Ikona spinacza dla załączników).
  - Detal wiadomości: Zastosowanie `<Card>` oraz `<Avatar>` jako fallback z dwiema pierwszymi literami nazwy nadawcy klienta.
  - Odpowiadanie: `<Textarea>` z wbudowanym minimalistycznym narzędziem do ostylowania formatów (Markdown/RichText) przyciskające się do dołu kolumny.

## 6. Tuning Wyglądu i Biznes Logiki
Podczas planowania testów zidentyfikowaliśmy punkty krytyczne do poprawy (tuningu):

### Tuning Biznes Logiki
- **Ochrona przed pętlami autoresponderów (Anti-Looping)**: Częsty problem drukarni cyfrowych. Należy dodać warunek, że jeśli przychodzi mail z nagłówkiem `Auto-Submitted: auto-generated` lub z tematem rozpoczynającym się od "Out of office" - system odkłada to do wątku, ale **NIE wyzwala notyfikacji** Reverb ani SMS. Musimy napisać do tego case w testach.
- **Sanityzacja HTML (XSS Protection)**: Prawdziwe maile to "dziki zachód". Musimy zintegrować bibliotekę (np. `mews/purifier`) na poziomie zapisywania `tresc_html` do bazy, aby chronić nasz UI (shadcn) przed atakami typu XSS. To również wymaga dodania odpowiedniego testu (wstrzyknięcie złośliwego znacznika `<script>`).

### Tuning UI
- **Zwijanie Cytatów**: Maile w korporacjach (B2B) często zawierają potężną historię korespondencji (drzewo). Na front-endzie UI domyślnie będzie "ucinać" historię maila w wątku poniżej odpowiedniego markera (np. `On [date] wrote:`) i chować w `<Collapsible>` (z shadcn-vue) zatytułowanym "Pokaż ukrytą historię". Znacząco usprawni to pracę operatorom ERP.
- **Skeletons (shadcn)**: W teście UI sprawdzamy, czy w przypadku ładowania szczegółów dużej wiadomości pojawia się `<Skeleton>` zamiast nagłego "mignięcia" zawartości na ekranie.
- **Odznaki VIP (Badges)**: Integracja z poziomami lojalnościowymi: jeśli klient ma status VIP lub platynowy, przy jego nowym mailu w inboxie obok imienia ma renderować się czerwony/złoty badge `<Badge variant="destructive">VIP</Badge>`. Dzięki temu pracownicy obsłużą ich priorytetowo.