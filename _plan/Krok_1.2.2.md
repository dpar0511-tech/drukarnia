# DRUKARNIA ERP 2026 — Szczegółowy Plan Implementacji: Krok 1.2.2 — IMAP Import [BE] — Baza danych [DB]

## 1. Cel i Zakres
* **Baza danych (Krok 1.2.1)**: Stworzenie spójnego schematu (tabele `watki_komunikacji`, `wiadomosci`, `zalaczniki_wiadomosci`, `szablony_wiadomosci`, `powiadomienia`) umożliwiającego rejestrację korespondencji e-mailowej i powiadomień systemowych.
* **Backend IMAP (Krok 1.2.2)**: Integracja ze skrzynką e-mail przy użyciu `webklex/laravel-imap`. Uruchamianie asynchronicznego skryptu co 2 minuty pobierającego nowe wiadomości. Deduplikacja przy pomocy unikalnego `zewnetrzny_message_id`.
* **UX/UI Workflow**: Implementacja interfejsu skrzynki odbiorczej typu "Split-pane" za pomocą `shadcn-vue`, gdzie po lewej stronie wyświetlana jest lista e-maili, a po prawej szczegóły.
* **UX Tuning / Biznes Logika**: System automatycznie spróbuje powiązać mail z istniejącym klientem. Jeśli nie znajdzie dopasowania, w interfejsie zaproponuje dodanie nowego klienta i utworzenie z zapytania nowego "Zamówienia".

## 2. Architektura Bazy Danych
Zgodnie z konwencją `laravel-best-practices`, relacyjna baza oparta na PostgreSQL 16:

1. **`watki_komunikacji`**:
    * `id` (PK)
    * `zamowienie_id` (FK, nullable)
    * `klient_id` (FK, nullable)
    * `temat` (string)
    * `status` (string: np. otwarty, zamkniety)
    * `timestamps`

2. **`wiadomosci`**:
    * `id` (PK)
    * `watek_id` (FK) -> cascadeOnDelete
    * `kierunek` (enum: przychodzacy, wychodzacy, systemowy)
    * `kanal` (enum: email, sms, system)
    * `tresc_html` (text, nullable)
    * `nadawca_email` (string, nullable)
    * `odbiorca_email` (string, nullable)
    * `zewnetrzny_message_id` (string, UNIQUE, nullable) - **kluczowe dla deduplikacji IMAP**
    * `przeczytana` (boolean, domyślnie false)
    * `timestamps`

3. **`zalaczniki_wiadomosci`**:
    * `id` (PK)
    * `wiadomosc_id` (FK) -> cascadeOnDelete
    * `plik_id` (FK z modułu DAMS, nullable) -> nullOnDelete
    * `nazwa_oryginalna` (string)

4. **`powiadomienia`**:
    * Tablica na powiadomienia "In-App" (w dzwoneczku w aplikacji)

## 3. Workflow Użytkownika i Interfejs (shadcn-vue + Inertia.js)

1. **Notyfikacje (Real-time)**: Gdy job przetworzy nowego maila, wysyła broadcast przez Laravel Reverb. Na froncie użytkownik (Admin/Menedżer) widzi powiadomienie "Toast" (komponent `sonner`) oraz zwiększa się licznik (`Badge`) na ikonie powiadomień.
2. **Skrzynka odbiorcza (Split-pane Layout)**:
    * Wykorzystamy komponent `Resizable` (shadcn-vue), aby panel działał jak klasyczny klient e-mail (np. Mac Mail).
    * **Lista po lewej (30%)**: Lista wątków (scrollowalna - `ScrollArea`). Używamy `Badge` przy nazwie, aby uwydatnić nieprzeczytane.
    * **Szczegóły (70%)**: Pełna treść wątku (renderowana wewnątrz komponentu `Card`). Awatary nadawców używają komponentu `Avatar` z opcją `AvatarFallback` na inicjały z maila.
3. **Akcje i Kros-modułowość**:
    * Bezpośrednio w podglądzie maila umieścimy inteligentne "Call to Action":
        * Jeśli nadawca nie jest w bazie: Przycisk **"Dodaj Klienta"**.
        * Bez względu na status: Przycisk **"Utwórz Zamówienie"** (przekazuje treść maila do formularza nowego zlecenia, łącząc moduły).

## 4. Implementacja Backend i IMAP (Webklex)

* Zmienne konfiguracyjne (`IMAP_HOST`, `IMAP_PORT`, itd.) zapisane w `.env`.
* **Scheduler**: W `routes/console.php` rejestrujemy job sprawdzający skrzynkę z blokadą przed podwójnym wykonaniem:
  `Schedule::job(new FetchImapEmails)->everyTwoMinutes()->withoutOverlapping();`

* **Zadanie `FetchImapEmails`**: Łączy się przez bibliotekę `webklex/laravel-imap`, filtruje po wiadomościach `unseen()`. Następnie przekazuje je do osobnego Joba `ProcessIncomingEmail` i flaguje jako `SEEN` na serwerze e-mail.
* **Zadanie `ProcessIncomingEmail`**:
    1. Sprawdza czy `zewnetrzny_message_id` istnieje (deduplikacja).
    2. Próbuje odnaleźć `Klient::where('email_glowny', $nadawca)->first()`. Jeśli istnieje, wiąże z nim wątek.
    3. Zapisuje treść HTML maila (z ewentualnym oczyszczaniem HTML np. pakietem `mews/purifier` lub bazując na plain_text).
    4. Rozgłasza Event `NewMessageReceived`, do którego podłączony jest Reverb na froncie.

## 5. Podsumowanie Kroków Technicznych:
1. Instalacja `webklex/laravel-imap`.
2. Migracje bazy dla 4 tabel i zaktualizowanie modeli (ustawienie `$fillable` oraz relacji `BelongsTo` / `HasMany`).
3. Utworzenie 2 klas typu Job (Fetch + Process) z poprawną obsługą wyjątków (np. błędne dane z maila IMAP).
4. Budowa kontrolera InboxController i implementacja stron `/inbox` z wykorzystaniem układu split-pane w `shadcn-vue`.
5. Testy jednostkowe mockujące połączenie IMAP i testujące pomyślne przypisanie klienta oraz deduplikację Message-ID.
