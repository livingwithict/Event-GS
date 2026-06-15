#!/bin/bash
set -e

# ============================================================
# Event Management — Shared Hosting Deployment Script
# Single directory: everything in the document root
# ============================================================

DOCROOT="/home2/ictechco/events.ictfoundation.org.np"

echo "=== Event Management — Production Deployment ==="
echo ""

cd "$DOCROOT"

# ----------------------------------------------------------
# 1. Install Composer dependencies
# ----------------------------------------------------------
echo "→ Installing Composer dependencies (no dev)..."
composer install --no-dev --optimize-autoloader --no-interaction --ignore-platform-req=ext-intl 2>&1

# ----------------------------------------------------------
# 2. Environment file
# ----------------------------------------------------------
if [ ! -f "$DOCROOT/.env" ]; then
    if [ -f "$DOCROOT/.env.production" ]; then
        echo "→ Copying .env.production → .env..."
        cp "$DOCROOT/.env.production" "$DOCROOT/.env"
    else
        echo "ERROR: No .env or .env.production found."
        exit 1
    fi
    echo ""
    echo "⚠  Edit .env with your credentials: nano $DOCROOT/.env"
    echo "   Then re-run: bash $DOCROOT/deploy.sh"
    exit 0
fi

# ----------------------------------------------------------
# 3. Generate APP_KEY if missing
# ----------------------------------------------------------
if grep -q '^APP_KEY=$' "$DOCROOT/.env"; then
    echo "→ Generating APP_KEY..."
    php artisan key:generate --force
fi

# ----------------------------------------------------------
# 4. Create required directories
# ----------------------------------------------------------
echo "→ Creating required directories..."
mkdir -p storage/framework/cache/data
mkdir -p storage/framework/sessions
mkdir -p storage/framework/views
mkdir -p storage/app/public
mkdir -p storage/app/private
mkdir -p storage/app/backups
mkdir -p storage/logs
mkdir -p bootstrap/cache

# ----------------------------------------------------------
# 5. Create public symlink (so artisan publishes to docroot)
# ----------------------------------------------------------
echo "→ Setting up public symlink..."
rm -f public
ln -s . public

# ----------------------------------------------------------
# 6. Run migrations
# ----------------------------------------------------------
echo "→ Running database migrations..."
php artisan migrate --force

# ----------------------------------------------------------
# 7. Publish all vendor assets
# ----------------------------------------------------------
echo "→ Publishing vendor assets..."
php artisan vendor:publish --tag=filament-assets --force 2>/dev/null || true
php artisan livewire:publish --assets 2>/dev/null || true
php artisan filament:upgrade 2>/dev/null || true

# ----------------------------------------------------------
# 8. Clear and rebuild caches
# ----------------------------------------------------------
echo "→ Clearing old caches..."
php artisan config:clear 2>/dev/null || true
php artisan route:clear 2>/dev/null || true
php artisan view:clear 2>/dev/null || true
php artisan filament:clear-cached-components 2>/dev/null || true

echo "→ Caching config, routes, views..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan filament:optimize

# ----------------------------------------------------------
# 9. Set file permissions
# ----------------------------------------------------------
echo "→ Setting file permissions..."
chmod -R 755 storage bootstrap/cache
chmod 755 artisan

# ----------------------------------------------------------
# Done
# ----------------------------------------------------------
echo ""
echo "============================================"
echo "  Deployment Complete!"
echo "============================================"
echo ""
echo "  Admin:    https://events.ictfoundation.org.np/admin"
echo "  Scanner:  https://events.ictfoundation.org.np/scanner/"
echo "  Health:   https://events.ictfoundation.org.np/up"
echo ""
echo "  Next steps:"
echo "  1. Seed admin user: php artisan db:seed --class=RoleSeeder"
echo "  2. Add cron jobs (cPanel > Cron Jobs):"
echo "     * * * * * cd $DOCROOT && php artisan schedule:run >> /dev/null 2>&1"
echo "     * * * * * cd $DOCROOT && php artisan queue:work --stop-when-empty --tries=3 >> /dev/null 2>&1"
echo "  3. Enable SSL (cPanel > AutoSSL)"
echo "  4. Disable debug: sed -i 's/APP_DEBUG=true/APP_DEBUG=false/' .env && php artisan config:cache"
