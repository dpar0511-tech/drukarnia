# Plan Implementacji: Krok 1.2.5 — Szablony seed [BE] (wraz z UX/UI Workflow)

## Cel (Objective)
Zaimplementować seeder dla Szablonów Wiadomości (Moduł 8: Communication Hub) z predefiniowanymi 9 szablonami. Zaprojektować i opisać docelowy obieg UX/UI dla Menedżera zarządzającego tymi szablonami (korzystając z podejścia: czysty tekst / Markdown + zintegrowany podgląd). Stworzyć kros-modułowe wytyczne dla zasilania parsera danymi z zamówień.

## Kluczowe pliki i kontekst (Key Files & Context)
- `database/seeders/SzablonWiadomosciSeeder.php`
- `app/Models/SzablonWiadomosci.php`
- Przyszłe UI (shadcn-vue): `resources/js/Pages/Admin/Templates/Index.vue`, `Edit.vue`
- Moduły: Communication Hub (8) oraz Order Management (3)

## Kroki implementacji (Implementation Steps)

### 1. Rozbudowa Modelu i Logiki (Tuning Biznesowy)
- **Model `SzablonWiadomosci`**: Rozszerzenie o logikę `extractVariables()` wykorzystującą RegEx (np. `/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/`) do automatycznego skanowania `tresc_template` i weryfikacji ze zdefiniowanym polem JSON `zmienne`.
- **Zabezpieczenia (Fallback)**: System wysyłki będzie cicho ignorował brakujące zmienne (zamieniał je na puste ciągi i logował warning) zamiast rzucać błędy krytyczne, zapobiegając awariom podczas masowej wysyłki maili przez kolejkę.

### 2. Utworzenie SzablonWiadomosciSeeder
Skrypt zasilający bazę danymi za pomocą podejścia "Markdown / Plain Text". Seed doda 9 głównych e-maili systemowych:
1. `oferta_wyslana`: "Twoja wycena zamówienia {{zamowienie.numer}} jest gotowa. [Opłać tutaj]({{link_platnosci}})"
2. `proforma_wyslana`: Dokument proforma w załączniku (Zmienne: `{{zamowienie.numer}}`).
3. `platnosc_otrzymana`: Potwierdzenie otrzymania wpłaty `{{platnosc.kwota}}`.
4. `prosba_o_pliki`: Prośba o upload brakujących plików do druku (`{{link_upload}}`).
5. `link_akceptacji`: Prośba o e-podpis/akceptację po udanym preflight (`{{link_akceptacji}}`, `{{wygasa_za_godzin}}`).
6. `preflight_failed`: Błąd w plikach klienta - powód odrzucenia (`{{preflight.powod}}`).
7. `zamowienie_gotowe`: Zlecenie zakończyło druk i oczekuje na odbiór kuriera.
8. `wysylka_utworzona`: Paczka nadana u kuriera `{{wysylka.kurier}}`, nr `{{wysylka.numer_listu}}`, śledzenie: `{{wysylka.tracking_url}}`.
9. `wysylka_dostarczona`: Zamówienie sfinalizowane (COMPLETED) z podziękowaniem.

### 3. Kros-Modułowa Wzajemna Akcja i UX/UI Workflow [FE]
W późniejszym etapie Frontend Developer zbuduje ekrany oparte na `shadcn-vue`:
- **Ekran Listy (`/admin/templates`)**: `DataTable` z kolumnami (Nazwa, Kanał - jako `Badge`, Temat).
- **Ekran Edycji (`/admin/templates/:id/edit`)**:
  - Dwa główne obszary robocze:
    - **Lewa Kolumna (`Tabs` component)**:
      - *Zakładka Edycji*: Standardowy `Input` dla tematu oraz obszerny `Textarea` z monospacową czcionką dla edycji czystego tekstu lub formatowania Markdown.
      - *Zakładka Podglądu*: Wyrenderowany na żywo Markdown, który interpoluje zmienne `{{ }}` na przykładowe dane demonstracyjne (np. "DRK-2026-0001"), aby użytkownik nietechniczny od razu wiedział, jaki rezultat dostanie klient.
    - **Prawa Kolumna**:
      - Sekcja "Dostępne Zmienne" oparta o komponenty `Badge` (np. `{{zamowienie.numer}}`, `{{klient.imie}}`).
      - UX tuning: Kliknięcie danego `Badge` powoduje automatyczne skopiowanie tagu do schowka lub (najlepiej) wstawienie go od razu w pozycję kursora wewnątrz otwartego pola `Textarea`. To zabezpiecza przed literówkami podczas ręcznego wpisywania nazw.

## Weryfikacja i Testowanie
1. **Wykonanie Seeding'u**: Po wywołaniu `php artisan db:seed --class=SzablonWiadomosciSeeder` w tabeli `szablony_wiadomosci` ma znaleźć się 9 poprawnie sformatowanych rekordów (ze zrzutowanymi tablicami zmiennych w kolumnie `zmienne`).
2. **Opcjonalny Unit Test (Backend)**: Zapewnienie, że metoda wyciągająca zmienne (`extractVariables`) poprawnie interpretuje podane tagi dla szablonu z seedera i nie rzuca niespodziewanych błędów składni.