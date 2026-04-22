# DRUKARNIA ERP — QUICK START GUIDE

> **Dla:** Nowych developerów  
> **Czas:** 30 minut setup + 1 godzina zapoznania  
> **Wymogi:** Docker, PHP 8.3+, Node.js 18+

---

## 1. CLONE & SETUP (5 minut)

```bash
# Clone repo
git clone https://github.com/drukarnia/erp.git
cd erp

# Setup environment
cp .env.example .env
cp .env.docker .env.local  # dla Docker

# Generate app key
php artisan key:generate
```

---

## 2. DOCKER UP (3 minutes)

```bash
# Start all services
docker-compose up -d

# Wait 10 seconds for DB to be ready
sleep 10

# Database setup
docker-compose exec app php artisan migrate --seed

# Frontend build
docker-compose exec app npm run build
```

**Services:**
- App: http://localhost (port 80)
- Mailhog: http://localhost:1025 (debug emails)
- MinIO: http://localhost:9000 (admin: minioadmin/minioadmin)
- PgAdmin: http://localhost:5050 (admin: admin@admin.com/admin)

---

## 3. LOGIN (2 minuty)

```
URL: http://localhost
Email: admin@drukarnia.local
Password: password (z .env)
```

---

## 4. STRUKTURA PROJEKTU (10 minut lektury)

### Backend — KEY FILES

```
app/
  Models/
    - Zamowienie.php           ← Order entity
    - Klient.php                ← Client
    - Pozycja.php               ← Line item
    - Plik.php                  ← File/asset
    - Wiadomosc.php             ← Message
  
  Services/
    - PricingEngine.php         ← Pricing logic (PURE PHP, no Eloquent)
    - MinioStorageService.php   ← S3 operations
    - MailSender.php            ← Email sending
    
  Jobs/
    - ProcessIncomingEmail.php  ← IMAP emails (queue)
    - ProcessUploadedFile.php   ← File processing
    - GenerateThumbnails.php    ← Image resize
    
  States/
    - Order/NewState.php        ← FSM states
    - Order/PricingState.php
    - Order/ApprovedState.php
    
  Listeners/
    - CreateProductionJob.php   ← Event handlers
    - SendNotification.php
    
  Policies/
    - OrderPolicy.php           ← Authorization
    - ClientPolicy.php

database/
  migrations/
    - 2026_01_01_create_klienci_table.php
    - 2026_01_02_create_zamowienia_table.php
    - ...
  
  seeders/
    - StatusDefinitionSeeder.php
    - RoleSeeder.php

routes/
  - web.php                     ← Inertia routes
  - api.php                     ← REST endpoints (webhooks)

tests/
  - Feature/OrderTest.php
  - Unit/PricingEngineTest.php
```

### Frontend — KEY FILES

```
resources/js/
  Pages/
    - Dashboard.vue             ← Main page per role
    - Orders/
      - Index.vue               ← Orders list
      - Show.vue                ← Order detail card
      - Create.vue              ← Order wizard
    
    - Clients/
      - Index.vue
      - Show.vue
    
    - Admin/
      - Users/Index.vue
      - Pricing/RulesIndex.vue
  
  Components/
    - OrderStatusBadge.vue      ← UI element
    - StatusTimeline.vue
    - UploadWidget.vue
    - PricingBreakdown.vue
    
    - ui/                       ← shadcn-vue components ONLY
      - Button.vue
      - Card.vue
      - Dialog.vue
      - Sheet.vue
      - Tabs.vue
      - etc.
  
  Layouts/
    - AppLayout.vue             ← Main layout (nav, sidebar, footer)
  
  Composables/
    - useAuth.js                ← Auth helpers
    - useNotifications.js       ← Websocket notifications

config/
  - auth.php                    ← Sanctum config
  - queue.php                   ← Redis, retry policy
  - horizon.php                 ← Queue UI
```

---

## 5. FIRST COMMIT (5 minut practice)

```bash
# Create a simple task: add icon to Button component
cd resources/js/Components
# Edit Button.vue → add icon prop

# Test it works
npm run dev

# Commit
git checkout -b feat/button-icon
git add -A
git commit -m "feat(ui): add icon prop to Button component"
git push origin feat/button-icon

# Create PR, get review, merge
```

---

## 6. HOW TO...

### Add a new page

1. Create `resources/js/Pages/NewFeature/Index.vue`
2. Create `app/Http/Controllers/NewFeatureController.php`
3. Add route in `routes/web.php`
4. Add link in `AppLayout.vue` sidebar (if admin-only, wrap in `@can('feature.view')`)

### Add a database migration

```bash
php artisan make:migration create_new_table
# Edit database/migrations/YYYY_MM_DD_HHmmss_create_new_table.php
php artisan migrate
```

### Add a service (business logic)

```bash
php artisan make:class Services/MyService
# resources/js/Services/MyService.php — NO Laravel dependencies
```

### Add a queue job

```bash
php artisan make:job ProcessSomething
# app/Jobs/ProcessSomething.php
# Use: ProcessSomething::dispatch($data);
```

### Add a test

```bash
# Feature test (HTTP)
php artisan make:test Feature/OrderTest

# Unit test (logic)
php artisan make:test Unit/PricingEngineTest --unit
```

Run tests:
```bash
./vendor/bin/phpunit tests/Feature/OrderTest.php
# or all:
./vendor/bin/phpunit
```

### Run a cron job manually

```bash
# Check schedule
php artisan schedule:list

# Run
php artisan schedule:run
```

---

## 7. DEBUGGING

### Laravel Pail (tail logs)

```bash
php artisan pail --since=10m
```

### Horizon (queue dashboard)

```
http://localhost/horizon
```

### PgAdmin (database)

```
http://localhost:5050
Server: postgres
Username: postgres
Password: (check .env)
```

### Mailhog (test emails)

```
http://localhost:1025
```

### Broadcast (websocket)

```bash
# Terminal 1: Watch
php artisan reverb:start

# Terminal 2: Test
php artisan tinker
>>> broadcast(new \App\Events\OrderStatusChanged($order));
```

---

## 8. COMMON ISSUES

### "SQLSTATE[HY000]: General error: 1030 Got error"

→ Database connection issue. Check `docker-compose ps` and `docker-compose logs postgres`.

```bash
docker-compose down -v  # Remove volumes
docker-compose up -d
docker-compose exec app php artisan migrate --seed
```

### "npm run dev" hangs

→ Vite issue. Kill and restart:

```bash
docker-compose exec app npm run dev  # Ctrl+C
docker-compose exec app npm run build
```

### "Queue jobs not processing"

```bash
# Check Horizon
http://localhost/horizon

# Restart queue worker
docker-compose exec app php artisan queue:restart

# Manual check
docker-compose exec app php artisan queue:work --tries=3
```

### "File upload fails"

→ MinIO issue. Check presigned URL:

```bash
docker-compose exec app php artisan tinker
>>> app(MinioStorageService::class)->getPresignedUrl('file-key')
```

---

## 9. MUST-READ DOCS

| Dokument | Zawartość | Czas |
|----------|-----------|------|
| [00_MASTER_PLAN_CONSOLIDATED.md](00_MASTER_PLAN_CONSOLIDATED.md) | Full architecture, 18 modules | 1–2 h |
| [01_ROADMAP_CONSOLIDATED.md](01_ROADMAP_CONSOLIDATED.md) | Phase 1–4 breakdown | 1 h |
| [ARCHITECTURE.md](ARCHITECTURE.md) | Technical deep-dives (ADR) | 1 h |
| [DATABASE_SCHEMA.md](DATABASE_SCHEMA.md) | Schema + relations | 30 min |

---

## 10. TERMINAL CHEAT SHEET

```bash
# DEVELOPMENT
docker-compose exec app php artisan tinker                  # REPL
docker-compose exec app php artisan route:list              # Routes
docker-compose exec app php artisan make:model Client -m    # Model + migration

# DATABASE
docker-compose exec app php artisan migrate                 # Apply migrations
docker-compose exec app php artisan migrate:rollback        # Undo
docker-compose exec app php artisan db:seed                 # Run seeders
docker-compose exec app php artisan db:wipe                 # DANGER: Drop all

# QUEUE
docker-compose exec app php artisan queue:work              # Start worker
docker-compose exec app php artisan queue:restart           # Restart all workers
docker-compose exec app php artisan queue:failed            # Show failed jobs

# TESTING
php artisan test                                             # Run all tests
php artisan test tests/Feature/OrderTest.php                # One test
php artisan test --coverage                                 # With coverage

# LOGS
php artisan pail                                             # Real-time logs
tail -f storage/logs/laravel-2026-04-20.log                 # File logs

# CACHE / REDIS
docker-compose exec app php artisan cache:clear             # Clear app cache
docker-compose exec redis redis-cli FLUSHALL                # Clear Redis entirely
```

---

## NEXT STEPS

1. ✅ Setup: `docker-compose up -d` + migrate + seed
2. ✅ Explore: `/dashboard` + `/orders` + `/clients`
3. ✅ Read: MASTER_PLAN (30 min overview)
4. ✅ Pick a feature from ROADMAP (Faza 1, Etap 1.x)
5. ✅ Create branch + commit + PR
6. ✅ Ask questions in Slack / GitHub Discussions

---

**Happy coding! 🚀**

Pytania? Otwórz issue na GitHub lub zapytaj w Slack: #dev-drukarnia-erp
