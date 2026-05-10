# 13 — PLAN WDROŻENIA I DEVOPS

## 1. Target infrastruktura (MVP → Faza 3)

**1× VPS Ubuntu 24.04 LTS:**
- **CPU:** 8 vCPU (dedicated lub high-CPU burstable).
- **RAM:** 16 GB.
- **Dysk:** 500 GB NVMe (system + aplikacja + `storage/app/private/` + PostgreSQL). Plus opcjonalnie drugi dysk 1 TB dla backupu.
- **Sieć:** 1 Gbit, static IPv4, IPv6.
- **Lokalizacja:** data center w Polsce (RODO compliance, niższa latencja). Dostawcy: OVH, Hetzner (FRA lepiej niż FSN dla PL), Cyberfolks.

**Dlaczego nie K8s / multiserver w MVP:** overhead operacyjny przewyższa benefity. 1× VPS obsłuży 1 drukarnię bez trudu. Skalowanie horizontal dopiero Faza 4.

**Backup server:** mały 2-core / 4 GB / 1 TB VPS w innej lokalizacji (alternative DC) — tylko do odbioru backupów przez rsync/rclone.

---

## 2. Stack serwerowy

| Warstwa | Wersja | Role |
|---------|--------|------|
| OS | Ubuntu 24.04 LTS | firewall (ufw), fail2ban, unattended-upgrades |
| Web | Nginx 1.24+ | reverse proxy, `X-Accel-Redirect`, HTTP/2, TLS terminacja |
| PHP | 8.3 + FPM | lub **Octane + FrankenPHP** (3× RPS, hot-reload capable) |
| DB | PostgreSQL 16 | main |
| Cache/Queue | Redis 7 | cache, queues, sessions, locks |
| Search | Meilisearch 1.11 | scout |
| WebSocket | Laravel Reverb | :8080 |
| Queue workers | Horizon (supervisor) | 6 supervisorów |
| Cron | Laravel scheduler (`php artisan schedule:run` co minutę) | backup, IMAP poll, refresh views |
| PDF tools | Ghostscript + ImageMagick | miniatury |
| Mail | Postfix local relay → SMTP zewn. | outbound |
| TLS | Let's Encrypt (`certbot`) | auto-renew |
| Monitor | Laravel Pulse + Uptime Kuma (self-host) + Sentry | |
| Logs | `laravel.log` + syslog | `logrotate`, 30 dni retencja |

---

## 3. Deploy workflow

### Narzędzie: **Deployer v7** (`composer require deployer/deployer --dev`)

`deploy.php`:
```php
import('recipe/laravel.php');

host('prod')
    ->set('hostname', '1.2.3.4')
    ->set('remote_user', 'deploy')
    ->set('deploy_path', '/var/www/drukarnia-erp')
    ->set('repository', 'git@github.com:firma/drukarnia-erp.git')
    ->set('branch', 'main')
    ->set('keep_releases', 5);

task('artisan:horizon:terminate', function () {
    run('cd {{current_path}} && php artisan horizon:terminate');
})->once();

task('artisan:queue:restart', function () {
    run('cd {{current_path}} && php artisan queue:restart');
})->once();

after('deploy:symlink', 'artisan:migrate');
after('artisan:migrate', 'artisan:horizon:terminate');  // restart workers
after('deploy:published', 'artisan:queue:restart');
```

Deploy: `dep deploy prod`. Zero-downtime przez symlinki `current → releases/{timestamp}`.

### Proces deployu (pełen)

1. `git pull origin main`.
2. `composer install --prefer-dist --no-dev --no-progress --optimize-autoloader`.
3. `npm ci && npm run build`.
4. `php artisan migrate --force`.
5. `php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan event:cache`.
6. `php artisan horizon:terminate` — Horizon sam startuje się z powrotem (supervisor).
7. `php artisan queue:restart`.
8. `php artisan storage:link` (jeśli nie istnieje).
9. Symlink `current → releases/{ts}`.
10. `php artisan optimize`.
11. Smoke test: `curl -sf https://erp.drukarnia.pl/health` → 200.

### Rollback

`dep rollback prod` — instant symlink do poprzedniego release. Migracje **nie są** automatycznie cofane (manual `php artisan migrate:rollback` jeśli trzeba).

**Zasada:** migracje muszą być **always forward-compatible** — nie droppuj kolumn w tym samym deployu co usunięcie kodu korzystającego.

---

## 4. Supervisor (systemd units)

### Horizon

`/etc/supervisor/conf.d/horizon.conf`:
```ini
[program:horizon]
process_name=%(program_name)s
command=php /var/www/drukarnia-erp/current/artisan horizon
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/horizon.log
stopwaitsecs=3600
```

### Reverb

```ini
[program:reverb]
command=php /var/www/drukarnia-erp/current/artisan reverb:start --host=0.0.0.0 --port=8080
autostart=true
autorestart=true
user=www-data
stdout_logfile=/var/log/reverb.log
```

### IMAP poll (alternatywa do scheduler)

W MVP: scheduler (`$schedule->command('imap:poll')->everyTwoMinutes()->withoutOverlapping()`).

Od Fazy 3 przy większym obciążeniu: dedicated worker `imap:listen` (long-running).

---

## 5. Nginx config

`/etc/nginx/sites-available/drukarnia-erp.conf`:
```nginx
server {
    listen 80;
    server_name erp.drukarnia.pl;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name erp.drukarnia.pl;

    ssl_certificate     /etc/letsencrypt/live/erp.drukarnia.pl/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/erp.drukarnia.pl/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers 'ECDHE-RSA-AES256-GCM-SHA512:...';
    ssl_prefer_server_ciphers off;

    add_header Strict-Transport-Security "max-age=63072000" always;

    root /var/www/drukarnia-erp/current/public;
    index index.php;

    client_max_body_size 5G;                      # dla tus uploadów
    client_body_timeout  600s;
    fastcgi_read_timeout 600s;

    # Chroniona lokalizacja plików (X-Accel-Redirect)
    location /_protected/ {
        internal;
        alias /var/www/drukarnia-erp/current/storage/app/private/;
    }

    # tus uploads — surowe długie żądania
    location /tus/uploads {
        client_max_body_size 5G;
        proxy_request_buffering off;
        try_files $uri /index.php?$query_string;
    }

    # Reverb WebSocket
    location /ws/ {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_read_timeout 3600s;
    }

    # PHP
    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Static assets
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff2)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
```

**HTTP/3 (QUIC)** — Faza 2, wymaga nginx 1.25+ z flagą build.

---

## 6. Octane + FrankenPHP (opcjonalne, Faza 2+)

Alternatywa do php-fpm. Daje 3–5× RPS dla Laravela. Instalacja:
```bash
composer require laravel/octane spiral/roadrunner
php artisan octane:install --server=frankenphp
```

Run: `php artisan octane:start --server=frankenphp --port=8000`.

Nginx proxy do :8000 zamiast fastcgi.

**Kiedy przełączyć:** MVP zostaje na fpm (prostsze). Faza 2 przy ≥50 req/s — FrankenPHP.

---

## 7. PostgreSQL konfiguracja

`/etc/postgresql/16/main/postgresql.conf` tuning dla 16 GB VPS:

```
shared_buffers = 4GB
effective_cache_size = 12GB
maintenance_work_mem = 1GB
work_mem = 16MB
max_connections = 100
checkpoint_completion_target = 0.9
wal_buffers = 16MB
default_statistics_target = 100
random_page_cost = 1.1           # SSD/NVMe
effective_io_concurrency = 200   # NVMe
```

Extensions:
```sql
CREATE EXTENSION "uuid-ossp";
CREATE EXTENSION "pg_trgm";       -- fuzzy search
CREATE EXTENSION "pgcrypto";      -- encryption if needed
```

---

## 8. Redis konfiguracja

`/etc/redis/redis.conf`:
```
maxmemory 2gb
maxmemory-policy allkeys-lru       # cache
save 900 1 300 10 60 10000          # RDB snapshots
appendonly yes                       # AOF dla queue'ów
appendfsync everysec
```

Osobna instancja (port 6380) dla cache jeśli okaże się potrzebne — na razie jedna wystarcza.

---

## 9. Backup strategy

### Codzienny (cron 03:00)

```bash
# Baza
pg_dump -Fc drukarnia_erp -f /mnt/backup/db/$(date +%Y-%m-%d).dump

# Storage
rsync -av --delete /var/www/drukarnia-erp/current/storage/app/private/ \
  /mnt/backup/storage/

# Rotacja (>30 dni)
find /mnt/backup/db -name "*.dump" -mtime +30 -delete
```

Scheduler w Laravel:
```php
$schedule->exec('bash /usr/local/bin/drukarnia-backup.sh')->dailyAt('03:00');
```

### Off-site (codziennie 04:00)

```bash
rclone sync /mnt/backup/ backup-server:drukarnia-erp/ --crypt --transfers 4
```

Szyfrowanie rclone crypt (AES-256) + long passphrase w `/root/.config/rclone/rclone.conf` (`chmod 600`).

### Restore test (raz w tygodniu, automated)

```bash
# Restore do testowej DB
pg_restore -d drukarnia_restore_test /mnt/backup/db/latest.dump
psql -d drukarnia_restore_test -c "SELECT COUNT(*) FROM zamowienia"
# Email reportu Admin
```

**RPO:** 24 h (max utrata).
**RTO:** 4 h (czas odtworzenia z backupu).

---

## 10. Monitoring i alerting

### Laravel Pulse

Wbudowany dashboard `/pulse` — widoczny tylko dla Admina. Karty:
- Slow queries (>1s).
- Slow jobs.
- Slow requests.
- Cache hit ratio.
- User activity.
- Horizon summary.

### Uptime Kuma (self-host)

Osobny mały VPS z Docker:
```yaml
# docker-compose.yml
services:
  uptime-kuma:
    image: louislam/uptime-kuma:1
    ports: ["3001:3001"]
    volumes: ["./data:/app/data"]
```

Monitory:
- HTTPS `https://erp.drukarnia.pl/health` — co 1 min, alert po 2 failach.
- TCP `erp.drukarnia.pl:5432` — Postgres.
- TCP `erp.drukarnia.pl:6379` — Redis.
- Keyword `OK` na `/health` endpoint.
- Cert expiry — alert 30 dni przed.

### Sentry (self-host lub SaaS)

`config/sentry.php` + `composer require sentry/sentry-laravel`. Tylko error level w prod, warning w staging.

### Endpoint `/health`

```php
Route::get('/health', function () {
    try {
        DB::select('SELECT 1');
        Cache::get('health');
        return response('OK', 200);
    } catch (\Throwable $e) {
        return response('FAIL: '.$e->getMessage(), 503);
    }
});
```

### Log aggregation

MVP: `laravel.log` + `logrotate` (30 dni, daily rotation, compress).
Faza 3: rozważyć Loki + Grafana dla dashboardów logów.

---

## 11. Security hardening

### Firewall (ufw)

```bash
ufw default deny incoming
ufw default allow outgoing
ufw allow ssh
ufw allow http
ufw allow https
# Reverb (jeśli zewn.) — domyślnie przez nginx proxy, nie wystawiać
ufw enable
```

### fail2ban

`/etc/fail2ban/jail.local`:
```ini
[sshd]
enabled = true
maxretry = 3
bantime = 1h

[nginx-http-auth]
enabled = true
maxretry = 5
```

### SSH

```bash
# /etc/ssh/sshd_config
PermitRootLogin no
PasswordAuthentication no
AllowUsers deploy admin
```

Klucze SSH z kontrolą `authorized_keys`. Rotacja przy odejściu pracownika.

### Unattended upgrades

```bash
apt install unattended-upgrades
dpkg-reconfigure --priority=low unattended-upgrades
# Auto-security patches nocne
```

### Codzienny skan

`lynis audit system` — email reportu do admina.

---

## 12. CI/CD pełen flow

```
Developer push → GitHub → Actions (test + lint + build)
                             │
                   ┌─────────┴──────────┐
                   │                    │
              PR do main           branch main
                   │                    │
                   │                    ▼
                   │              Deploy staging (auto)
                   │                    │
                   │                    ▼
                   │              Smoke test (Dusk)
                   │                    │
                   ▼                    ▼
              Code review          Ręczny approve
                   │                    │
                   ▼                    ▼
              Merge main          Deploy prod (dep deploy)
                                        │
                                        ▼
                                   Smoke + monitoring
                                        │
                           (w razie fail → auto rollback)
```

### Staging server

Identyczny stack, na subdomenie `staging-erp.drukarnia.pl`. Dane: mockowe (seed + anonimized copy produkcji). Deploy automatyczny z każdego push na `main` (Faza 2).

---

## 13. Disaster recovery

### Scenariusz A: Awaria dysku produkcji

1. Zamów nowy VPS (lub drugi dysk w istniejącym).
2. Install stack (Ansible playbook: `ansible-playbook bootstrap.yml`).
3. Restore Postgres: `pg_restore -d drukarnia_erp /mnt/backup/db/latest.dump`.
4. Restore storage: `rsync -av /mnt/backup/storage/ /var/www/drukarnia-erp/current/storage/app/private/`.
5. DNS cutover: wpis A → nowy IP (TTL 300).
6. Monitoring 24 h.

**Target RTO:** 4 h.

### Scenariusz B: Compromise (włamanie)

1. Odciąć serwer od sieci (`ufw disable incoming`).
2. Snapshot dysku do forensyki.
3. Rotacja wszystkich haseł (APP_KEY, DB, Redis, API tokens dostawców).
4. Restore z backupu sprzed kompromitacji.
5. Patch exploit vectora.
6. Breach notification (RODO, 72 h).

Playbook w `docs/runbooks/compromise.md`.

### Scenariusz C: Błąd migracji destrukcyjnej

1. Auto-rollback Deployer (symlink wstecz).
2. Restore DB z dumpa pre-migration (automatyczny dump przed każdym deployem — `pg_dump` jako `before deploy:migrate` task).

---

## 14. Runbooks

W `docs/runbooks/`:
- `deploy.md` — standardowy deploy.
- `rollback.md` — jak cofnąć.
- `compromise.md` — reakcja na włamanie.
- `disk-full.md` — gdy `/var` > 90%.
- `horizon-stuck.md` — restart workerów.
- `subiekt-offline.md` — fallback.
- `backup-restore.md` — full restore.

Runbooki **testowane raz na kwartał** (chaos drill — symulacja failu + przejście runbook).

---

## 15. Koszty miesięczne (szacunek prod)

| Pozycja | ~EUR/mies. |
|---------|-----------|
| VPS 8 vCPU / 16 GB / 500 GB NVMe (Hetzner Cloud CX51) | 55 |
| Backup VPS (Hetzner CX11 + 1 TB volume) | 15 |
| Domena | 1 |
| Let's Encrypt | 0 |
| Sentry (dev tier lub self-host) | 0–25 |
| Monitoring Uptime Kuma (self-host) | 0 |
| Mailgun 10k emails | 15 |
| SMSAPI pay-as-you-go | 5–20 |
| Subiekt nexo Sfera | licencja Subiekta |
| Meilisearch cloud (opc.) | 0 (self-host) – 30 |

**Razem:** ~100–150 EUR/mies. dla 1 drukarni.

Porównanie: MinIO / S3 dla 200 GB plików: ~5 EUR (S3) ale pełna utrata kontroli → powód rezygnacji.
