# AGENTS.md

> **Project context:** Laravel 13 + Filament v5 event management app for ICT Foundation Nepal. Full overview in `CLAUDE.md` (tech stack, setup, model relationships, API surface). This file is the **gotcha list** — only what's hard to infer from filenames.

## Environment

- **`.env` must exist** in the project root. There is no `.env` by default — `.env.example` (pgsql config) and `.env.local` (pgsql config + Sparrow token) are templates. On Windows + Herd, if no `.env` is present Laravel falls back to its built-in `sqlite` defaults (`database/database.sqlite`), which **silently breaks** anything pgsql-specific (see Database below). Copy `.env.local` → `.env` and set `DB_PASSWORD`.
- `.env.local` is **not** auto-loaded by Laravel or Herd. It's a checked-in template only. Ignore any docs that say it overrides `.env` — that convention is not implemented.
- **`config:clear` is mandatory** after any `.env` change, DB switch, or `php artisan` config edit. Cached config survives env edits silently.
- `README.md` is empty. Don't waste time reading it.
- `composer dev` is broken — it tries to run `php artisan horizon` which is in `dont-discover` (`composer.json:81-83`). Use the manual block below.
- `composer-setup.php` at project root is a leftover Composer installer — not part of the app, safe to delete.

## Windows + Herd dev setup (non-obvious)

- **Herd is PHP + nginx only — no DB bundled.** No `psql`/`mysql`/`mariadb` in `C:\Users\User\.config\herd\bin`. Install PostgreSQL separately (see Database).
- **PostgreSQL via `winget install PostgreSQL.PostgreSQL.15` is broken on Windows** — leaves an incomplete install (missing `lib/*.dll`). Use the official EDB installer instead: `https://get.enterprisedb.com/postgresql/postgresql-15.13-1-windows-x64.exe`. Run with admin. Default data dir: `C:\Program Files\PostgreSQL\15\data`. Service: `postgresql-x64-15` (auto-starts). Default superuser: `postgres` (password set during install). `psql` at `C:\Program Files\PostgreSQL\15\bin\psql.exe`.
- **PHP `composer` proxy is at `C:\Users\User\.config\herd\bin\composer.bat`** — not on PATH by default. Prepend `C:\Users\User\.config\herd\bin;` to `$env:Path` in every shell session, or run `herd composer ...` which proxies correctly.
- **`Start-Process` quirk for `npm`:** `npm` is a `.cmd` shim, not a Win32 binary. `Start-Process -FilePath npm` fails with "%1 is not a valid Win32 application". Use `Start-Process -FilePath cmd.exe -ArgumentList "/c","npm","run","dev"`. Same goes for `npx`.
- **`Start-Process` quirk for log files:** `RedirectStandardOutput` and `RedirectStandardError` cannot point to the same file. Use separate files.
- **`Start-Process` quirk for working dir:** always pass `-WorkingDirectory` explicitly — `$PWD` does not persist between separate tool invocations.

## How to run (dev)

The block below assumes bash (macOS / Linux / WSL / Git Bash on Windows). Native Windows cmd / PowerShell needs the same commands without the leading `pkill` cleanup.

```bash
# Optional: kill orphans from previous runs
pkill -9 -f "artisan serve|queue:work|vite" 2>/dev/null

php artisan config:clear

# BOTH stdout and stderr MUST be redirected to dedicated log files.
# See "Broken pipe" below — without this, Livewire responses get corrupted.
nohup php -d display_errors=Off artisan serve --port=8000 > /tmp/serve-stdout.log 2> /tmp/serve-stderr.log &
php artisan queue:work --tries=3 > /tmp/queue.log 2>&1 &
npm run dev &

# Sanity:
php artisan dev:check
```

**Windows PowerShell equivalent** (Herd is PHP+nginx only — no `pkill`, no `nohup`. Use `Start-Process` with separate stdout/stderr files):

```powershell
$env:Path = "C:\Users\User\.config\herd\bin;" + $env:Path
$proj = "C:\Users\User\Documents\GitHub\event-management"
$log  = "$env:USERPROFILE\dev-logs"; New-Item -ItemType Directory -Path $log -Force | Out-Null

php artisan config:clear

# Serve. `-d display_errors=Off` + redirected logs prevents the broken-pipe trap.
Start-Process -FilePath php -ArgumentList "-d","display_errors=Off","artisan","serve","--port=8000" `
  -WorkingDirectory $proj -RedirectStandardOutput "$log\serve-stdout.log" -RedirectStandardError "$log\serve-stderr.log" -WindowStyle Hidden

# Queue worker — separate stdout AND stderr files (same file fails).
Start-Process -FilePath php -ArgumentList "artisan","queue:work","--tries=3" `
  -WorkingDirectory $proj -RedirectStandardOutput "$log\queue-stdout.log" -RedirectStandardError "$log\queue-stderr.log" -WindowStyle Hidden

# Vite — npm is a .cmd shim, must be invoked via cmd.exe.
Start-Process -FilePath cmd.exe -ArgumentList "/c","npm","run","dev" `
  -WorkingDirectory $proj -RedirectStandardOutput "$log\vite-stdout.log" -RedirectStandardError "$log\vite-stderr.log" -WindowStyle Hidden

Start-Sleep 5
php artisan dev:check
```

### Known issue: "Notice: file_put_contents() ... Broken pipe" → login silently fails

**Root cause:** PHP's built-in dev server writes request logs to `php://stdout`. When stdout is a closed pipe (detached terminal, expired session), the write fails with `errno=32 Broken pipe`. If `display_errors` is on or PHP emits the notice anyway, it gets **prepended to every response**:

- HTML pages → ugly `<br /><b>Notice</b>` banner
- Livewire JSON → `SyntaxError: Unexpected token '<'` in browser → login form submits but never redirects
- SVG icons → escaped quotes because JSON parser fails

**Fix:**
1. `php artisan dev:check --ports=8000,8001,8002,5173` — kill orphan PHP servers (`--kill` does this)
2. `curl -s http://localhost:8000/admin/login | head -c 50` — first 50 bytes MUST be `<!DOCTYPE`. Starts with `<br`? Server is corrupt.
3. `pkill -9 -f "artisan serve"`, then restart with the `nohup` block above
4. `dev:check` again — must report "Clean HTML ✓"

## Database

- **Dev:** PostgreSQL 15 (`event_management`, user `manojghale` on macOS, `postgres` superuser on Windows). Uses `EXTRACT(HOUR FROM ...)::int` — **not SQLite-compatible**. Install via the EDB Windows installer (see Windows section) — not via winget.
- **Test:** **file-based** SQLite (`database/testing.sqlite`, set in `phpunit.xml`). Was previously `:memory:`; changed because in-memory SQLite + `RefreshDatabase` causes "cannot start a transaction within a transaction" errors on the 2nd+ test in a class.
- **Production:** MySQL on cPanel shared hosting. See `DEPLOY.md`.
- **Migrations:** 39 files, run with `php artisan migrate`. New schema → new migration, never edit applied migrations.

## Filament v5 — non-obvious API surface (has caused repeated issues)

- Forms: `Filament\Schemas\Schema` with `->components([])` — **NOT** `Filament\Forms\Form`
- Table row actions: `->recordActions([])` — **NOT** `->actions([])`
- Action imports: `Filament\Actions\*` (ViewAction, EditAction, BulkAction, BulkActionGroup, DeleteBulkAction) — **NOT** `Tables\Actions\*`
- `Resource::$navigationGroup` must be `string|UnitEnum|null` — add `use UnitEnum;` (don't declare as property)
- `Widget::$view` and `Page::$view` are **non-static** properties: `protected string $view = '...'`
- `Page::$navigationIcon` must be `string|\BackedEnum|null`
- ChartWidget: `$heading` and `$maxHeight` are **non-static** (declaring `static` causes a fatal error)
- View-only resources: empty form schema `return $schema->components([])`, not infolist
- CRUD toasts: override `getCreatedNotification()` / `getSavedNotification()` on the resource
- Empty tables: `->emptyStateHeading()` / `->emptyStateDescription()` (Filament v5 has no default empty state)
- Don't use Filament's `FileUpload` on a Page — it spawns infinite file chooser dialogs. Use a native `<input type="file">` (see CSV import below).

## Tailwind v4

Theme lives in `resources/css/filament/admin/theme.css`. **No `tailwind.config.js`** — uses `@theme` blocks.

- `@source` directives control which files get utility classes. If a Blade component in `resources/views/components/` uses Tailwind classes and they don't render, add it to the theme:
  ```css
  @source '../../../../resources/views/components/**/*';
  ```
- After changing `@source`, run `npm run build` to rebuild CSS.
- For icons, prefer `<x-filament::icon>` in Blade. Raw SVGs work but require a `@source` entry.

## Auth & roles

6 roles stored as a `role` string column on `users` — **NOT** Spatie (the tables exist but are empty; do **not** add `HasRoles` to any model):

| Role | Admin Panel | Notes |
|---|---|---|
| `super_admin` | Yes | Full access, manages admins |
| `admin` | Yes | Full access, manages managers |
| `manager` | Yes | Assigned events only via `event_user` pivot |
| `finance` | Yes | Payments + reports |
| `scanner` | No | API-only (scan/entry/meal) |
| `viewer` | No | API-only placeholder |

- `HasRoleBasedVisibility` trait in `app/Filament/Resources/Concerns/` gates resource nav (default `['super_admin', 'admin']` — override `getVisibleRoles()`).
- `EnsureFilamentAccess` middleware gates `/admin/*` to `['super_admin', 'admin', 'manager', 'finance']`.
- `EnsureRole` middleware on API routes.
- Seeded login: `admin@ictfoundation.org.np` / `password` (run `php artisan db:seed --class=RoleSeeder`).
- **Never use `$this->middleware()` in controllers** — Laravel 13's Controller base class doesn't have it. Apply middleware on routes.
- Auth successes/failures logged to `storage/logs/auth-YYYY-MM-DD.log` (includes email, IP, user agent). Check this first if login fails silently.

### Login stuck / looping

Three likely culprits, in order:

1. **SESSION_DOMAIN mismatch** — `.env` (copied from `.env.local`) sets `SESSION_DOMAIN=localhost`. Accessing via `http://127.0.0.1:8000` won't match → cookies never sent, sessions never persisted. Always use `http://localhost:8000` in dev.
2. **SPA mode missing exceptions** — `->spa()` is enabled in `AdminPanelProvider` with `/admin/login` and `/admin/logout` as `spaUrlExceptions`. If these exceptions are removed, Livewire intercepts the post-login redirect with a stale CSRF token → 419 errors → user stays on login page.
3. **Broken pipe notices** — see "How to run" above. Run `php artisan dev:check`.

Diagnostic: `php artisan auth:diagnose [email] [password] [--attempt]` runs a 7-point check including a live HTTP login.

## Event system

- **Event switcher** — Alpine dropdown injected into sidebar via `renderHook('panels::sidebar.nav.start', ...)` in `AdminPanelProvider`. Persists `session('active_event_id')` via `POST /event-switcher/switch`. `SetDefaultActiveEvent` listener sets first event on login.
- **Single-event dashboard** — `/admin/events` is a `ListRecords` table. Key resources (Registrations, Payments, Categories, ScanActions) auto-filter by active event via `->modifyQueryUsing()`. Don't forget this when adding new scoped resources.
- **`events.settings`** is JSONB accessed via `$event->settingEnabled('key')` (defaults to `true` if not set). The `Event` model has an accessor that merges defaults — **do not** add `'settings' => 'array'` cast alongside it. They conflict.
- `storage/app/keys/` is the documented drop folder for ConnectIPS `.pfx`/`.pem` files (already gitignored via `storage/app/.gitignore`).

## CSV import (two-phase)

`/admin/import-preview` → `ImportPreview` page → native file upload → `POST /import-upload` → `RegistrationsImport` stages rows in `import_staging` (status: `pending`) → per-row "Register" / "Skip" / bulk "Register Selected".

Gotchas:
- `+9779800000001` in CSV is converted to integer by the parser (drops the `+`). The import detects and re-prepends it.
- Use `$file->hashName()` not `$file->store()` to preserve the file extension for the Maatwebsite Excel reader.
- Column lookup is case-insensitive via the `col()` helper.

## Report downloads

Event detail page has 5 export actions. They use **web routes** (not API routes) and `auth` middleware (not `auth:sanctum`):

```
/reports/{event}/pdf-summary       → ReportDownloadController
/reports/{event}/payments          → ReportDownloadController
/reports/{event}/scanner-activity  → ReportDownloadController
/reports/{event}/category-summary  → ReportDownloadController
/reports/{event}/card-delivery     → ReportDownloadController
```

All `?format=xlsx` works. The API equivalents under `/api/reports/*` exist for the (removed) scanner PWA.

## Registration flow

- Public: `/event/{slug}/register` → `PublicRegistrationController`
- Onsite: `/admin/onsite-register/{event}` → `OnsiteRegistrationController` (auth + role: super_admin/admin/manager)
- CSV: see "CSV import" above
- Promo codes: `PromoCodeResource` under Events nav, applied in `PublicRegistrationController::store()`
- Early bird: `early_bird_price` + `early_bird_until` on `ParticipantCategory`
- Tax: `settings.tax_rate` on event, applied in `PaymentRedirector::initiate()`
- Waitlist: `settings.enable_waitlist` on event, sets `approval_status = 'waitlisted'`
- Companion booking: `companion_count` field on the form

## ConnectIPS payment

Full docs: `docs/connectips-integration.md`. Service code: `app/Services/Payment/ConnectIPSService.php` + `app/Services/Payment/PaymentRedirector.php`. Config: `config/connectips.php` (all env-driven).

- UAT: `https://uat.connectips.com:7443` — Production: `https://login.connectips.com` (no port)
- mTLS required for API calls. Private key in `storage/app/keys/CREDITOR.{pem,pfx}` (gitignored).
- **Key must be a proper 2048-bit RSA.** A 2047-bit or other malformed key will be accepted by `openssl_pkey_get_private` but `openssl_sign` fails with `bignum routines::no inverse` (OpenSSL 3.x is strict). If `connectips:test-flow` errors with this, regenerate: `php -r "openssl_pkey_export(openssl_pkey_new(['private_key_bits'=>2048,'private_key_type'=>OPENSSL_KEYTYPE_RSA]), \$pem); file_put_contents('storage/app/keys/CREDITOR.pem', \$pem);"` (with `OPENSSL_CONF` and `HOME` set, see Testing).
- 12 `CONNECTIPS_*` env vars (see `.env.example` or `.env.local`).
- Reconciliation fields persisted on `payments`: `gateway_txn_id`, `batch_id`, `debit_bank_code`, `charge_amount_paisa`, `credit_status`. `creditStatus` `000`/`999`/`DEFER` = merchant-side success.
- End-to-end test: `php artisan connectips:test-flow` (mocked) / `--live` (real NCHL UAT). Self-cleans.
- There is no refund API. Mark refunded in Filament; process the money in NCHL's merchant portal.
- There is no IPN/webhook. NCHL only supports redirect + manual `validateTxn`.

## SMS (Sociair / Sparrow)

Config: `config/services.php` → `services.sparrow`. Env vars: `SMS_DRIVER`, `SPARROW_SMS_TOKEN`, `SPARROW_SMS_BASE_URL`, `SMS_BATCH_SIZE`. Driver `log` = dev (console), `sparrow` = production. Sender ID is configured in the Sociair dashboard, not env.

## Scheduled tasks (`routes/console.php`)

| Task | Schedule | Command |
|---|---|---|
| DB backup | daily 02:00 | `db:backup --retain=90` |
| Event reminders | daily 09:00 | `event:send-reminders` |
| Post-event thanks | daily 10:00 | `event:send-thankyou` |
| Payment expiry | every 5 min | `payment:expire` |

## Testing, lint, deploy

```bash
composer test                          # config:clear + phpunit (via run-with-openssl-env.php on Windows)
php artisan test tests/Feature/ScanTest # single file
php artisan test --parallel            # parallel run
./vendor/bin/pint                      # fix code style
./vendor/bin/pint --test               # check only
php artisan dev:check                  # dev env: port scan, response integrity, sessions
php artisan deploy:check               # pre-deploy: config/route/view cache, DB, env, migrations, git
php artisan auth:diagnose [email] [pwd] # login diagnostic
php artisan connectips:test-flow [--live] # payment gateway end-to-end
```

Test layout: `tests/Feature/*Test.php` and `tests/Unit/{Models,Services}/*Test.php`. File-based SQLite (`database/testing.sqlite`), `RefreshDatabase` trait, 4 bcrypt rounds (fast).

**Windows OpenSSL env vars (mandatory):** `composer test` is wrapped by `tests/run-with-openssl-env.php` (called from `composer.json:55-58`), which sets `OPENSSL_CONF=C:\Users\User\.config\herd\openssl.cnf` and `HOME=C:\Users\User` via `putenv()` before spawning `php artisan test`. This is required because Herd's minimal `openssl.cnf` references `$ENV::HOME/.rnd` and PHP's openssl extension reads env at process startup (not at `putenv()` time). The wrapper is a no-op on non-Windows.

## Conventions

- PDF: `dompdf/dompdf` directly — **not** `barryvdh/laravel-dompdf` (incompatible with Laravel 13)
- Queue/Cache: database driver (no Redis — shared hosting friendly)
- Commit format: `feat:`, `fix:`, `docs:`, `chore:`
- The `scanner-app/` directory has been **removed** — physical scanner devices return GuestID directly. Scan API still works (`POST /api/scan` accepts QR hash, UUID, or guest number via `QRCodeService::resolve()`)
- Don't commit `storage/app/keys/*`, `.env`, or `.env.local`

## Docs worth referencing

- `CLAUDE.md` — project overview, full architecture, API routes
- `docs/connectips-integration.md` — full NCHL spec, field lengths, error reference
- `docs/feature-roadmap.md` — phase tracker, what's done vs. future
- `docs/architectural-design.md`, `docs/system-design.md` — design rationale
- `DEPLOY.md` — production deploy steps
