# Shared Hosting Deployment Guide

Guide for deploying the Event Management system on shared hosting (tested with Nest Nepal Cloud Babaal).

> **Note:** This setup does not use Redis or Laravel Horizon. Queue and cache use the database driver, which works on shared hosting. See [Future: Redis & Horizon](#future-redis--horizon) for re-adding these when upgrading to a VPS.

## Prerequisites on Hosting

- PHP 8.3+ with extensions: `pdo_pgsql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`
- PostgreSQL 15 database
- Cron job access
- SSH access (recommended) or file manager upload

---

## Step 1: Build the Scanner PWA Locally

```bash
cd scanner-app
npm install
npm run build
cd ..
```

This creates `scanner-app/dist/` with static files.

## Step 2: Upload Files

Upload the entire project to your hosting. The directory structure should be:

```
/home/username/
├── event-management/          # all Laravel files (OUTSIDE public_html)
│   ├── app/
│   ├── bootstrap/
│   ├── config/
│   ├── database/
│   ├── resources/
│   ├── routes/
│   ├── storage/               # must be writable
│   ├── vendor/
│   ├── artisan
│   ├── composer.json
│   ├── .env
│   └── public/               # this becomes your web root
│       ├── index.php
│       ├── .htaccess
│       └── scanner/          # scanner PWA built assets
│           ├── index.html
│           └── assets/
└── public_html/               # → symlink or copy of public/
```

### Option A: Symlink (preferred)

```bash
# Remove default public_html
rm -rf ~/public_html

# Symlink to Laravel's public directory
ln -s ~/event-management/public ~/public_html
```

### Option B: Custom document root

If your hosting allows setting the document root (e.g., cPanel "Change Document Root"):
- Set document root to `/home/username/event-management/public`

### Option C: Manual copy (no SSH)

1. Upload all project files to a directory outside `public_html` (e.g., `~/event-management/`)
2. Copy the contents of `public/` into `public_html/`
3. Edit `public_html/index.php` to update paths:

```php
// Change these lines:
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';

// To match your directory structure:
require __DIR__.'/../event-management/vendor/autoload.php';
$app = require_once __DIR__.'/../event-management/bootstrap/app.php';
```

## Step 3: Copy Scanner Build Output

```bash
# Copy scanner build to public directory
cp -r ~/event-management/scanner-app/dist/* ~/event-management/public/scanner/
```

The scanner PWA will be accessible at `https://yourdomain.com/scanner/`.

## Step 4: Configure Environment

Copy `.env.example` to `.env` and update:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com
FRONTEND_URL=https://yourdomain.com/scanner

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=your_db_name
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password

QUEUE_CONNECTION=database
CACHE_STORE=database
SESSION_DRIVER=database

MAIL_MAILER=mailgun
MAILGUN_DOMAIN=your-domain
MAILGUN_SECRET=your-key

SANCTUM_STATEFUL_DOMAINS=yourdomain.com
```

Generate the app key:
```bash
php artisan key:generate
```

## Step 5: Set Permissions

```bash
chmod -R 755 ~/event-management
chmod -R 775 ~/event-management/storage
chmod -R 775 ~/event-management/bootstrap/cache
```

## Step 6: Run Migrations

```bash
cd ~/event-management
php artisan migrate
php artisan db:seed --class=RoleSeeder
```

## Step 7: Optimize for Production

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Step 8: Set Up Cron for Queue Processing

Add this cron job via cPanel or `crontab -e`:

```bash
* * * * * cd /home/username/event-management && php artisan queue:work --stop-when-empty >> /dev/null 2>&1
```

This runs every minute, processes all pending jobs, then exits.

Also add the Laravel scheduler cron:

```bash
* * * * * cd /home/username/event-management && php artisan schedule:run >> /dev/null 2>&1
```

## Step 9: Configure CORS for Scanner

In `.env`, make sure:

```env
FRONTEND_URL=https://yourdomain.com/scanner
SANCTUM_STATEFUL_DOMAINS=yourdomain.com
```

## URLs After Deployment

| Service | URL |
|---------|-----|
| Admin panel | `https://yourdomain.com/admin` |
| Scanner PWA | `https://yourdomain.com/scanner/` |

---

## Troubleshooting

### 500 Internal Server Error
- Check `storage/logs/laravel.log`
- Verify `storage/` and `bootstrap/cache/` are writable (775)
- Run `php artisan config:cache`

### Blank page
- Check `public/.htaccess` exists
- Verify PHP version is 8.3+
- Check `APP_KEY` is set in `.env`

### Queue jobs not processing
- Verify the cron job is running: `php artisan queue:failed` to check for failures
- Check `QUEUE_CONNECTION=database` in `.env`

### Scanner not connecting to API
- Verify `FRONTEND_URL` and `SANCTUM_STATEFUL_DOMAINS` match your domain
- Check `config/cors.php` allows your origin

### Database connection error
- Verify PostgreSQL credentials in `.env`
- Check `DB_HOST` is correct (often `127.0.0.1` or `localhost` on shared hosting)
- Ensure `pdo_pgsql` extension is enabled: `php -m | grep pgsql`

---

## Future: Redis & Horizon

Redis and Laravel Horizon were intentionally removed for shared hosting compatibility. They provide real-time scanning cache optimization and queue monitoring, but are not required at current event scale.

**When to re-add:** When migrating to a VPS or dedicated server with Redis available.

**What was removed:**
- `laravel/horizon` and `predis/predis` packages
- `app/Providers/HorizonServiceProvider.php`
- `app/Listeners/UpdateRedisCache.php` (cached QR scan results and event stats in Redis)
- `config/horizon.php`
- Event listeners for `EntryRecorded` and `MealUsed` that updated Redis cache

**Current replacements:**
- Queue: `database` driver (jobs table) + cron-based worker
- Cache: `database` driver
- Scanning: direct PostgreSQL lookup (was Redis lookup with DB fallback)

Full re-implementation details are tracked in the project memory (`redis-horizon-removal.md`) for future reference.
