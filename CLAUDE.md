# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Event management system for ICT Foundation Nepal. Manages pre-event registrations, on-site QR code scanning, meal coupon validation, bulk email/SMS communications, and historical event archives. Replaces manual Excel workflows.

## Tech Stack

- **Backend:** Laravel 13, PHP 8.3+, PostgreSQL 15
- **Admin Panel:** FilamentPHP v5 (built on Livewire)
- **Scanner App:** React 19 + Vite PWA in `scanner-app/` (separate build, port 5173)
- **QR Scanning:** `html5-qrcode` + `simplesoftwareio/simple-qrcode`
- **Queue:** Database driver (Redis removed for shared hosting; see docs/deployment.md for re-adding)
- **Cache:** Database driver (Redis removed for shared hosting)
- **Excel:** `maatwebsite/excel`
- **Auth:** Laravel Sanctum (token-based for API)
- **Audit:** `spatie/laravel-activitylog`
- **Roles:** `spatie/laravel-permission`

## Local Dev Setup

- PostgreSQL via Homebrew: `DB_HOST=127.0.0.1`, `DB_PORT=5432`, `DB_USERNAME=manojghale` (macOS username, no password)
- Start service: `brew services start postgresql@15`
- Run migrations: `php artisan migrate`
- Seed admin user: `php artisan db:seed --class=RoleSeeder` (creates `admin@ictfoundation.org.np` / `password`)
- Admin panel: `http://localhost:8000/admin`
- Scanner PWA: `http://localhost:5173`

## Commands

```bash
# Full setup from scratch
composer setup                            # install, env, key, migrate, npm

# Dev (all services concurrently: server, queue, logs, vite)
composer dev

# Tests (43 tests, uses in-memory SQLite)
composer test                             # clear config + run tests
php artisan test                          # run all tests
php artisan test tests/Feature/ScanTest   # run single file
php artisan test --parallel               # parallel tests

# Queue (database driver)
php artisan queue:work --tries=3          # process queued jobs
php artisan queue:work --stop-when-empty  # process and exit when empty

# Scanner PWA
cd scanner-app && npm run dev             # dev server (port 5173)
cd scanner-app && npm run build           # production build

# Code quality
./vendor/bin/pint                         # fix code style
./vendor/bin/pint --test                  # check only

# Backup
php artisan db:backup                     # pg_dump with gzip (retains 90 days)
php artisan db:backup --retain=30         # custom retention
```

## Architecture

**Two separate applications sharing one Laravel backend:**
1. **FilamentPHP admin panel** at `/admin` -- CRUD for events, registrations, communications, reporting, archive management
2. **React PWA scanner** (port 5173) -- mobile QR scanning, entry/meal recording

**Filament Resources** (`app/Filament/Resources/`):
- `EventResource` -- event CRUD with year filter, trashed/archive filter, meal usage report, event summary report bulk actions
- `RegistrationResource` -- registration management with CSV export, trashed/archive filter, restore/force-delete
- `CommunicationResource` -- communication logs with resend failed action, bulk CSV delivery report export
- `EventStatsOverview` widget on dashboard

**Filament v5 API notes** (non-obvious, caused issues):
- Forms use `Filament\Schemas\Schema` with `->components([])`, not `Filament\Forms\Form`
- Table row actions use `->recordActions([])`, not `->actions([])`
- Action imports: `Filament\Actions\ViewAction`, `Filament\Actions\EditAction`, etc. (not `Tables\Actions\`)
- Bulk actions: `Filament\Actions\BulkActionGroup`, `Filament\Actions\DeleteBulkAction`

**API surface** (`routes/api.php`):
- Auth: `POST /api/login`, `GET /api/user`, `POST /api/logout`
- Scanner (role: scanner+): `POST /api/scan`, `POST /api/entry`, `POST /api/meal`, `GET /api/guest/search`
- Manager (role: event_manager+): `GET /api/event/{id}/dashboard`, `POST /api/event/{id}/import`, `POST /api/event/{id}/send-invites`
- Reports: `GET /api/reports/attendance/{event}`, `GET /api/reports/noshow/{event}`, `GET /api/reports/duplicate-scans/{event}`, `GET /api/reports/communications/{event}`, `GET /api/reports/meal-usage/{event}`, `GET /api/reports/event-summary/{event}`

**Roles:** `super_admin`, `event_manager`, `scanner`, `viewer` -- stored as `role` column on `users` table, enforced via `EnsureRole` middleware (`app/Http/Middleware/EnsureRole.php`). `spatie/laravel-permission` is installed but role checks use the direct column, not Spatie's role assignment.

**Models** (`app/Models/`): `Event` -> hasMany `Registration` -> hasMany `Communication`. All use `SoftDeletes` + `LogsActivity`.

**App structure:**
- `app/Services/` -- `QRCodeService`, `CommunicationService`
- `app/DTOs/` -- `ScanResponseDTO`
- `app/Jobs/` -- `SendBulkEmail`, `SendBulkSMS`
- `app/Events/` -- `EntryRecorded`, `MealUsed`
- `app/Imports/` -- `RegistrationsImport`
- `app/Exports/` -- `AttendanceExport`, `NoShowExport`, `CommunicationExport`, `MealUsageExport`
- `app/Http/Requests/` -- `ScanRequest`, `EntryRequest`, `MealRequest`, `ImportRegistrationsRequest`
- `app/Http/Middleware/` -- `EnsureRole`, `IdempotentScan` (deduplicates via X-Request-Id, caches 5s)
- `app/Console/Commands/` -- `BackupDatabase`

**Scanning flow:**
```
Scan QR -> POST /api/scan (DB lookup) -> display guest -> POST /api/entry or /api/meal
Entry/Meal writes are idempotent (return false if already recorded)
```

**QR codes:** UUID v4 per registration. `QRCodeService` handles generation and resolution.

**Scanner PWA** (`scanner-app/src/`):
- Pages: `Login`, `Scanner`
- Components: `QrScanner`, `GuestCard`, `ActionButtons`, `SearchFallback`
- Hook: `useAuth` (token management)

**CORS:** `config/cors.php` allows `FRONTEND_URL` origin with credentials. `max_age` 86400.

## Test Structure

Tests use in-memory SQLite (`phpunit.xml`). 43 tests across:
- `tests/Feature/` -- AuthTest, ScanTest, EntryTest, MealTest, CommunicationTest, RegistrationImportTest, ReportTest, IntegrationTest
- `tests/Unit/` -- Model tests (Event, Registration, User), QRCodeServiceTest

## Key Config

- `config/cors.php` -- CORS origins from `FRONTEND_URL` env
- `config/sanctum.php` -- stateful domains
- `.env.example` -- reference for all required env vars

## Design Decisions

- **DTOs** for API responses -- models don't leak to API
- **Event-driven:** Laravel events for audit logging
- **Idempotency:** `recordEntry()` / `recordMeal()` return bool
- **Validation in three layers:** Frontend (React), FormRequest classes, database constraints
- **Archive via soft deletes:** TrashedFilter on Event/Registration resources with restore and force-delete actions
- **No Redis dependency:** Queue and cache use database driver, suitable for shared hosting

## Documentation

- `docs/PRD.md` -- full requirements
- `docs/Event_Management_System_Requirement.md` -- original requirements from ICT Foundation Nepal
- `docs/system-design.md` -- database schema, API endpoints
- `docs/architectural-design.md` -- C4 diagrams, data flows
- `docs/tech-stacks.md` -- technology rationale
- `docs/best-coding-practice.md` -- patterns, testing strategy
- `docs/run-commands.md` -- complete run command reference
- `docs/deployment.md` -- shared hosting deployment guide

## Git Workflow

- Commit format: `feat:`, `fix:`, `docs:`, `chore:`
