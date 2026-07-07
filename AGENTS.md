# AGENTS.md

## Environment

- **`.env.local` overrides `.env`** when `APP_ENV=local`. If the app behaves differently than `.env` says, check `.env.local`.
- `config:clear` is mandatory after any `.env` change or DB switch.

## How to run

```bash
pkill -9 -f "artisan serve|queue:work|vite"
php artisan config:clear

# BOTH stderr AND stdout must be redirected to prevent "broken pipe" notices
# that corrupt responses (especially Livewire JSON). Use dedicated log files:
nohup php -d display_errors=Off artisan serve --port=8000 > /tmp/serve-stdout.log 2> /tmp/serve-stderr.log &
php artisan queue:work --tries=3 > /tmp/queue.log 2>&1 &
npm run dev &
```

Then verify with:
```bash
php artisan dev:check    # port scan, response integrity, sessions, orphans
```

`composer dev` is broken — it tries to run `php artisan horizon` which is in `dont-discover`.

### Known issue: "Notice: file_put_contents() ... Broken pipe" / login fails silently

**Root cause:** PHP's built-in dev server writes request logs to `php://stdout`. When stdout is a closed pipe (detached terminal, expired shell session), the write fails with `errno=32 Broken pipe`. If `display_errors` is on or PHP emits the notice anyway, it gets prepended to every response:

- HTML pages get an ugly `<br /><b>Notice</b>` banner at the top
- Livewire JSON responses get corrupted → `SyntaxError: Unexpected token '<'` in the browser console → login form submits but never redirects
- SVG icons render with escaped quotes because the JSON parser fails

**Fix checklist:**
1. Run `php artisan dev:check --ports=8000,8001,8002,5173` — any orphan PHP servers on unexpected ports? Kill them.
2. Run `curl -s http://localhost:8000/admin/login | head -c 50` — the first 50 bytes MUST be `<!DOCTYPE`. If it starts with `<br`, the server is corrupt.
3. Kill all PHP servers (`pkill -9 -f "artisan serve"`), then restart with `nohup` as shown above.
4. Run `php artisan dev:check` again — response integrity should show "Clean HTML ✓".

## Database

- **Dev:** PostgreSQL 15 (`event_management` db). Uses `EXTRACT(HOUR FROM ...)::int` — **not SQLite-compatible**.
- **Test:** In-memory SQLite (configured in `phpunit.xml`).
- **Production:** MySQL (cPanel shared hosting, see `DEPLOY.md`).

## Filament v5 — common mistakes

- Forms: `Filament\Schemas\Schema` with `->components([])` — **not** `Filament\Forms\Form`
- Table row actions: `->recordActions([])` — **not** `->actions([])`
- Action imports: `Filament\Actions\*` — **not** `Tables\Actions\*`
- Section: `Filament\Schemas\Components\Section`
- Tab: `Filament\Schemas\Components\Tabs\Tab`
- `Resource::$navigationGroup` must be `string|UnitEnum|null` — add `use UnitEnum;`
- `Widget::$view` and `Page::$view` are **non-static**: `protected string $view = '...'`
- `Page::$navigationIcon` must be `string|\BackedEnum|null`
- ChartWidget: `$heading` and `$maxHeight` are **non-static**
- Use `<x-filament::icon>` in Blade; raw SVGs work but need Tailwind `@source` (see below)
- CRUD toasts: override `getCreatedNotification()` / `getSavedNotification()`
- Empty tables: `->emptyStateHeading()` / `->emptyStateDescription()`

## Tailwind v4 theme

Theme: `resources/css/filament/admin/theme.css`. No `tailwind.config.js` — uses `@theme` blocks.

**Critical:** Tailwind `@source` directives control which files get utility classes. If a Blade component in `resources/views/components/` uses Tailwind classes and they don't render, add to the theme:

```css
@source '../../../../resources/views/components/**/*';
```

After changing `@source`, run `npm run build` to rebuild CSS.

## Auth & roles

Users have a simple `role` string column — **not** Spatie roles/permissions. Spatie `laravel-permission` tables exist in the DB (migrated) but are empty and unused. Do not add `HasRoles` to any model. Role hierarchy:

| Role | Admin Panel | Notes |
|------|:---:|-------|
| `super_admin` | Yes | Full access, manages admins |
| `admin` | Yes | Full access, manages managers |
| `manager` | Yes | Assigned events only via `event_user` pivot |
| `finance` | Yes | Payments + reports |
| `scanner` | No | API-only (scan/entry/meal endpoints) |
| `viewer` | No | Read-only placeholder |

- `HasRoleBasedVisibility` trait controls resource access — default is `['super_admin', 'admin']`
- `EnsureFilamentAccess` middleware gates `/admin/*` to `['super_admin', 'admin', 'manager', 'finance']`
- `EnsureRole` middleware on API routes
- Login: `admin@ictfoundation.org.np` / `password` (after seeding)
- **Never use `$this->middleware()` in controllers** — Laravel 11 Controller base class doesn't have it. Apply middleware on routes instead.
- User management: `UserResource` under Settings nav. SA creates admins/managers; Admin creates managers only.
- **Auth logging:** Login successes and failures are logged to `storage/logs/auth-YYYY-MM-DD.log`. If a login fails silently (no error message), check this log first. Failed attempts include email, IP, and user agent. Use `php artisan auth:diagnose` to run a full 7-point diagnostic.
- **Login silently loops / stuck on login page:** Three possible culprits:
  1. **SPA mode**: `->spa()` is enabled with `/admin/login` and `/admin/logout` as `spaUrlExceptions`. If these exceptions are missing, Livewire intercepts the post-login redirect with a stale CSRF token → 419 errors → user stays on login page. Added in `AdminPanelProvider.php`.
  2. **Broken pipe notices**: PHP dev server writing to closed stdout corrupts Livewire JSON responses. Run `php artisan dev:check` — response integrity must show "Clean HTML ✓". See Known Issue in "How to run".
  3. **SESSION_DOMAIN mismatch**: `.env.local` sets `SESSION_DOMAIN=localhost`. Accessing via `http://127.0.0.1:8000` will NOT match this domain — cookies are never sent, sessions are never persisted. Always use `http://localhost:8000` in dev.

## Event system

### Event switcher
- Alpine.js dropdown in sidebar nav (injected via `renderHook('panels::sidebar.nav.start', ...)`)
- Stores `session('active_event_id')` via `POST /event-switcher/switch`
- `SetDefaultActiveEvent` listener sets first event on login
- Event-user assignments in `event_user` pivot table (`event_id`, `user_id`, `assigned_by`)

### Single event dashboard
- `/admin/events` is a `ListRecords` table — select an event to view/edit/export.
- All 7 dashboard widgets scope to `session('active_event_id')`, set via the sidebar event-switcher.
- Key resources (Registrations, Payments, Categories, ScanActions) auto-filter by active event via `->modifyQueryUsing()`

### Event settings
- Toggle settings stored in `events.settings` JSONB column
- Accessed via `$event->settingEnabled('key')` — defaults to `true` if not set
- The `settings` attribute must always return an array with defaults. The accessor in `Event.php` merges defaults. **Do not** use `'settings' => 'array'` cast alongside the accessor — it conflicts.

## CSV import (two-phase)

Flow: Upload → Stage → Register

1. `/admin/import-preview` — `ImportPreview` Filament page (Attendees nav group)
2. Upload via native `<input type="file">` → `POST /import-upload` → `ImportUploadController`
3. `RegistrationsImport` stages rows in `import_staging` table (status: `pending`)
4. Each row has "Register" / "Skip" buttons. "Register" creates `Registration` + QR + sends notifications.
5. Bulk "Register Selected" action available.

### Import gotchas
- **Never use Filament's `FileUpload` component on a Page** — it spawns infinite file chooser dialogs. Use a native `<input type="file">` in a Blade form.
- Phone numbers: CSV parsers convert `+9779800000001` to integer, stripping the `+`. The import detects and prepends `+` automatically.
- Column names: `col()` helper in `RegistrationsImport` does case-insensitive column lookups.
- File extension: Use `$file->hashName()` (not `$file->store()`) to preserve the extension for Maatwebsite Excel reader detection.

## Report downloads

Event detail page has 6 header actions. The 5 export actions (PDF Summary, Payments, Scanner Activity, Category Summary, Card Delivery) use **web routes**, not API routes:

```
/reports/{event}/pdf-summary       → ReportDownloadController
/reports/{event}/payments          → ReportDownloadController
/reports/{event}/scanner-activity  → ReportDownloadController
/reports/{event}/category-summary  → ReportDownloadController
/reports/{event}/card-delivery     → ReportDownloadController
```

These are `auth`-protected web routes, not `auth:sanctum` API routes. The controller uses Export classes directly.

## Registration flow

- Public: `/event/{slug}/register` → `PublicRegistrationController`
- Onsite: `/admin/onsite-register/{event}` → `OnsiteRegistrationController` (auth-protected, role: super_admin/admin/manager)
- CSV: Import → Stage → Register flow above
- Promo codes: `PromoCodeResource` under Events nav, applied in `PublicRegistrationController::store()`
- Early bird pricing: `early_bird_price` + `early_bird_until` on `ParticipantCategory`
- Tax: `settings.tax_rate` on event, calculated in `initiatePayment()`
- Waitlist: `settings.enable_waitlist` on event, sets `approval_status = 'waitlisted'`
- Companion booking: `companion_count` field on registration form

## ConnectIPS payment integration

Full docs: `docs/connectips-integration.md`. Architecture summary:

- `app/Services/Payment/ConnectIPSService.php` — token signing (SHA256 + RSA), initiate (auto-submit form), validateTxn, getTxnDetail, result interpretation
- `app/Services/Payment/PaymentRedirector.php` — shared service for both public + onsite paid flows (price → tax → discount → create Payment → auto-submit HTML)
- `config/connectips.php` — all env-driven gateway config including mTLS cert path + SSL verify
- UAT: `https://uat.connectips.com:7443`, Production: `https://login.connectips.com`
- mTLS required for API calls — client cert extracted from NCHL's `.pfx` into `storage/app/keys/CREDITOR.pem` + `.crt`
- `connectips:test-flow --live` hits real NCHL; `connectips:test-flow` (mocked) tests the full pipeline
- Reconciliation fields persisted: `gateway_txn_id`, `batch_id`, `debit_bank_code`, `charge_amount_paisa`, `credit_status`
- `creditStatus` 000/999/DEFER = merchant-side success per NCHL spec

## SMS (Sociair/Sparrow)

Config: `config/services.php` → `services.sparrow`. Env vars: `SMS_DRIVER`, `SPARROW_SMS_TOKEN`, `SPARROW_SMS_BASE_URL`, `SMS_BATCH_SIZE`. Driver `log` = dev (logs to console), `sparrow` = production API. Sender ID configured in Sociair dashboard, not env vars.

## Running tests

```bash
composer test                          # config:clear + test
php artisan test tests/Feature/ScanTest  # single file
./vendor/bin/pint --test               # lint only
```

## Diagnostic commands

| Command | What it does |
|---|---|
| `php artisan dev:check [--kill] [--ports=8000,8001,8002,5173]` | Scans dev ports for PHP processes, finds orphans, checks response integrity (no PHP notices corrupting HTML/JSON), verifies sessions, lists log files. `--kill` terminates found orphans. |
| `php artisan deploy:check` | Pre-deploy checklist: config/route/event/view cache, DB connectivity, pending migrations, APP_KEY, APP_DEBUG, required env vars (incl. ConnectIPS), PHP extensions, memory limit, git status. |
| `php artisan auth:diagnose [email] [password] [--attempt]` | Full auth diagnostic: user exists? password hash match? hash algorithm? role in allowed list? rate limit status? session/auth driver config? Live HTTP login test that captures both `Login` and `Failed` events. |
| `php artisan connectips:test-flow [--live]` | End-to-end ConnectIPS test: creates event + paid category → registers attendee → generates auto-submit form → calls validatetxn (mocked or live) → gettxndetail → records reconciliation. Cleans up test data. |

## Navigation groups

Defined in `AdminPanelProvider`: Events, Attendees, Finance, Communications, Settings.

## Key conventions

- PDF: `dompdf/dompdf` directly — not barryvdh/laravel-dompdf
- Queue/Cache: database driver (no Redis)
- Commit format: `feat:`, `fix:`, `docs:`, `chore:`
- Scanner app (`scanner-app/`) has been **removed** — physical scanner devices return GuestID directly. Scan API still works (`POST /api/scan` accepts QR hash, UUID, or guest number).
- `QRCodeService::resolve()` handles URLs, UUIDs, qr_hash tokens, and guest numbers (format: `XXX-G-YYYYYY`).
