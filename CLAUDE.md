# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Event management system for ICT Foundation Nepal. Manages pre-event registrations, on-site QR code scanning, meal coupon validation, bulk email/SMS communications, and historical event archives. Replaces manual Excel workflows.

## Tech Stack

- **Backend:** Laravel 13, PHP 8.3+, PostgreSQL 15
- **Admin Panel:** FilamentPHP v5 (built on Livewire)
- **Scanner App:** React 19 + Vite PWA in `scanner-app/` (separate build, port 5173)
- **PDF:** `dompdf/dompdf` v3 (barryvdh/laravel-dompdf is incompatible with Laravel 13 — use dompdf directly)
- **QR:** `html5-qrcode` (scanner) + `simplesoftwareio/simple-qrcode` (backend)
- **Excel:** `maatwebsite/excel`
- **Auth:** Laravel Sanctum (token-based for API)
- **Audit:** `spatie/laravel-activitylog`
- **Queue/Cache:** Database driver (no Redis — suitable for shared hosting)
- **Payment:** Connect IPS gateway (Nepal)

## Local Dev Setup

- PostgreSQL via Homebrew: `DB_HOST=127.0.0.1`, `DB_PORT=5432`, `DB_USERNAME=manojghale` (macOS username, no password)
- Start service: `brew services start postgresql@15`
- Run migrations: `php artisan migrate`
- Seed users: `php artisan db:seed --class=RoleSeeder` (creates 6 demo users for all roles, all with password `password`)
- Admin panel: `http://localhost:8000/admin` (login: `admin@ictfoundation.org.np`)
- Scanner PWA: `http://localhost:5173`

## Commands

```bash
composer setup                            # install, env, key, migrate, npm
composer dev                              # server + queue + logs + vite concurrently
composer test                             # config:clear + php artisan test
php artisan test tests/Feature/ScanTest   # run single test file
php artisan test --parallel               # parallel tests

php artisan queue:work --tries=3          # process queued jobs
php artisan queue:work --stop-when-empty  # process and exit

cd scanner-app && npm run dev             # PWA dev server (port 5173)
cd scanner-app && npm run build           # PWA production build

./vendor/bin/pint                         # fix code style
./vendor/bin/pint --test                  # check only

php artisan db:backup                     # pg_dump with gzip (retains 90 days)
php artisan db:backup --retain=30         # custom retention
```

## Architecture

**Two applications sharing one Laravel backend:**
1. **FilamentPHP admin panel** at `/admin` — CRUD, reporting, label printing, import tracking
2. **React PWA scanner** (port 5173) — mobile QR scanning, entry/meal/card delivery recording

### Filament v5 API (non-obvious — has caused repeated issues)

- Forms: `Filament\Schemas\Schema` with `->components([])`, **not** `Filament\Forms\Form`
- Table row actions: `->recordActions([])`, **not** `->actions([])`
- Action imports: `Filament\Actions\ViewAction`, `Filament\Actions\EditAction` etc. (**not** `Tables\Actions\`)
- Bulk actions: `Filament\Actions\BulkActionGroup`, `Filament\Actions\DeleteBulkAction`
- ChartWidget: `$heading` and `$maxHeight` are **non-static** properties (declaring static causes fatal error)
- `Resource::$navigationGroup` must not be declared as a property — causes type error with `UnitEnum`
- View-only resources: use empty form schema `return $schema->components([])`, not infolist

### Roles & Access Control

**6 roles** stored as `role` string column on users (not Spatie traits):
| Role | Filament Access | Scope |
|------|----------------|-------|
| `super_admin` | Full | All resources |
| `event_manager` | Full | All resources |
| `registration_staff` | Limited | Events (read), Registrations, Import Batches, Communications (read) |
| `finance` | Limited | Events (read), Payments, Registrations (read) |
| `scanner` | None | API-only (scan/entry/meal) |
| `viewer` | None | API-only |

Implementation: `HasRoleBasedVisibility` trait in `app/Filament/Resources/Concerns/` — override `getVisibleRoles()` per resource. `EnsureFilamentAccess` middleware blocks non-admin roles from `/admin`. `EnsureRole` middleware enforces roles on API routes.

### Filament Resources

| Resource | Purpose |
|----------|---------|
| EventResource | Event CRUD, year/trashed filters, meal usage report, event summary, import CSV action |
| RegistrationResource | Registration CRUD, CSV export, ticket download, label print, trashed filter |
| ParticipantCategoryResource | Category management per event |
| ScanActionTypeResource | Configurable scan actions per event |
| PaymentResource | Payment list with status filters, CSV export |
| CommunicationResource | Comm logs, resend failed, bulk CSV delivery report |
| LabelTemplateResource | Label template CRUD per event |
| ImportBatchResource | Import history with status/error tracking |

### API Routes (`routes/api.php`)

- **Auth:** `POST /login`, `GET /user`, `POST /logout`
- **Scanner** (scanner, event_manager, super_admin, registration_staff): `/scan`, `/entry`, `/meal`, `/scan-action`, `/guest/search`
- **Manager+** (event_manager, super_admin, registration_staff, finance): `/event/{id}/dashboard`, all report endpoints
- **Manager-only** (event_manager, super_admin): `/event/{id}/import`, `/event/{id}/send-invites`
- **Reports:** attendance, noshow, duplicate-scans, communications, meal-usage, event-summary, event-summary-pdf, payments, scanner-activity, category-summary (all accept `?format=xlsx`)

### Web Routes (`routes/web.php`)

- `/event/{slug}/register` — public self-registration with Connect IPS payment
- `/checkin/t/{token}` — QR code verification page
- `/ticket/{token}` and `/ticket/{token}/download` — HTML/PDF ticket view
- `/labels` — label printing endpoint

### Models & Relationships

`Event` → hasMany `Registration` → hasMany `Communication`. All use `SoftDeletes` + `LogsActivity`.
Additional: `Payment`, `ParticipantCategory`, `ScanActionType`, `ScanLog`, `LabelTemplate`, `ImportBatch`, `ImportError`.

### Key Services

| Service | Purpose |
|---------|---------|
| `QRCodeService` | QR generation and resolution (UUID v4 per registration) |
| `CommunicationService` | Email/SMS dispatch with type-specific templates |
| `TicketService` | PDF ticket generation via dompdf (A6 landscape) |
| `LabelService` | Label sheet PDF generation, mark-as-printed tracking |
| `ConnectIPSService` | Connect IPS payment gateway (SHA256 + RSA + base64 token) |

### Scheduled Tasks (`routes/console.php`)

- `db:backup` — daily at 02:00
- `event:send-reminders` — daily at 09:00
- `event:send-thankyou` — daily at 10:00

### Scanning Flow

```
Scan QR → POST /api/scan (DB lookup by UUID) → display guest → POST /api/entry|/meal|/scan-action
Entry/Meal writes are idempotent (return false if already recorded)
```

### Scanner PWA (`scanner-app/src/`)

- Pages: `Login`, `Scanner`
- Components: `QrScanner`, `GuestCard`, `ActionButtons`, `SearchFallback`
- Hook: `useAuth` (token management)
- CORS: `config/cors.php` allows `FRONTEND_URL` origin with credentials

## Test Structure

Tests use in-memory SQLite (`phpunit.xml`). 45 tests across:
- `tests/Feature/` — AuthTest, ScanTest, EntryTest, MealTest, CommunicationTest, RegistrationImportTest, ReportTest, IntegrationTest
- `tests/Unit/` — UserModelTest, EventModelTest, RegistrationModelTest, QRCodeServiceTest

## Design Decisions

- **DTOs** for API responses — models don't leak to API
- **Event-driven:** Laravel events (`EntryRecorded`, `MealUsed`) for audit logging
- **Idempotency:** `recordEntry()` / `recordMeal()` return bool
- **Archive via soft deletes:** TrashedFilter with restore/force-delete actions
- **dompdf directly:** barryvdh/laravel-dompdf requires `illuminate/support ^6-^11`, incompatible with Laravel 13
- **Simple role column:** No Spatie migration — string `role` column suffices for this project's scale
- **Database queue/cache:** No Redis dependency, suitable for shared hosting

## Git Workflow

- Commit format: `feat:`, `fix:`, `docs:`, `chore:`
