# Audyt i Plan Poprawek: Krok 1.0.1 — Inicjalizacja projektu [CFG]

## Cel
Naprawa błędów konfiguracyjnych i odchyleń od `MASTER_PLAN_CONSOLIDATED` oraz zaleceń architektonicznych wprowadzonych podczas inicjalizacji projektu (Krok 1.0.1).

## Znalezione Błędy i Odchylenia (Bugi)

1. **Brak usługi Laravel Reverb w `docker-compose.yml`**
   - **Opis:** Projekt używa Laravel Reverb dla WebSockets (Moduł 8: Communication Hub), jednak w pliku `docker-compose.yml` brakuje dedykowanego kontenera dla tej usługi.
   - **Skutek:** Brak możliwości zestawienia połączeń Real-Time (WebSockets) z poziomu frontendu i nie działające nadawanie zdarzeń.

2. **Nieprawidłowy pakiet animacji Tailwind (Błąd kompatybilności z `shadcn-vue`)**
   - **Opis:** W `package.json` zainstalowano pakiet `tw-animate-css`. Oficjalnym wymogiem dla animacji komponentów interfejsu `shadcn-vue` jest pakiet `tailwindcss-animate`. Dodatkowo, w pliku `resources/css/app.css` figuruje błędna składnia dla Tailwinda v4 (`@import "tw-animate-css";` zamiast ładowania pluginu).
   - **Skutek:** Animacje podstawowych komponentów (Accordion, Dialog, Select) mogą nie działać poprawnie.

3. **Niestandardowy (Customowy) Middleware do kontroli ról zamiast Spatie**
   - **Opis:** Utworzono autorski plik `app/Http/Middleware/CheckPermission.php`, który wywołuje w model `User` autorską metodę `$user->hasPermission()`. Plik ten oznaczono w `bootstrap/app.php` jako alias `permission`. Odbiega to radykalnie od wymagań MASTER_PLAN, który wymusza stosowanie pakietu `spatie/laravel-permission` i jego potężnych wbudowanych middleware'ów (`role`, `permission`, `role_or_permission`).
   - **Skutek:** Duplikacja kodu, odchylenie od standardów architektonicznych oraz ryzyko problemów z bezpieczeństwem autoryzacji.

4. **Niekompletny plik `tsconfig.json` dla ekosystemu `shadcn-vue` i Vue 3**
   - **Opis:** Konfiguracja TypeScript w pliku `tsconfig.json` jest ograniczona do absolutnego minimum. Ekosystem `shadcn-vue` oraz natywne projekty Vite + Vue 3 wymagają sprecyzowania sposobu analizy kodu m.in. dla JSX/TSX i rozwiązywania modułów (tzw. `bundler` / `node`).
   - **Skutek:** Potencjalne i ukryte błędy kompilacji TypeScript, mniejsza pomoc IDE, słaba rozpoznawalność struktur komponentów.

## Plan Implementacji Poprawek (Krok po kroku)

### Krok 1: Dodanie usługi Reverb do Docker Compose
1. Otworzyć `docker-compose.yml`.
2. Dodać nową usługę `reverb` wzorując się na usłudze `horizon`, używając obrazu PHP (plik `docker/php/Dockerfile`).
3. Zdefiniować polecenie startowe kontenera jako: `command: php artisan reverb:start --host="0.0.0.0" --port=8080`.
4. Wyeksponować zewnętrzny port (np. `8080:8080`).
5. Dodać współdzielone ścieżki i sieci.

### Krok 2: Przywrócenie standardu Spatie Permissions
1. Usunąć z systemu całkowicie plik middleware: `app/Http/Middleware/CheckPermission.php`.
2. Zmodyfikować rejestrację middleware'ów w pliku `bootstrap/app.php`:
   ```php
   $middleware->alias([
       'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
       'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
       'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
   ]);
   ```
3. W modelu `app/Models/User.php`:
   - Usunąć metodę autorską: `public function hasPermission(string $permission) { ... }`.
   - Zastąpić ewentualne ręczne sprawdzanie uprawnień natywnym trait-em `HasRoles` pakietu Spatie, który już tam w większości figuruje.

### Krok 3: Naprawa animacji i konfiguracji dla Tailwind v4 / shadcn
1. Uruchomić wewnątrz środowiska polecenia node:
   ```bash
   npm uninstall tw-animate-css
   npm install tailwindcss-animate
   ```
2. Zmodyfikować plik główny styli `resources/css/app.css`:
   - Usunąć linijkę: `@import "tw-animate-css";`
   - Dodać poprawną dyrektywę zgodną z architekturą Tailwind v4: `@plugin "tailwindcss-animate";`

### Krok 4: Uzupełnienie pliku `tsconfig.json`
1. Rozbudować konfigurację w `tsconfig.json` uwzględniając najnowsze standardy kompilatora dla bundlerów.

### Krok 5: Weryfikacja Poprawek
1. Zbudować kontenery Docker: `docker-compose down && docker-compose up -d --build`.
2. Sprawdzić logi: `docker-compose logs reverb`.
3. Wywołać `npm run build`.
