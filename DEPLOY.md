# Production Deployment — Silver Lining VMs

Target: **https://events.globalspark.com.np** (A record already points to
`163.223.99.213`, TTL 300).

> **PostgreSQL only.** Seven migrations use `jsonb`, queries use `ilike` and
> `EXTRACT(...)::int`, and three unique indexes are partial (`WHERE deleted_at IS
> NULL`). The `.44` box runs PostgreSQL 14, which supports all of it.

## Infrastructure

```
internet → 163.223.99.213 (NAT) → 172.16.52.46   public VM: nginx 80/443, certbot
                                         ↓ proxy
                                    172.16.52.45   app VM: this app + Node/PM2 apps
                                    172.16.52.44   database VM: PostgreSQL 14
```

| Host | Role | Notes |
|---|---|---|
| `.46` (`163.223.99.213`) | public entry, SSL | vhosts: `ict-award`, `listmonk`, `dnc-live` |
| `.45` | app | Ubuntu 22.04.5, `/var/www/<app>`, ufw: 22, 41447, 10050, 80, 443 |
| `.44` | database | Ubuntu 22.04.1, PostgreSQL 14, listens `localhost,172.16.52.44` |

SSH runs on port **41447** everywhere. Only `.46` is reachable from outside.

```bash
ssh -p 41447 root@163.223.99.213        # → .46
ssh -p 41447 root@172.16.52.45          # from .46 → app VM
ssh -p 41447 root@172.16.52.44          # from .46 → database VM
```

This app runs on `.45` alongside the existing Node/PM2 apps — PHP-FPM and Node
coexist, nginx routes by vhost.

## 1. Database — on `.44`

```bash
sudo -u postgres psql <<'SQL'
CREATE DATABASE eventgs_db;
CREATE USER eventgs_user WITH ENCRYPTED PASSWORD 'CHANGE-ME';
GRANT ALL PRIVILEGES ON DATABASE eventgs_db TO eventgs_user;
SQL
sudo -u postgres psql -d eventgs_db -c 'GRANT ALL ON SCHEMA public TO eventgs_user;'

echo 'host  eventgs_db  eventgs_user  172.16.52.45/32  scram-sha-256' \
  >> /etc/postgresql/14/main/pg_hba.conf
systemctl restart postgresql
ufw allow from 172.16.52.45 to any port 5432
```

Verify from `.45`: `psql -h 172.16.52.44 -U eventgs_user -d eventgs_db -c '\conninfo'`

## 2. PHP 8.4 — on `.45`

Ubuntu 22.04 ships PHP 8.1, so use the ondrej PPA:

```bash
apt update && apt install -y software-properties-common
add-apt-repository -y ppa:ondrej/php && apt update

apt install -y php8.4-fpm php8.4-pgsql php8.4-mbstring php8.4-xml php8.4-curl \
               php8.4-zip php8.4-gd php8.4-bcmath php8.4-intl php8.4-imagick \
               unzip git
systemctl enable --now php8.4-fpm
php -v && php -m | grep -E 'imagick|pgsql'
```

> **imagick is not optional** — QR codes render as PNG through it. Without it
> every label and ticket fails.

Composer:

```bash
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
```

## 3. Application — on `.45`

```bash
mkdir -p /var/www && cd /var/www
git clone https://github.com/bishranta/event-gs.git event-gs
cd event-gs

composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
```

Then edit `.env`:

| Variable | Value |
|---|---|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | `https://events.globalspark.com.np` |
| `APP_TIMEZONE` | `Asia/Kathmandu` |
| `DB_CONNECTION` | `pgsql` |
| `DB_HOST` | `172.16.52.44` |
| `DB_PORT` | `5432` |
| `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | from step 1 |
| `SESSION_DRIVER` | `database` |
| `QUEUE_CONNECTION` | `database` |
| `CACHE_STORE` | `database` |
| `MAIL_MAILER` | `resend` |
| `RESEND_API_KEY` | `re_...` (Sending access) |
| `MAIL_FROM_ADDRESS` | `invitation@digitalconclave.org` |
| `MAIL_FROM_NAME` | `Digital Nepal Conclave` |
| `MAIL_REPLY_TO_ADDRESS` | a real inbox |
| `SMS_DRIVER` | `sparrow` (or `log` to disable) |

Finish:

```bash
php artisan migrate --force
php artisan db:seed --class=RoleSeeder
php artisan storage:link
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan filament:optimize

chown -R www-data:www-data /var/www/event-gs/storage /var/www/event-gs/bootstrap/cache
```

Without `storage:link`, event logos and the invitation card silently fail to load.

## 4. nginx on `.45`

```nginx
# /etc/nginx/sites-available/event-gs
server {
    listen 80;
    server_name events.globalspark.com.np;
    root /var/www/event-gs/public;
    index index.php;

    client_max_body_size 50M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_read_timeout 120;
    }

    location ~ /\.(?!well-known).* { deny all; }
}
```

```bash
ln -sf /etc/nginx/sites-available/event-gs /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx
```

## 5. nginx on `.46` (public entry)

```nginx
# /etc/nginx/sites-available/event-gs
server {
    listen 80;
    listen [::]:80;
    server_name events.globalspark.com.np;
    client_max_body_size 50M;

    location / {
        proxy_pass http://172.16.52.45:80;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_read_timeout 120;
    }
}
```

```bash
ln -sf /etc/nginx/sites-available/event-gs /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx
certbot --nginx -d events.globalspark.com.np
```

Because traffic arrives over a proxy, Laravel must trust it. In
`bootstrap/app.php` the middleware group already runs behind `.46`; if links come
out as `http://`, set `TrustProxies` to `*`.

## 6. Scheduled work — on `.45`

```bash
crontab -e
```

```
* * * * * cd /var/www/event-gs && php artisan schedule:run >> /dev/null 2>&1
* * * * * cd /var/www/event-gs && php artisan queue:work --queue=high,default --stop-when-empty --tries=3 >> /dev/null 2>&1
```

> `--queue=high,default` is required. Bulk invitation emails go onto the `high`
> queue; a worker watching only `default` leaves them queued forever, silently.

## 7. Verify

```bash
php artisan about                    # env production, debug off, timezone Asia/Kathmandu
php -m | grep imagick
curl -I https://events.globalspark.com.np
```

In the browser:

1. Log in at `/admin` as `admin@ictfoundation.org.np` — **change every password**.
2. A fresh record's timestamp matches the wall clock.
3. Registrations → **Preview Label**: QR renders.
4. Registrations → **Ticket**: invitation card opens with name and QR.
5. Communications → **Send Emails** to one test guest; log shows **sent**.
6. Scan that guest in **Scan Station**; entry is recorded.

## Redeploying

```bash
cd /var/www/event-gs && git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan filament:optimize
```

## Troubleshooting

| Symptom | Cause |
|---|---|
| Emails queue but never send | queue cron missing, or missing `--queue=high,default` — check `select count(*) from jobs;` |
| `cURL error 60` on send | no CA bundle; point `curl.cainfo`/`openssl.cafile` at `cacert.pem` |
| Label/ticket QR errors | `php8.4-imagick` missing |
| Times 5h45m early | `APP_TIMEZONE` unset, or config cached before setting it |
| 403 for every login | `users.role` not one of the six in `App\Enums\Role` |
| `psql` hangs from `.45` | ufw on `.44` has no rule for `172.16.52.45` |
| Migration fails on `jsonb` | wrong database engine — must be PostgreSQL |
