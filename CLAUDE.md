# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Event management system for ICT Foundation Nepal. Manages pre-event registrations, on-site QR code scanning, meal coupon validation, bulk email/SMS communications, and historical event archives. Replaces manual Excel workflows.

## Tech Stack

- **Backend:** Laravel 13, PHP 8.2+, PostgreSQL 15, Redis 7
- **Admin Panel:** FilamentPHP v5 (built on Livewire)
- **Scanner App:** React 18 + Vite PWA in `scanner-app/` (separate build, port 5173)
- **QR Scanning:** `html5-qrcode` + `simplesoftwareio/simple-qrcode`
- **Queue:** Laravel Horizon (Redis driver)
- **Excel:** `maatwebsite/excel`
- **Auth:** Laravel Sanctum (token-based for API)
- **Audit:** `spatie/laravel-activitylog`
- **Roles:** `spatie/laravel-permission`

## Commands

```bash
# Tests (43 tests, uses in-memory SQLite)
php artisan test                          # run all tests
php artisan test tests/Feature/ScanTest   # run single file
php artisan test --parallel               # parallel tests

# Queue
php artisan horizon                       # start queue worker (requires Redis)

# Scanner PWA
cd scanner-app && npm run dev             # dev server (port 5173)
cd scanner-app && npm run build           # production build

# Code quality
./vendor/bin/pint                         # fix code style
```

## Architecture

**Two separate applications sharing one Laravel backend:**
1. **FilamentPHP admin panel** at `/admin` -- CRUD for events, registrations, communications, reporting
2. **React PWA scanner** (port 5173) -- mobile QR scanning, entry/meal recording

**Filament Resources** (`app/Filament/Resources/`):
- `EventResource` -- event CRUD with registrations relation manager
- `RegistrationResource` -- registration management
- `CommunicationResource` -- communication logs
- `EventStatsOverview` widget on dashboard

**API surface** (`routes/api.php`):
- Auth: `POST /api/login`, `GET /api/user`, `POST /api/logout`
- Scanner (role: scanner+): `POST /api/scan`, `POST /api/entry`, `POST /api/meal`, `GET /api/guest/search`
- Manager (role: event_manager+): `GET /api/event/{id}/dashboard`, `POST /api/event/{id}/import`, `POST /api/event/{id}/send-invites`, `GET /api/reports/attendance/{event}`, `GET /api/reports/noshow/{event}`

**Roles:** `super_admin`, `event_manager`, `scanner`, `viewer` -- enforced via `EnsureRole` middleware (`app/Http/Middleware/EnsureRole.php`).

**Models** (`app/Models/`): `Event` -> hasMany `Registration` -> hasMany `Communication`. All use `SoftDeletes` + `LogsActivity`.

**App structure:**
- `app/Services/` -- `QRCodeService`, `CommunicationService`
- `app/DTOs/` -- `ScanResponseDTO`
- `app/Jobs/` -- `SendBulkEmail`, `SendBulkSMS`
- `app/Events/` -- `EntryRecorded`, `MealUsed`
- `app/Listeners/` -- `UpdateRedisCache`
- `app/Imports/` -- `RegistrationsImport`
- `app/Exports/` -- `AttendanceExport`, `NoShowExport`

**Scanning flow:**
```
Scan QR -> POST /api/scan (Redis lookup, fallback DB) -> display guest -> POST /api/entry or /api/meal
Entry/Meal writes are idempotent (return false if already recorded)
Events dispatched (EntryRecorded, MealUsed) update Redis cache async
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
- `config/horizon.php` -- queue supervisor config
- `config/sanctum.php` -- stateful domains
- `.env.example` -- reference for all required env vars

## Design Decisions

- **DTOs** for API responses -- models don't leak to API
- **Event-driven:** Laravel events for cache updates, audit logging
- **Idempotency:** `recordEntry()` / `recordMeal()` return bool
- **Validation in three layers:** Frontend (React), FormRequest classes, database constraints

## Documentation

- `docs/PRD.md` -- full requirements
- `docs/system-design.md` -- database schema, API endpoints, Redis keys
- `docs/architectural-design.md` -- C4 diagrams, data flows
- `docs/tech-stacks.md` -- technology rationale
- `docs/best-coding-practice.md` -- patterns, testing strategy

## Git Workflow

- Commit format: `feat:`, `fix:`, `docs:`, `chore:`
