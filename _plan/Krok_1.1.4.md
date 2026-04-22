# Plan Implementacji - Krok 1.1.4: Testy (Access Control) [TEST]

> **Wersja:** 1.0
> **Dotyczy:** Drukarnia ERP 2026 (Moduł 13 - Access Control)
> **Stack:** PHPUnit, Laravel 11, Inertia.js (Vue 3), shadcn-vue

## 1. Background & Motivation (Cel i Kontekst)
Celem kroku jest zapewnienie pełnego pokrycia testami automatycznymi (>85%) krytycznego modułu kontroli dostępu (Access Control). System autoryzacji i przypisywania ról wymaga solidnego ubezpieczenia przed regresją. Realizacja zakłada optymalizację wydajności testów, przejrzystość procesów w tle oraz zdefiniowanie doświadczenia użytkownika (UX) podczas prób nieautoryzowanego dostępu, wykorzystując najlepsze praktyki Laravel (PHPUnit) i standardy shadcn-vue dla UI.

## 2. Scope & Impact (Zakres)
- Stworzenie pełnej suity testów (Feature) dla procesu uwierzytelniania: `LoginTest.php`, `RoleAccessTest.php`.
- Testowanie poprawności blokowania dostępu dla zdezaktywowanych kont użytkowników (soft block).
- Testowanie działania ról systemowych (np. brak dostępu `Operator` do strony `/admin/users`).
- Tuning obsługi błędów 403 (Forbidden) poprzez rzutowanie ich na przyjazne dla użytkownika powiadomienia Toasts w panelu (przekierowanie + Flash message).
- Weryfikacja interakcji między modułami: Moduł 13 (Auth) <-> Moduł 1 (System Core / Audit Logs).

## 3. Proposed Solution (Proponowane Rozwiązanie i Tuning)

### A. Workflow UI/UX i Kreslenie (Projekt Interakcji)
Chociaż zadanie dotyczy infrastruktury backendowej i testów, precyzujemy testowane zachowania warstwy UI:
1. **Zablokowany Użytkownik:**
   - **UX:** Gdy konto pracownika oznaczone jest jako nieaktywne, próba logowania pozostawia użytkownika na ekranie logowania. Pola input sygnalizują błąd wizualny. Po stronie frontendu renderowany jest Toast: "Konto jest nieaktywne. Skontaktuj się z administratorem." Test sprawdza wyrzucane do sesji błędy walidacji.
2. **Odmowa Dostępu (Brak Roli - 403):**
   - **UX Blueprint:** Jeśli pracownik (np. Operator) spróbuje wejść na URL zarezerwowany dla Administratora, system powstrzyma akcję na poziomie Gate/Policy. 
   - Zamiast statycznej, systemowej strony 403 HTML, użytkownik zostaje inteligentnie przekierowany z powrotem na główny widok dashboardu.
   - W backendzie zapisujemy odpowiedź przez parametr flash error. Na frontendzie odbieramy to przez Inertia Shared Props w komponencie nadrzędnym i wywołujemy natywnego Toasta: "Nie posiadasz uprawnień". Test asertuje odpowiednie przekierowanie HTTP i obecność komunikatu w sesji.
3. **Pomyślne Logowanie:**
   - Błyskawiczne przekierowanie do panelu. Test weryfikuje nadanie stanu uwierzytelnienia oraz zwrot odpowiedniego przekierowania.

### B. Cross-Module Interaction (Interakcja Między Modułami)
1. **Auth <-> AuditLog (Moduł 13 <-> Moduł 1):** 
   - Testy zweryfikują, czy po poprawnym zalogowaniu się na konto odkładany jest wpis w tabeli logów. Używamy mechanizmu autoryzacji wysyłającego natywny event Login. Test weryfikuje zrzut z wykorzystaniem asercji bazy.
2. **Auth <-> Communication Hub (Moduł 13 <-> Moduł 8):** 
   - Przygotowujemy pole (faking events) na to, by przyszłe subskrypcje (np. alerty WebSocket dla menedżerów) nasłuchujące logowania z niezaufanych IP, działały poprawnie.

### C. Business Logic Tuning (Laravel Best Practices & Specialist Skills)
1. **Wydajność Testów:** Zamieniamy standardowy RefreshDatabase na LazilyRefreshDatabase. Trait ten optymalizuje zapytania bazy (uruchamia migracje tylko wtedy, kiedy test w ogóle dotyka bazy).
2. **Factory States:** Implementacja wydzielonych stanów w klasie fabryki użytkowników, dzięki czemu instancje będą generowane semantycznie bez nadpisywania w metodach testowych.
3. **Konwencje asercji:** Zastępujemy luźne sprawdzanie rygorystycznymi asercjami modelowymi do bazy danych.
4. **Wyjątki (Error Handling):** Dodajemy globalny mechanizm wyłapywania wyjątku autoryzacyjnego w systemie, wymuszający zwrot komunikatu JSON dla API, a dla Inertii przekierowanie z errorem. W logach wyciszamy podwójne rzucanie wyjatków (zgodnie ze specjalistycznymi zasadami).

## 4. Implementation Steps (Kroki Realizacji)

### Etap 1: Konfiguracja optymalizacji testowych
1. Modernizacja bazy testowej – implementacja LazilyRefreshDatabase.
2. Aktualizacja i zrefaktorowanie pliku fabryki o niezbędne stany, w tym relacje przypisania do ról poprzez zdarzenia po utworzeniu.
3. Refaktoryzacja klasycznych testów autoryzacji do standardów PSR-12, używając ścisłego typowania w plikach testowych.

### Etap 2: Implementacja Testów Autoryzacji
1. Utworzenie nowej klasy dla testów logowania.
2. Stworzenie metody testującej odmowę zalogowania kont zdezaktywowanych. Oczekiwany status zwrotny 302 i odpowiedni błąd w sesji.
3. Stworzenie metody testującej tworzenie logu po pomyślnym zalogowaniu (weryfikacja asercją bazy danych).
4. Wykorzystanie mechanizmu blokowania eventów w osobnej metodzie, chroniącej testy przed triggerowaniem fizycznych Mailerów.

### Etap 3: Implementacja Testów Ról i UX
1. Rozbudowanie klasy testowej dla ról.
2. Wyodrębnienie "Happy path" – Administrator z uprawnieniami wizytuje strony edycji (używamy asercji pakietu Inertii).
3. Wyodrębnienie "Forbidden path" – Operator uderzający na te same ścieżki jest kierowany powrotnie na dashboard. Sprawdzamy stan poprzez odpowiednie przekierowanie i błąd w sesji.
4. Implementacja tuningu na wyłapanie błędu odmowy dostępu z paczki Spatie, modyfikująca standardową odpowiedź.

## 5. Verification & Testing
Proces weryfikacji obejmuje testowanie samego frameworka testów:
- Bieg lokalny i sprawdzający wydajność: testy równoległe (oczekiwanie to 100 procent zdanych bez ostrzeżeń).
- Pokrycie kodu: Docelowo powyżej 85 procent by zidentyfikować ewentualnie nieobsłużone ścieżki dostępu (m.in. kontrolery Userów, Ról, Middleware'y przypisywania).
- Uruchomienie reguł formattera by upewnić się o zgodności z PSR-12.

## 6. Migration & Rollback strategies
Utworzony kod znajduje się głównie w zamkniętej strefie katalogu testów. Jego implementacja nie niesie zagrożeń dla struktury aplikacyjnej, z jednym wyjątkiem - refaktoring konfiguracji przechwytywania wyjątków. W przypadku jakiegokolwiek spadku funkcjonalności panelu produkcyjnego na skutek zmienionych reguł przechwytywania wyjątków autoryzacji (np. zapętlenia przekierowań), dokonujemy powrotu zmiany przez wycofanie z użycia git. Wtedy UX rzucania błędami powróci do standardowych responsów 403 HTTP.