# Running the Project (Dev)

Laravel 13 + Filament v5 event management app. Below is everything you need to launch the three dev servers on **Windows + Herd** (PHP + nginx only — no DB bundled).

> For macOS/Linux/WSL, see the bash snippet at the bottom. For full architecture, model relationships, and API surface, see [`CLAUDE.md`](CLAUDE.md). For the gotcha list, see [`AGENTS.md`](AGENTS.md).

---

## 1. Prerequisites (one-time)

| Requirement | Notes |
|---|---|
| PHP 8.4 via Herd | `C:\Users\User\.config\herd\bin` — **not** on PATH by default. Prepend it in every shell (see step 3). |
| PostgreSQL 15 | Install via the official EDB installer (`https://get.enterprisedb.com/...postgresql-15.13-1-windows-x64.exe`). **Do not** use `winget` — it leaves an incomplete install. Service `postgresql-x64-15` auto-starts. `psql` at `C:\Program Files\PostgreSQL\15\bin\psql.exe`. |
| Node.js + npm | For Vite asset building. |
| Composer | `C:\Users\User\.config\herd\bin\composer.bat` proxies correctly. |

The dev DB is PostgreSQL (`event_management`), superuser `postgres` on Windows. It uses `EXTRACT(HOUR FROM ...)::int` and JSONB — **not** SQLite-compatible.

---

## 2. Prepare `.env`

There is **no `.env`** by default — `.env.example` (pgsql config) and `.env.local` (pgsql config + Sparrow token) are templates.

```powershell
Copy-Item .env.local .env        # then edit .env and set DB_PASSWORD
```

Then verify required values:

| Key | Value |
|---|---|
| `DB_CONNECTION` | `pgsql` |
| `DB_DATABASE` | `event_management` |
| `DB_USERNAME` | `postgres` |
| `DB_PASSWORD` | *(your install-time password)* |
| `SESSION_DOMAIN` | `localhost` ← critical (see Login section) |
| `SMS_DRIVER` | `log` (dev) / `sparrow` (prod) |
| `CONNECTIPS_*` | 12 vars — see `.env.example` (only needed for payment testing) |

> `.env.local` is **not** auto-loaded by Laravel or Herd — it's a checked-in template only.

---

## 3. Open a PowerShell prompt and prepend Herd's bin

Every new shell session needs this — `php`/`composer` are not on PATH by default:

```powershell
$env:Path = "C:\Users\User\.config\herd\bin;" + $env:Path
```

---

## 4. Install dependencies (first run only)

```powershell
composer install
npm install
```

---

## 5. Run migrations & seed

```powershell
php artisan config:clear      # mandatory after any .env change
php artisan migrate
php artisan db:seed --class=RoleSeeder
```

Seeded login: **`admin@ictfoundation.org.np`** / **`password`**.

Verify migrations are all applied:

```powershell
php artisan migrate:status
```

---

## 6. Launch the three dev servers

Run this block from the project root. It creates a `~/dev-logs/` folder and starts each process in the background with **separate** stdout/stderr files (required on Windows — writing both to the same file fails).

```powershell
$env:Path = "C:\Users\User\.config\herd\bin;" + $env:Path
$proj = "C:\Users\User\Documents\GitHub\event-management"
$log  = "$env:USERPROFILE\dev-logs"; New-Item -ItemType Directory -Path $log -Force | Out-Null

php artisan config:clear

# 1) PHP dev server (port 8000)
#    -d display_errors=Off prevents broken-pipe notices from corrupting Livewire/JSON responses
Start-Process -FilePath php -ArgumentList "-d","display_errors=Off","artisan","serve","--port=8000" `
  -WorkingDirectory $proj -RedirectStandardOutput "$log\serve-out.log" -RedirectStandardError "$log\serve-err.log" -WindowStyle Hidden

# 2) Queue worker (database driver — handles payment expiry, SMS, reminders)
Start-Process -FilePath php -ArgumentList "artisan","queue:work","--tries=3" `
  -WorkingDirectory $proj -RedirectStandardOutput "$log\queue-out.log" -RedirectStandardError "$log\queue-err.log" -WindowStyle Hidden

# 3) Vite dev server (port 5173) — npm is a .cmd shim, must invoke through cmd.exe
Start-Process -FilePath cmd.exe -ArgumentList "/c","npm","run","dev" `
  -WorkingDirectory $proj -RedirectStandardOutput "$log\vite-out.log" -RedirectStandardError "$log\vite-err.log" -WindowStyle Hidden

Start-Sleep 6
php artisan dev:check
```

### Why each quirk matters

- **`-d display_errors=Off`** — PHP's built-in dev server logs to `php://stdout`. When stdout is a closed pipe (detached terminal), PHP emits `Notice: file_put_contents() ... Broken pipe` and **prepends** it to every response. HTML pages get a `<br /><b>Notice</b>` banner; Livewire JSON breaks with `SyntaxError: Unexpected token '<'` → login silently fails. Redirecting to log files + disabling display_errors prevents this.
- **npm via `cmd.exe /c`** — `npm` is a `.cmd` shim, not a Win32 binary. `Start-Process -FilePath npm` fails with "%1 is not a valid Win32 application".
- **Separate out/err files** — `RedirectStandardOutput` and `RedirectStandardError` cannot point to the same file.
- **`-WorkingDirectory` explicitly** — `$PWD` does not persist between separate tool invocations.

---

## 7. Open the app

Browse to **http://localhost:8000/admin/login**

> ⚠️ Use **`localhost`**, not `127.0.0.1`. `.env` sets `SESSION_DOMAIN=localhost`; accessing via `127.0.0.1` won't match → cookies never sent → sessions never persisted → login loops silently.

Login: `admin@ictfoundation.org.np` / `password`

---

## 8. Verify it's healthy

```powershell
php artisan dev:check
```

Expected: "Clean HTML ✓", 0 orphans, sessions table accessible. If the response integrity check fails, the PHP server is corrupt — kill everything and restart (see Stop / Restart below).

Quick manual check — first 50 bytes of the login page **must** start with `<!DOCTYPE`:

```powershell
(curl http://localhost:8000/admin/login | Select-Object -ExpandProperty Content).Substring(0,50)
```

If it starts with `<br`, restart the PHP server — broken-pipe notices have corrupted it.

---

## Stop / Restart the servers

```powershell
# Kill anything listening on the dev ports
Get-NetTCPConnection -LocalPort 8000,8001,8002,5173 -State Listen -ErrorAction SilentlyContinue |
  ForEach-Object { Stop-Process -Id $_.OwningProcess -Force }
```

Then re-run the launch block in step 6.

---

## Tail the logs

```powershell
# Tail all
Get-Content "$env:USERPROFILE\dev-logs\serve-out.log" -Wait
Get-Content "$env:USERPROFILE\dev-logs\vite-out.log"  -Wait
Get-Content "$env:USERPROFILE\dev-logs\queue-out.log" -Wait

# Auth-specific (check this first if login fails silently)
Get-Content "storage\logs\auth-$(Get-Date -Format yyyy-MM-dd).log" -Wait
```

---

## Login stuck / looping? (in order of likelihood)

1. **`SESSION_DOMAIN` mismatch** — accessing `http://127.0.0.1:8000` with `SESSION_DOMAIN=localhost` → cookies never sent. Always use `http://localhost:8000`.
2. **SPA mode exceptions removed** — `->spa()` is enabled in `AdminPanelProvider` with `/admin/login` and `/admin/logout` as `spaUrlExceptions`. Removing them → Livewire intercepts the post-login redirect with a stale CSRF → 419 → no redirect.
3. **Broken pipe notices** — run `php artisan dev:check`; rebuild the server if "Clean HTML" is missing.

Diagnostic: `php artisan auth:diagnose admin@ictfoundation.org.np password --attempt` runs a 7-point check including a live HTTP login.

---

## macOS / Linux / WSL equivalent

```bash
pkill -9 -f "artisan serve|queue:work|vite" 2>/dev/null

php artisan config:clear

nohup php -d display_errors=Off artisan serve --port=8000 > /tmp/serve-stdout.log 2> /tmp/serve-stderr.log &
php artisan queue:work --tries=3 > /tmp/queue.log 2>&1 &
npm run dev &

php artisan dev:check
```

> `composer dev` is **broken** — it tries to run `php artisan horizon` which is in `dont-discover` (`composer.json:81-83`). Use the manual blocks above.

---

## Common commands

| Command | Purpose |
|---|---|
| `php artisan dev:check` | Port scan, response integrity, sessions, cache state |
| `php artisan config:clear` | Mandatory after any `.env` change or DB switch |
| `php artisan migrate` | Apply new migrations (never edit applied ones) |
| `php artisan db:seed --class=RoleSeeder` | Seed roles + admin user |
| `php artisan auth:diagnose [email] [pwd] --attempt` | 7-point login diagnostic |
| `./vendor/bin/pint` | Fix code style |
| `composer test` | PHPUnit (file-based SQLite, wrapped for OpenSSL env on Windows) |
| `php artisan connectips:test-flow` | ConnectIPS payment gateway end-to-end (mocked) |
| `php artisan deploy:check` | Pre-deploy validation |