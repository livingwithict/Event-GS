#!/usr/bin/env bash
#
# Deploy the event system on 172.16.52.45.
#
# Install once:
#   ln -sf /var/www/event-gs/deploy.sh /usr/local/bin/deploy-eventgs
#   chmod +x /var/www/event-gs/deploy.sh
#
# Then after every push:
#   deploy-eventgs
#
set -euo pipefail

APP_DIR=/var/www/event-gs
PHP_FPM=php8.4-fpm
DOMAIN=events.globalspark.com.np

cd "$APP_DIR"

say() { printf '\n\033[1;34m→ %s\033[0m\n' "$1"; }
ok()  { printf '\033[1;32m✓ %s\033[0m\n' "$1"; }
die() { printf '\n\033[1;31m✗ %s\033[0m\n' "$1" >&2; exit 1; }

# Refuse to deploy on top of hand-edits: they would be silently reverted, or
# would block the pull halfway through.
if [[ -n "$(git status --porcelain --untracked-files=no)" ]]; then
    git status --short
    die "Uncommitted changes in $APP_DIR. Commit or discard them, then retry."
fi

say "Pulling"
BEFORE=$(git rev-parse HEAD)
git pull --ff-only
AFTER=$(git rev-parse HEAD)

if [[ "$BEFORE" == "$AFTER" ]]; then
    echo "  already at $(git log --oneline -1)"
else
    git --no-pager log --oneline "$BEFORE..$AFTER" | sed 's/^/  /'
fi

changed() { git diff --name-only "$BEFORE" "$AFTER" | grep -qE "$1"; }

say "PHP dependencies"
if [[ "$BEFORE" != "$AFTER" ]] && changed '^(composer\.(json|lock))$'; then
    COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-interaction
else
    echo "  unchanged, skipping composer"
fi

say "Frontend assets"
if [[ ! -d node_modules ]] || { [[ "$BEFORE" != "$AFTER" ]] && changed '^(package(-lock)?\.json)$'; }; then
    npm ci
fi
if [[ ! -f public/build/manifest.json ]] || [[ "$BEFORE" != "$AFTER" ]]; then
    npm run build
else
    echo "  unchanged, skipping build"
fi

say "Database"
php artisan migrate --force

say "Caches"
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan filament:optimize

# storage:link is a no-op when the symlink already exists.
[[ -L public/storage ]] || php artisan storage:link

say "Permissions"
chown -R www-data:www-data storage bootstrap/cache

say "Reloading PHP-FPM"
systemctl reload "$PHP_FPM"

say "Health check"
CODE=$(curl -s -o /dev/null -w '%{http_code}' -H "Host: $DOMAIN" http://127.0.0.1/admin/login)
[[ "$CODE" == "200" ]] || die "/admin/login returned $CODE — check storage/logs/laravel-$(date +%F).log"
ok "admin/login 200"

# Anything the app emits as http:// breaks the padlock on an https page.
INSECURE=$(curl -s -H "Host: $DOMAIN" http://127.0.0.1/admin/login \
    | grep -oE 'http://[^"'"'"' ]+' | grep -v 'w3.org' || true)
if [[ -n "$INSECURE" ]]; then
    printf '\033[1;33m! mixed content:\033[0m\n%s\n' "$INSECURE"
fi

QUEUED=$(php artisan tinker --execute='echo DB::table("jobs")->count();' 2>/dev/null | tail -1)
echo "  queued jobs: ${QUEUED:-?}"

ok "Deployed $(git log --oneline -1)"
