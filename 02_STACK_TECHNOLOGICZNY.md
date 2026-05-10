# 02 — STACK TECHNOLOGICZNY

Wszystkie wersje są **zablokowane** w `composer.json` / `package.json`. Zmiana majorów wymaga ADR.

## 1. Backend

| Komponent | Wersja | Rola | Uwagi |
|-----------|--------|------|-------|
| PHP | **8.3** | Runtime | JIT, readonly classes, fibers |
| Laravel | **11** | Framework | `bootstrap/app.php` (nowa struktura), middleware deklaratywny |
| Laravel Octane (FrankenPHP) | latest | Hot-reload + 3× RPS | Opcjonalne; MVP może iść na php-fpm |
| Laravel Fortify | v1 | Auth (login, register, forgot, 2FA) | |
| Laravel Sanctum | v4 | API tokens (portal klienta, mobile PWA) | |
| Laravel Horizon | v5 | Monitoring kolejek Redis | |
| Laravel Reverb | v1 | WebSocket (zamiast Pushera) | |
| Laravel Pail | v1 | Tail logów dev | |
| Laravel Boost | v2 | MCP tools w tym projekcie | (dev only) |
| Laravel MCP | v0 | Artisan MCP server | (dev tool) |
| Laravel Prompts | v0 | CLI prompts | (seeders, komendy) |
| Laravel Sail | v1 | Docker dev | (opcjonalne) |
| Tightenco Ziggy | v2 | Routes w JS | |
| Laravel Pint | v1 | Formatter | `vendor/bin/pint --dirty --format agent` przed commitem |
| PHPUnit | **11** | Testy | Pest też OK |
| Laravel Pulse | latest | APM wbudowany | |

### Paczki Spatie

| Paczka | Rola |
|--------|------|
| `spatie/laravel-permission` | Role i granularne uprawnienia (RBAC) |
| `spatie/laravel-model-states` | FSM 16 stanów zamówienia |
| `spatie/laravel-activitylog` | Audit log (GDPR-ready) |
| `spatie/laravel-medialibrary` | **Versioning + thumbnails** dla DAMS (patrz 08) |
| `spatie/pdf-to-image` | PDF → PNG (miniatury) — wymaga Ghostscript |

### Paczki pomocnicze

| Paczka | Rola |
|--------|------|
| `brick/math` | Arytmetyka Decimal dla pieniędzy — **nigdy float** |
| `league/csv` | Parsowanie CSV (import materiałów, parametrów — zamiast pandas) |
| `intervention/image` v3 | Miniatury i konwersje obrazów |
| `ankitpokhrel/tus-php` | tus.io server (resumable uploads) |
| `saloonphp/saloon` v3 | Adaptery HTTP (Subiekt, GUS, P24, kurierzy) — patrz 10 |
| `laravel/scout` + `meilisearch/meilisearch-php` | Search |
| `predis/predis` lub `phpredis` | Redis client |
| `pestphp/pest-plugin-laravel` | (opcjonalnie) Pest zamiast PHPUnit |
| `nunomaduro/collision` | Ładne błędy w CLI |

### **Usunięte z legacy (NIE instalować!)**

Starsze plany (`MASTER_PLAN.md`) sugerowały paczki związane z hmurą. **Nie dodawać:**
- `league/flysystem-aws-s3-v3`
- `pusher/pusher-php-server`
- `laravel/nova` (płatne, zastępuje Filament)
- `intervention/imagehttp` (nieużywane)

Zamiast MinIO/S3: `league/flysystem-local` (część Laravela) — dysk `local_private` (patrz 08).

---

## 2. Frontend

| Komponent | Wersja | Rola |
|-----------|--------|------|
| Vue | **3.4+** | Composition API, `<script setup>` |
| Inertia.js | **v3** | SPA bez REST (useForm, useHttp, layout props, deferred props) |
| @inertiajs/vue3 | v3 | Adapter Vue |
| @inertiajs/vite | latest | SSR auto w Vite dev |
| Vite | **5** | Build, HMR |
| Tailwind CSS | **3** | Utility CSS (v4 gdy stabilny) |
| **shadcn-vue** | latest | **Jedyne źródło komponentów UI** |
| Reka UI | latest | Baza shadcn-vue (headless) |
| `lucide-vue-next` | latest | **Jedyna biblioteka ikon** |
| TypeScript | 5.x | (opcjonalnie z Breeze `--typescript`) |
| vue-konva + Konva.js | 3 + 9 | **Tylko moduł 18** (Design Editor) |
| PDF.js | 4 | Podgląd PDF w approvals i DAMS |
| Chart.js + vue-chartjs | 4 + 5 | Moduł 14 (Reports) — wykresy |
| vee-validate + zod | 4 + 3 | Walidacja form (opcjonalnie; podstawowa walidacja z Inertii) |
| laravel-echo + pusher-js | 2 + 8 | Client Reverb |
| @tanstack/vue-table | v8 | Zaawansowane tabele (lista zamówień, raportów) |

**Zasady bezwzględne (z globalnego `CLAUDE.md`):**
- Wszystkie komponenty UI z `resources/js/Components/ui/` (shadcn-vue). Brakujące: `npx shadcn-vue@latest add <komponent>`.
- Ikony: **wyłącznie** `lucide-vue-next`.
- Kolory: **wyłącznie semantyczne** (`bg-primary`, `text-muted-foreground`, `border-input`). **Zakaz** `bg-blue-500`, `text-gray-400`.
- Odstępy: `flex gap-*` / `grid gap-*`. **Zakaz** `space-x-*` i `space-y-*`.
- Kwadraty/ikony: `size-*` (np. `size-10`), nie `w-10 h-10`.
- Forms: `<FormField>` + `<FormItem>` + `<FormLabel>` + `<FormControl>` + `<FormMessage>` — nie ręczne divy.
- Modale: `<Dialog>` + `<DialogTrigger asChild>` + `<DialogContent>`.
- `v-model` (nie `:value` + `@update:*`).
- Dynamiczne klasy: `cn(...)` (`@/lib/utils`).

---

## 3. Infrastruktura

| Komponent | Wersja | Rola |
|-----------|--------|------|
| PostgreSQL | **16** | Główna DB; JSONB, generated columns, partitioning |
| Redis | **7** | Cache, queues, locks, Horizon |
| Meilisearch | v1.11+ | Search; polski tokenizer |
| Nginx | 1.24+ | Reverse proxy, `X-Accel-Redirect` dla DAMS |
| Mailpit | latest | Dev mailbox |
| Ghostscript | 10+ | PDF → PNG |
| ImageMagick | 7+ | Resizing |
| Supervisor | latest | Horizon + Reverb (prod) |
| Ubuntu | **24.04 LTS** | OS hosta |

---

## 4. Narzędzia dev

| Narzędzie | Rola |
|-----------|------|
| Docker + Docker Compose | Dev env (`docker compose up`) |
| Laravel Sail (opc.) | Owijka Dockera |
| GitHub Actions | CI — `pint --test`, `pest`, `npm run build` |
| Deployer v7 (lub Envoy) | Deploy zero-downtime |
| Sentry (self-host lub SaaS) | Error tracking |
| Uptime Kuma | Monitoring dostępności |
| Laravel Telescope | (opc. tylko dev) debug bar |
| Laravel Herd (macOS) lub Laragon (Win) | Lokalne środowisko |

---

## 5. Wersje wymagane w `composer.json` (minimum)

```json
{
  "require": {
    "php": "^8.3",
    "laravel/framework": "^11.0",
    "inertiajs/inertia-laravel": "^2.0",
    "tightenco/ziggy": "^2.0",
    "laravel/fortify": "^1.0",
    "laravel/sanctum": "^4.0",
    "laravel/horizon": "^5.0",
    "laravel/reverb": "^1.0",
    "spatie/laravel-permission": "^6.0",
    "spatie/laravel-model-states": "^2.0",
    "spatie/laravel-activitylog": "^4.0",
    "spatie/laravel-medialibrary": "^11.0",
    "spatie/pdf-to-image": "^3.0",
    "intervention/image": "^3.0",
    "ankitpokhrel/tus-php": "^2.0",
    "saloonphp/saloon": "^4.0",
    "laravel/scout": "^11.1",
    "meilisearch/meilisearch-php": "^1.0",
    "brick/math": "^0.12",
    "league/csv": "^9.0"
  },
  "require-dev": {
    "phpunit/phpunit": "^11.0",
    "laravel/pint": "^1.0",
    "laravel/pail": "^1.0",
    "laravel/telescope": "^5.0",
    "nunomaduro/collision": "^8.0"
  }
}
```

## 6. Wersje wymagane w `package.json` (minimum)

```json
{
  "dependencies": {
    "@inertiajs/vue3": "^2.0",
    "@inertiajs/vite": "^1.0",
    "vue": "^3.4",
    "lucide-vue-next": "^0.400",
    "laravel-echo": "^2.0",
    "pusher-js": "^8.0",
    "chart.js": "^4.0",
    "vue-chartjs": "^5.0",
    "vue-konva": "^3.0",
    "konva": "^9.0",
    "pdfjs-dist": "^4.0",
    "@tanstack/vue-table": "^8.0",
    "vee-validate": "^4.0",
    "zod": "^3.0"
  },
  "devDependencies": {
    "@vitejs/plugin-vue": "^5.0",
    "vite": "^5.0",
    "tailwindcss": "^3.0",
    "postcss": "^8.0",
    "autoprefixer": "^10.0",
    "typescript": "^5.0"
  }
}
```

Wersje Reka UI i shadcn-vue są zarządzane przez CLI `npx shadcn-vue@latest init` — nie instalowane ręcznie.
