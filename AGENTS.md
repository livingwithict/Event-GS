# AGENTS.md

## Critical environment gotcha

**`.env.local` overrides `.env`** when `APP_ENV=local`. If the app behaves differently than `.env` says, check `.env.local` first.

## How to run (composer dev is broken)

`composer dev` tries to run `php artisan horizon` — horizon is in `dont-discover` in `composer.json` and fails. Run manually:

```bash
pkill -9 -f "artisan serve|queue:work|vite"
php artisan config:clear          # mandatory after any .env change
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
- Section import: `Filament\Schemas\Components\Section`
- Tab import for table tabs: `Filament\Schemas\Components\Tabs\Tab`
- `Resource::$navigationGroup` must use `string|UnitEnum|null` type (add `use UnitEnum;`) — the parent trait declares `string|UnitEnum|null`, not `?string`
- `Widget::$view` is non-static: `protected string $view = '...'` (static causes fatal error)
- Empty state: `->emptyStateHeading()` / `->emptyStateDescription()` on tables
- CRUD toasts: override `getCreatedNotification()` / `getSavedNotification()` on create/edit page classes
- Character counters: `->hint(fn ($state) => ($state ? strlen($state) : 0).'/255')`

## Custom Filament theme (Tailwind v4)

Theme lives at `resources/css/filament/admin/theme.css` and is registered via `->viteTheme(...)` in `AdminPanelProvider.php`.

```bash
php artisan make:filament-theme --panel=admin    # scaffold + auto-register
npm run build                                     # compile
```

- **No `tailwind.config.js`** — Tailwind v4 uses `@theme` blocks in CSS
- `@import 'tailwindcss'` + `@source` directives control what files Tailwind scans
- The auto-generated stub imports the vendor: `@import '../../../../vendor/filament/filament/resources/css/theme.css'`

## Auth & roles

- Users have a simple `role` string column — **not** Spatie roles/permissions (even though `spatie/laravel-permission` is a dependency)
- `HasRoleBasedVisibility` trait in `app/Filament/Resources/Concerns/` controls Filament resource visibility — override `getVisibleRoles()` per resource
- Login: `admin@ictfoundation.org.np` / `password` (after `php artisan db:seed --class=RoleSeeder`)

## Navigation & sidebar

Navigation groups are defined in `AdminPanelProvider` via `->navigationGroups([...])` with `NavigationGroup::make()`. Each resource sets `$navigationGroup` (string matching the group label) and `$navigationSort` to order items within a group.

## Notifications (database)

```bash
php artisan make:notifications-table    # creates migration
# Edit migration: use json('data') for PostgreSQL, not text('data')
php artisan migrate
```

Panel config: `->databaseNotifications()` + `->databaseNotificationsPolling('30s')`

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
- See `CLAUDE.md` for full architecture, API routes, service details, scanner app structure, and role matrix
