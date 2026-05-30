# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Event management system for ICT Foundation Nepal. Manages pre-event registrations, on-site QR code scanning, meal coupon validation, bulk email/SMS communications, and historical event archives. Replaces manual Excel workflows.

## Tech Stack

- **Backend:** Laravel 11, PHP 8.2+, PostgreSQL 15, Redis 7
- **Admin Panel:** FilamentPHP 3 (built on Livewire)
- **Scanner App:** React 18 + Vite PWA in `scanner-app/` (separate build)
- **QR Scanning:** `html5-qrcode` + `simplesoftwareio/simple-qrcode`
- **Queue:** Laravel Horizon (Redis driver)
- **Excel:** `maatwebsite/excel`
- **Auth:** Laravel Sanctum (token-based for API)
- **Audit:** `spatie/laravel-activitylog`

## Commands

```bash
# Laravel
php artisan test                          # run all tests
php artisan test tests/Feature/ScanTest   # run single test file
php artisan test --parallel               # parallel tests
php artisan horizon                       # start queue worker

# Scanner PWA
cd scanner-app && npm run dev             # PWA dev server (port 5173)
cd scanner-app && npm run build           # production build

# Code quality
./vendor/bin/pint                         # fix code style
./vendor/bin/phpstan analyse              # static analysis (target level 8)
```

## Architecture

**Two separate applications sharing one Laravel backend:**
1. **FilamentPHP admin panel** at `/admin` — CRUD for events, registrations, communications, reporting
2. **React PWA scanner** at `/scanner` — mobile QR scanning, entry/meal recording

**API surface** (`routes/api.php`):
- Scanner endpoints: `/api/scan`, `/api/entry`, `/api/meal`, `/api/guest/search` — role: scanner+
- Manager endpoints: `/api/event/{id}/import`, `/api/event/{id}/send-invites`, `/api/reports/*` — role: event_manager+
- Auth: `/api/login`, `/api/logout`

**Roles:** `super_admin`, `event_manager`, `scanner`, `viewer` — enforced via `EnsureRole` middleware.

**Data flow for scanning:**
```
Scan QR → /api/scan (Redis lookup first, then DB) → display guest → Record Entry / Mark Meal
Entry/Meal writes are idempotent (model returns false if already recorded)
Events dispatched (EntryRecorded, MealUsed) update Redis cache async
```

**QR codes:** UUID v4 per registration, HMAC-SHA256 signed with `APP_KEY`. QR payload is the raw UUID. `QRCodeService` handles generation and resolution.

**Key models:** `Event` → hasMany `Registration` → hasMany `Communication`. All models use `SoftDeletes` + `LogsActivity` (Spatie).

**Queue jobs:** `SendBulkEmail` (high), `SendBulkSMS` (high), `GenerateQRCodes` (low). Configured in `config/horizon.php`.

## Design Decisions

- **Modular architecture** under `app/`: Services, DTOs, Events/Listeners, Jobs, Imports/Exports
- **Repository pattern** for data access in services (see `docs/best-coding-practice.md`)
- **DTOs** for API responses (e.g., `ScanResponseDTO`) — models don't leak directly to API
- **Event-driven:** Laravel events for cache updates, audit logging, notifications
- **Idempotency:** `recordEntry()` and `recordMeal()` return bool — false if already done
- **Validation in three layers:** Frontend (React), FormRequest classes, database constraints

## Documentation Index

- `docs/PRD.md` — full requirements with FR/NFR IDs
- `docs/system-design.md` — database schema (SQL), API endpoint table, Redis key design
- `docs/architectural-design.md` — C4 diagrams, data flows, deployment architecture
- `docs/tech-stacks.md` — technology rationale, cost estimates, env setup
- `docs/best-coding-practice.md` — patterns, testing strategy, Git workflow
- `docs/superpowers/plans/2026-05-30-event-management-system.md` — 16-task TDD implementation plan

## Git Workflow

- `main` — production, `staging` — pre-prod, `feature/*` — individual features
- Commit format: `feat:`, `fix:`, `docs:`, `chore:`
