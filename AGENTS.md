# AGENTS.md

## Critical environment gotcha

**`.env.local` overrides `.env`** when `APP_ENV=local`. If the app behaves differently than `.env` says, check `.env.local` first. This project ships with `.env.local` hardcoding `DB_CONNECTION=sqlite` — that will silently override any PostgreSQL settings in `.env`.

## How to run (composer dev is broken)

`composer dev` tries to run `php artisan horizon` which fails — horizon is in `dont-discover` in `composer.json`. Run manually:

```bash
# Kill stale processes first
pkill -9 -f "artisan serve|queue:work|vite"

# Clear cached config (critical after any .env change)
php artisan config:clear

# Start all three
php -d display_errors=Off artisan serve --port=8000 &
php artisan queue:work --tries=3 &
npm run dev &
```

## Database

- **Dev:** PostgreSQL 15 (`event_management` db). Code uses `EXTRACT(HOUR FROM ...)::int` — **will not work with SQLite.**
- **Test:** In-memory SQLite (set in `phpunit.xml`).
- **Production:** MySQL (cPanel shared hosting, see `DEPLOY.md`).
- `config:clear` is mandatory after switching databases — a running server caches the old connection.

## Filament v5 — non-obvious API

- Forms: `Filament\Schemas\Schema` with `->components([])`, **not** `Filament\Forms\Form`
- Table row actions: `->recordActions([])`, **not** `->actions([])`
- Action imports: `Filament\Actions\*` (not `Tables\Actions\*`)
- ChartWidget: `$heading` and `$maxHeight` are **non-static**
- `Resource::$navigationGroup` cannot be declared as a property

## Auth & roles

- Users have a simple `role` string column — **not** Spatie roles/permissions (even though `spatie/laravel-permission` is a dependency)
- `HasRoleBasedVisibility` trait in `app/Filament/Resources/Concerns/` controls Filament resource visibility
- Login: `admin@ictfoundation.org.np` / `password` (after `php artisan db:seed --class=RoleSeeder`)

## Running tests

```bash
composer test                          # config:clear + php artisan test
php artisan test tests/Feature/ScanTest  # single file
php artisan test --parallel            # parallel
./vendor/bin/pint --test               # lint only
```

## Key conventions

- PDF: `dompdf/dompdf` directly (not barryvdh/laravel-dompdf — incompatible with Laravel 13)
- Queue/Cache: database driver (no Redis)
- Commit format: `feat:`, `fix:`, `docs:`, `chore:`
- See `CLAUDE.md` for full architecture, API routes, and service details
