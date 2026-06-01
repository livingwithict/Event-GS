# Run Commands

Quick reference for running the Event Management system locally and in production.

> **Note:** This project does not require Redis or Laravel Horizon. Queue and cache use the database driver. See `docs/deployment.md` for details on re-adding Redis when migrating to a VPS.

## Prerequisites

- PHP 8.3+
- PostgreSQL 15
- Node.js 18+
- Composer 2+

---

## First-Time Setup

### Option A: One-command setup

```bash
composer setup
```

This runs `composer install`, copies `.env.example` to `.env`, generates an app key, runs migrations, installs npm deps, and builds the scanner app.

### Option B: Manual setup

```bash
# 1. Install PHP dependencies
composer install

# 2. Configure environment
cp .env.example .env
php artisan key:generate

# 3. Edit .env with your database credentials, then migrate
php artisan migrate

# 4. Seed the admin user
php artisan db:seed --class=RoleSeeder

# 5. Install and build the scanner PWA
cd scanner-app
npm install
npm run build
cd ..
```

### Docker setup

```bash
docker compose up -d
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed --class=RoleSeeder
```

This starts PostgreSQL 15, the Laravel app (port 8000), a queue worker, and an Nginx reverse proxy (port 80).

---

## Daily Development

### Start everything (recommended)

```bash
composer dev
```

Runs four processes concurrently via `npx concurrently`:

| Process | Command | Purpose |
|---------|---------|---------|
| server | `php artisan serve` | Laravel dev server (port 8000) |
| queue | `php artisan queue:work --tries=3 --stop-when-empty` | Process queued jobs |
| logs | `php artisan pail --timeout=0` | Stream application logs |
| vite | `npm run dev` | Frontend hot reload |

Stop with `Ctrl+C` (kills all processes).

### Start services individually

```bash
# Backend only
php artisan serve

# Queue worker (required for emails, SMS, bulk imports)
php artisan queue:work --tries=3

# Scanner PWA dev server (separate terminal)
cd scanner-app && npm run dev
```

### URLs

| Service | URL |
|---------|-----|
| Admin panel | http://localhost:8000/admin |
| Scanner PWA | http://localhost:5173 |

---

## Testing

```bash
# Run all tests (43 tests, in-memory SQLite)
php artisan test

# Clear config cache first
composer test

# Run a single test file
php artisan test tests/Feature/ScanTest

# Run tests in parallel
php artisan test --parallel

# Run only unit or feature tests
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature
```

Tests use SQLite in-memory, sync queue driver, and array mail/cache -- no external services needed.

---

## Code Quality

```bash
# Fix code style (Laravel Pint)
./vendor/bin/pint

# Check style without fixing
./vendor/bin/pint --test
```

---

## Queue & Background Jobs

```bash
# Start queue worker (database driver)
php artisan queue:work --tries=3

# Process jobs and exit when queue is empty
php artisan queue:work --stop-when-empty

# Process a single job
php artisan queue:work --once

# Monitor failed jobs
php artisan queue:failed

# Retry a failed job
php artisan queue:retry {id}

# Retry all failed jobs
php artisan queue:retry all
```

Queue jobs are stored in the `jobs` database table. Bulk emails and SMS are dispatched to the queue automatically.

For shared hosting, add a cron to process jobs:
```bash
* * * * * cd /path/to/project && php artisan queue:work --stop-when-empty >> /dev/null 2>&1
```

---

## Scanner PWA

```bash
cd scanner-app

# Dev server with hot reload (port 5173)
npm run dev

# Production build
npm run build

# Preview production build
npm run preview

# Lint
npm run lint
```

---

## Database

```bash
# Run migrations
php artisan migrate

# Rollback last batch
php artisan migrate:rollback

# Fresh migrate (drops all tables, re-runs)
php artisan migrate:fresh

# Backup (compressed pg_dump, stored in storage/app/backups/)
php artisan db:backup

# Backup with 30-day retention
php artisan db:backup --retain=30
```

---

## Cache & Config

```bash
# Clear all caches
php artisan optimize:clear

# Individual clears
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

# Cache for production
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## Environment Variables

Key variables in `.env` (see `.env.example` for full reference):

| Variable | Default | Purpose |
|----------|---------|---------|
| `APP_URL` | `http://localhost:8000` | Backend URL |
| `FRONTEND_URL` | `http://localhost:5173` | Scanner PWA URL (used for CORS) |
| `DB_CONNECTION` | `pgsql` | Database driver |
| `DB_HOST` | `127.0.0.1` | PostgreSQL host |
| `DB_DATABASE` | `event_management` | Database name |
| `QUEUE_CONNECTION` | `database` | Queue driver |
| `CACHE_STORE` | `database` | Cache driver |
| `MAIL_MAILER` | `mailgun` | Email driver |
| `MAILGUN_DOMAIN` | — | Mailgun domain |
| `MAILGUN_SECRET` | — | Mailgun API key |
| `SMS_DRIVER` | `sparrow` | SMS provider |
| `SPARROW_SMS_TOKEN` | — | Sparrow SMS API token |
| `SANCTUM_STATEFUL_DOMAINS` | `localhost:5173` | Sanctum stateful domains |
