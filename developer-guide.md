# Developer Guide

> **Event Management System for ICT Foundation Nepal** — a Laravel 13 + Filament v5 app that replaces manual Excel workflows for pre-event registration, online payment, QR-based on-site scanning, meal coupon validation, label/badge printing, bulk email/SMS, and post-event reporting.
>
> New here? Read this guide top-to-bottom, then `RUN.md` to get the app running, and `AGENTS.md` for the gotcha list.

---

## 1. Tech stack

| Layer | Choice | Notes |
|---|---|---|
| Backend | Laravel 13, PHP 8.3+ | Queue + cache use the **database driver** (no Redis — shared hosting friendly) |
| Admin panel | Filament v5 (Livewire) | `/admin` |
| Database (dev) | PostgreSQL 15 | `event_management` DB. Uses `EXTRACT(HOUR ...)::int` and JSONB — **not SQLite-compatible** |
| Database (test) | file-based SQLite | `database/testing.sqlite` |
| Database (prod) | MySQL on cPanel | see `DEPLOY.md` |
| PDF | `dompdf/dompdf` v3 | direct use — `barryvdh/laravel-dompdf` is incompatible with Laravel 13 |
| QR | `simplesoftwareio/simple-qrcode` (backend) | QR payload is a URL to `/checkin/t/{guest_number}` |
| Excel | `maatwebsite/excel` | CSV/XLSX import + `?format=xlsx` report exports |
| API auth | Laravel Sanctum | token-based |
| Audit | `spatie/laravel-activitylog` | on Event, Registration, User, Payment, ScanLog, etc. |
| SMS | Sparrow / Sociair | `SMS_DRIVER=log` (dev) / `sparrow` (prod) |
| Payments | Connect IPS (NCHL, Nepal) | redirect + manual validation; no webhook |
| Frontend assets | Vite + Tailwind v4 | `theme.css` with `@theme` blocks (no `tailwind.config.js`) |

---

## 2. High-level architecture

One Laravel app serving two consumers:

1. **Filament admin panel** (`/admin`) — event config, registration management, payments, labels, reports.
2. **HTTP/JSON API** (`/api/*`) — consumed by physical scanner devices (the React scanner PWA was **removed**; scanners call the API directly).

### Directory map (what lives where)

```
app/
  Filament/            Admin panel
    Resources/         CRUD resources (Event, Registration, Payment, ...)
    Resources/Concerns/ HasRoleBasedVisibility, HasEventScope (traits)
    Pages/             ImportPreview (Import Guests page)
    Widgets/           Dashboard charts/stats
  Http/Controllers/    Web controllers (public reg, tickets, labels, reports)
  Http/Controllers/Api/ Scanner + report endpoints
  Http/Middleware/     EnsureRole, EnsureFilamentAccess, IdempotentScan, SecurityHeaders, AdminSessionTimeout
  Imports/             RegistrationsImport (CSV/XLSX → staging)
  Exports/             Report exports (PDF + XLSX)
  Services/            QRCodeService, TicketService, LabelService, CommunicationService, InvoiceService,
                       Payment/{ConnectIPSService, PaymentRedirector}
  Models/              Eloquent models
  DTOs/ Events/ Jobs/ Listeners/ Observers/   cross-cutting pieces
routes/
  web.php              Public pages + report downloads (auth middleware)
  api.php              Sanctum-protected scanner + report JSON endpoints
  console.php          Scheduled tasks
```

---

## 3. Local setup (summary)

Full step-by-step in **`RUN.md`**. Essentials:

1. `.env` must exist (copy `.env.example` or `.env.local.bak`, set `DB_PASSWORD`). **`php artisan config:clear` after any `.env` change.**
2. `composer install` + `npm install`
3. `php artisan migrate` then `php artisan db:seed --class=RoleSeeder`
4. Launch three processes — see `RUN.md`:
   - `php -d display_errors=Off artisan serve --port=8000`
   - `php artisan queue:work --tries=3`
   - `npm run dev` (Vite on `127.0.0.1:5173`)
5. Sanity check: `php artisan dev:check` must report **"Clean HTML ✓"**.

> Seeded login: `admin@ictfoundation.org.np` / `password`. Always browse via **`http://localhost:8000`** — `.env` sets `SESSION_DOMAIN=localhost`, and `127.0.0.1` breaks sessions silently.

---

## 4. Data model

Core entities and relationships:

```
User ─┬─ creator (created_by) ──────── Event
      └─ assignedEvents (pivot: event_user) ──┘
                        │
Event ───┬── ParticipantCategory ──< Registration >── PromoCode
         │                        │       │
         ├── ScanActionType <─────│       └── Communication
         │        │               │
         │        └── ScanLog ────┘       Registration ──< Payment
         ├── LabelTemplate
         ├── Registration (hasMany)
         └── ImportBatch ──< ImportError
```

- **Event** — details, images, `start/end_datetime` (multi-day), registration window, `max_capacity`, `settings` (JSONB array-cast, read via `settingEnabled('key')`, defaults `true`), status `draft|published|closed|archived`.
- **ParticipantCategory** — `is_paid`, `price`, `early_bird_price/until`, `badge_color`, `sort_order`, `qr_access_permissions` (which scan actions each category may use), `requires_approval`.
- **Registration** — auto-generates `guest_number` (`{CODE}-G-XXXXXX`) and `qr_hash` (HMAC) on create. Fields: `approval_status` (`pending|waitlisted|approved`), `payment_status`, `badge_status`, `label_printed/collected_at`, `group_id`/`companion_count` (group booking), `registration_source` (`self|csv|admin_manual`).
- **Payment** — `amount_paisa`, `transaction_id` (`P...`), `gateway_txn_id`, reconciliation fields (`batch_id`, `debit_bank_code`, `charge_amount_paisa`, `credit_status`), status `pending|initiated|success|failed|cancelled|expired|refunded`, `expires_at` (30 min), `invoice_number`.
- **ScanActionType** — per-event actions: `action_name`, `action_code` (e.g. `CHECKIN`, `LUNCH`, `DINNER`, `CARD_DELIVERY`, `BADGE_COLLECT`, per-day `DAY1_*`), `column_mapping` (writes a timestamp column on `registrations`), `allow_multiple`.
- **ScanLog** — one row per scan: `participant_id`, `action_type_id`, `scanned_by`, `scanned_at`, `scan_date`.
- **Communication** — every email/SMS attempt with `type` (`email|sms`), `email_type`, `status` (`pending|sent|failed`).
- **ImportBatch / ImportError / ImportStaging** — CSV import tracking and two-phase staging.

---

## 5. Roles & access control

Roles are a `role` **string column on `users`** — **not** Spatie (the permission tables exist but are empty; do not add `HasRoles`).

| Role | Admin panel | Access |
|---|---|---|
| `super_admin` | Yes | Full access, manages admins |
| `admin` | Yes | Full access, manages managers |
| `manager` | Yes | **Assigned events only** (via `event_user` pivot) |
| `finance` | Yes | Payments + reports |
| `scanner` | No | API-only (scan/entry/meal) |
| `viewer` | No | API-only placeholder |

- **Navigation gating:** `HasRoleBasedVisibility` trait (default `['super_admin', 'admin']`, override `getVisibleRoles()`).
- **Panel access:** `EnsureFilamentAccess` middleware blocks `/admin/*` to `['super_admin','admin','manager','finance']`.
- **API access:** `EnsureRole` middleware (`role:scanner,admin,super_admin,manager` for scanner endpoints, etc.).

---

## 6. Event lifecycle (the end-to-end flow)

```
1. CREATE & CONFIGURE (admin)
   Event (details, images, dates, venue, toggles, status) 
     → Participant Categories (prices, early-bird, QR permissions)
     → Scan Action Types (checkin/meals/card delivery)
     → Label Templates
     → Publish event

2. REGISTER (three sources)
   Public /event/{slug}/register   → self-service + ConnectIPS payment
   Onsite /admin/onsite-register   → walk-in desk (admin/manager)
   CSV Import (ImportPreview)      → bulk upload, staged, approved row-by-row

3. PAY (paid categories)
   Promo code → early-bird → subtotal → tax (settings.tax_rate) → ConnectIPS
   → callback validates → creditStatus 000/999/DEFER = success
   → confirmation email + SMS + PDF ticket (QR) → guest_number issued

4. EVENT DAY
   Scanner device: POST /api/scan → guest card → POST /api/entry|/meal|/scan-action
   → scan_logs + column timestamps, idempotent per action
   Badge/label collection tracked (CARD_DELIVERY / BADGE_COLLECT)

5. POST-EVENT
   Reminder email day before (09:00), thank-you after (10:00)
   Reports: attendance, no-show, meals, payments, scanner activity, card delivery
   Archive via soft-delete
```

---

## 7. Registration flows

### 7.1 Public self-registration — `PublicRegistrationController`

`GET /event/{slug}/register` guards:
- event `enable_self_registration` toggle must be on
- registration window open (`isRegistrationOpen()`)
- capacity check → either "closed" or waitlist join (if `enable_waitlist`)

`POST` creates the registration:
1. Duplicate check by email/phone.
2. Validate category (event-scoped, active).
3. Promo code (`PromoCode::isValid()` → `calculateDiscount(effectivePrice)`).
4. Effective price = early-bird price if within window, else `price`.
5. `approval_status` = `pending` (category `requires_approval`) → `waitlisted` (at capacity + waitlist) → `approved`.
6. `companion_count > 0` → creates that many companion registrations sharing a `group_id`.
7. Paid + payment enabled → `PaymentRedirector` → auto-submits form to ConnectIPS (no redirect, response is an HTML form).
8. Otherwise send confirmation (email+SMS with QR ticket) → success page.

### 7.2 Onsite registration — `OnsiteRegistrationController`

`GET/POST /admin/onsite-register/{event}` (auth + `super_admin|admin|manager`). Walk-in form with `payment_method` = `none|gateway|cash`. Optional confirmation notification.

### 7.3 CSV import (two-phase)

Admin → **Import Guests** page (`ImportPreview`):
1. Header action "Upload CSV / XLSX" (Filament `FileUpload` **inside an action form**).
2. `RegistrationsImport` validates per-row (name required; email/phone at least one; gender/meal enum) and stages valid rows in `import_staging` (`status=pending`); invalid rows → `import_errors` with the row number.
3. Per-row "Register" / "Skip" or bulk "Register Selected" → creates registrations (`registration_source=csv`) and queues confirmations.
4. API equivalent: `POST /api/event/{event}/import`.

> Gotcha: `+9779800000001` in a CSV gets parsed to an integer (drops `+`); `normalizePhone()` re-prepends `977` for 13–14 digit numbers.

---

## 8. Payment flow (ConnectIPS)

Entry points: `app/Services/Payment/PaymentRedirector.php` + `ConnectIPSService.php`, config `config/connectips.php` (12 `CONNECTIPS_*` env vars).

1. **Initiate** — `PaymentRedirector::initiate()`: original price → promo discount → `tax_rate` from event settings → `amount_paisa`; creates a `Payment` (`pending`, `expires_at` = now + 30 min); calls `ConnectIPSService::initiatePayment()` which returns an **auto-submitting HTML form**.
2. **Redirect** — browser POSTs to NCHL (`uat.connectips.com:7443` UAT / `login.connectips.com` prod), user pays.
3. **Callback** — `paymentSuccess()`: `validateTxn` (mTLS) → `interpretValidationResult`:
   - `success` → `getTransactionDetail` → `markAsSuccess` + `recordReconciliationDetails` → **merchant credit check** (`creditStatus` in `000|999|DEFER`; otherwise mark failed) → send confirmation → success page.
   - `pending` → leave `initiated`, show pending page.
   - `failed` → `markAsFailed` → failure page.
4. **Retry** — `paymentRetry()` re-initiates a fresh payment for pending/failed registrations.
5. **Expiry** — `payment:expire` every 5 min marks expired.

Key facts:
- mTLS requires `storage/app/keys/CREDITOR.{pem,pfx}` (gitignored). Key must be a proper **2048-bit RSA** or `openssl_sign` fails (`bignum routines::no inverse`).
- **No refund API** — mark refunded in Filament, process the money in NCHL's merchant portal.
- **No IPN/webhook** — redirect + manual `validateTxn` only.
- End-to-end test: `php artisan connectips:test-flow` (mocked) / `--live` (real UAT). Self-cleans.

---

## 9. Scanning flow (API)

Scanner devices authenticate via Sanctum token (`POST /api/login`).

```
POST /api/scan  (code = URL | guest_number | unique_code | qr_hash)
   QRCodeService::resolve() → Registration
   → 403 if event_id doesn't match (event-specific QR)
   → ScanResponseResource (guest card data + action statuses)

POST /api/entry | /api/meal | /api/scan-action
   Registration::recordAction(ScanActionType, scannedBy)
   → checks category qr_access_permissions (canPerformAction)
   → idempotent: blocked if action already recorded (allow_multiple=false / column_mapping set)
   → writes ScanLog + optional column timestamp
```

- `POST /api/scan-action` takes `action_type_id` — all configured actions are dynamic per event.
- `IdempotentScan` middleware + `throttle:60,1` guard the scanner endpoints.
- `CARD_DELIVERY` / `BADGE_COLLECT` track badge handover (`badge_status`, `label_collected_at`).

---

## 10. Notifications (email + SMS) — `CommunicationService`

- **Every send is logged** to `communications` with a status (`pending|sent|failed`); failed rows can be re-sent from the Filament UI.
- Email templates (Blade): `registration_confirmation`, `payment_success`, `payment_failed`, `event_reminder`, `post_event_thank_you`, `urgent_update`. QR SVG embedded; PDF ticket attached on `registration_confirmation` and `payment_success`.
- SMS via Sparrow (`config/services.php → sparrow`); driver `log` just prints to the console (dev), `sparrow` hits the API (prod). Sender ID configured in the Sociair dashboard, not env.
- Gated per-event by `enable_notifications` setting.
- Queued jobs: `SendBulkEmail`, `SendBulkSMS`, `SendEventReminders`, `SendPostEventThankYou` (database queue).

---

## 11. Tickets & labels

| Piece | Service | Details |
|---|---|---|
| Ticket | `TicketService` | A6 landscape PDF via dompdf: event logo, name, category color strip, `guest_number`, QR (PNG), date/venue. Public at `/ticket/{token}` (HTML) and `/ticket/{token}/download` (PDF). |
| Labels | `LabelService` | A4 grid of participant labels with QR; `label_printed/at/by` + `label_collected_at` tracked; bulk + single print via `/labels`. |
| Invoice | `InvoiceService` | Generates invoice PDFs for successful payments. |

---

## 12. Reports & exports

Two surfaces:

1. **Web routes** (`auth` middleware, from the Event detail page):
   ```
   /reports/{event}/pdf-summary       → PDF event summary
   /reports/{event}/payments          → payments list
   /reports/{event}/scanner-activity  → scans per scanner/action/device
   /reports/{event}/category-summary  → per-category reg/payment/attendance
   /reports/{event}/card-delivery     → delivered vs pending
   ```
2. **API routes** (`auth:sanctum` + role) — same set plus attendance, no-show, duplicate-scans, communications, meal-usage, event-summary(-pdf).

All accept `?format=xlsx` (maatwebsite exports in `app/Exports/`).

---

## 13. Admin panel (Filament)

- **Event switcher** — sidebar dropdown persists `session('active_event_id')`; `HasEventScope` trait auto-filters resources by active event (don't forget it when adding new scoped resources). Managers only see assigned events.
- **Resources:** Event, Registration, ParticipantCategory, ScanActionType, Payment, Communication, LabelTemplate, ImportBatch, PromoCode, User.
- **Widgets:** EventStatsOverview, PaymentStatsOverview, RegistrationTrendChart, RecentRegistrationsTable, RecentScansTable, ArrivalDistributionWidget (check-in by hour), QuickActionsWidget.

---

## 14. Scheduled tasks (`routes/console.php`)

| Task | Schedule | Command |
|---|---|---|
| DB backup | daily 02:00 | `db:backup --retain=90` |
| Event reminders | daily 09:00 | `event:send-reminders` (day-before event) |
| Post-event thanks | daily 10:00 | `event:send-thankyou` |
| Payment expiry | every 5 min | `payment:expire` |

---

## 15. Key services at a glance

| Service | Purpose |
|---|---|
| `QRCodeService` | QR payload (`/checkin/t/{guest_number}`), SVG/PNG generation, `resolve()` (URL → guest_number → unique_code → qr_hash) |
| `PaymentRedirector` / `ConnectIPSService` | Price calc (discount/tax) → payment record → ConnectIPS initiate/validate |
| `CommunicationService` | Typed email/SMS with delivery log + resend |
| `TicketService` / `LabelService` / `InvoiceService` | PDF generation |
| `RegistrationsImport` | CSV/XLSX → staging + per-row validation |

---

## 16. Testing, lint, deploy

```bash
composer test                          # config:clear + phpunit (OpenSSL env wrapper on Windows)
php artisan test tests/Feature/ScanTest # single file
php artisan test --parallel            # parallel
./vendor/bin/pint                      # fix code style
./vendor/bin/pint --test               # check only
php artisan dev:check                  # dev env health (ports, HTML integrity, sessions)
php artisan deploy:check               # pre-deploy validation
php artisan auth:diagnose <email> <pwd> --attempt  # login diagnostic
php artisan connectips:test-flow [--live]          # payment gateway e2e
```

- Test layout: `tests/Feature/*Test.php`, `tests/Unit/{Models,Services}/*Test.php`. File-based SQLite + `RefreshDatabase` + 4 bcrypt rounds.
- Conventions: commits `feat:`/`fix:`/`docs:`/`chore:`; never edit applied migrations (new schema → new migration); never commit `.env`, `.env.local.bak`, or `storage/app/keys/*`.

---

## 17. Docs index

| Doc | What it's for |
|---|---|
| `RUN.md` | Step-by-step dev run guide (env, launch, stop, troubleshooting) |
| `AGENTS.md` | Gotcha list — Filament v5 API, broken-pipe trap, Windows/Herd quirks, known issues |
| `docs/feature-roadmap.md` | Phase tracker (1–15 done; several Phase 16 items are actually built) |
| `docs/connectips-integration.md` | Full NCHL/ConnectIPS spec, field lengths, errors |
| `docs/architectural-design.md`, `docs/system-design.md` | Design rationale |
| `DEPLOY.md` | Production (cPanel/MySQL) deploy steps |
