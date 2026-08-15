# Production Deployment

> **The app requires PostgreSQL.** Seven migrations use `jsonb`, queries use `ilike`
> and `EXTRACT(...)::int`, and three unique indexes are partial (`WHERE deleted_at
> IS NULL`). None of that works on MySQL. Earlier versions of this guide said
> MySQL — that was wrong and would fail on the first migration.

## Server structure

```
/home2/ictechco/
├── event-app/                              ← Laravel app (NOT web-accessible)
│   ├── storage/                            ← must be writable
│   ├── bootstrap/cache/                    ← must be writable
│   ├── .env                                ← production config
│   └── deploy.sh
└── events.ictfoundation.org.np/            ← document root
    ├── index.php
    ├── .htaccess
    ├── build/
    └── storage -> ../event-app/storage/app/public/
```

## 1. Subdomain and database

1. **cPanel → Subdomains**: create the subdomain, document root
   `/home2/ictechco/events.ictfoundation.org.np/`.
2. Create a **PostgreSQL** database and user, and grant all privileges.

## 2. PHP version and extensions

**cPanel → MultiPHP Manager**: PHP **8.4** for the subdomain.

**cPanel → Select PHP Version**, enable:

`pdo_pgsql`, `pgsql`, `mbstring`, `gd`, **`imagick`**, `bcmath`, `zip`, `openssl`,
`tokenizer`, `xml`, `curl`, `intl`, `fileinfo`

> **imagick is not optional.** QR codes are rendered as PNG through it — without
> it every label and ticket fails with *"You need to install the imagick
> extension"*.

## 3. Environment

Copy `.env.example` to `.env` and set:

| Variable | Value | Notes |
|---|---|---|
| `APP_ENV` | `production` | |
| `APP_DEBUG` | `false` | Never true in production |
| `APP_KEY` | leave empty | `deploy.sh` generates it |
| `APP_URL` | `https://events.ictfoundation.org.np` | No trailing slash |
| `APP_TIMEZONE` | `Asia/Kathmandu` | **Without this every timestamp is 5h45m early** |
| `DB_CONNECTION` | `pgsql` | Not mysql |
| `DB_HOST` / `DB_PORT` | `localhost` / `5432` | |
| `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | from step 1 | |
| `SESSION_DRIVER` | `database` | `file` also fine |
| `QUEUE_CONNECTION` | `database` | Emails are queued — see step 6 |
| `MAIL_MAILER` | `resend` | |
| `RESEND_API_KEY` | `re_...` | Resend → API Keys, **Sending access** |
| `MAIL_FROM_ADDRESS` | `invitation@digitalconclave.org` | Domain must be **Verified** in Resend |
| `MAIL_FROM_NAME` | `Digital Nepal Conclave` | |
| `MAIL_REPLY_TO_ADDRESS` | a real inbox | The From address cannot receive mail |
| `SMS_DRIVER` | `sparrow` | `log` sends nothing |
| `SPARROW_SMS_TOKEN` | your token | |

## 4. Install and migrate

```bash
cd /home2/ictechco/event-app
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan db:seed --class=RoleSeeder
php artisan storage:link          # logos, banners, invitation card artwork
```

Without `storage:link`, event logos and the invitation card silently fail to load.

## 5. Caches

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan filament:optimize
```

Re-run these after **every** deploy — a cached config ignores later `.env` edits.

## 6. Cron jobs

**cPanel → Cron Jobs**, both every minute:

```
* * * * * cd /home2/ictechco/event-app && php artisan schedule:run >> /dev/null 2>&1
* * * * * cd /home2/ictechco/event-app && php artisan queue:work --queue=high,default --stop-when-empty --tries=3 >> /dev/null 2>&1
```

> `--queue=high,default` is required. Bulk invitation emails are dispatched onto
> the `high` queue; a worker that only watches `default` leaves them queued
> forever, with no error anywhere.

## 7. SSL

**cPanel → SSL/TLS Status** or AutoSSL for the subdomain. The HTTPS redirect is
already in `.htaccess`.

## 8. Verify before the event

```bash
php artisan about                       # timezone Asia/Kathmandu, env production, debug off
php -m | grep -i imagick                # must print imagick
php artisan tinker --execute="print(config('mail.default'));"   # resend
```

Then in the browser:

1. Log in and confirm the timestamp on a fresh record matches the wall clock.
2. Registrations → **Preview Label** — the QR must render, not error.
3. Registrations → **Ticket** — the invitation card opens with name and QR.
4. Communications → **Send Emails** to one test guest, then check the
   Communications log shows **sent**, and that the mail arrives.
5. Scan that guest's code in **Scan Station** and confirm entry is recorded.

## Troubleshooting

**Emails queue but never send** — the queue worker cron is missing or lacks
`--queue=high,default`. Check `select count(*) from jobs;`.

**cURL error 60 / SSL certificate problem** — the host has no CA bundle. Download
`https://curl.se/ca/cacert.pem` and point `curl.cainfo` and `openssl.cafile` at it
in `php.ini`.

**Labels/tickets error on QR** — imagick missing (step 2).

**Times are 5h45m early** — `APP_TIMEZONE` missing, or config cached before it was
set. Set it, then `php artisan config:cache`.

**Login returns 403 for everyone** — the user's `role` is not one of the six in
`App\Enums\Role`. Check `select distinct role from users;`.

**Migration fails on `jsonb` or `ILIKE`** — the database is MySQL, not PostgreSQL.
