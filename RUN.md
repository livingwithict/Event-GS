# Running the Project (Dev)

Laravel 13 + Filament v5 event management app.

## Start it

```powershell
cd d:\ICT\event-management
.\start.ps1
```

That's it. The script checks PostgreSQL, clears config, runs migrations, then starts the
web server (`:8000`), the queue worker, and Vite (`:5173`) in the background — restarting
them first if they were already running. It prints the URL when the app answers.

Then open **http://localhost:8000/admin/login**

> Use `localhost`, **not** `127.0.0.1`. `.env` sets `SESSION_DOMAIN=localhost`; via
> `127.0.0.1` the cookie never matches and login loops silently.

Login: `admin@ictfoundation.org.np` / `password`

To stop everything:

```powershell
.\start.ps1 stop
```

Run `.\start.ps1` again after changing `.env` or `php.ini` — neither is re-read by a
running server.

---

## This machine

| | |
|---|---|
| PHP 8.4 | `C:\php\php.exe` (with `imagick` — required for label/ticket QR codes) |
| PostgreSQL 15 | service `postgresql-x64-15`, database `event_management`, user `postgres` |
| Project | `d:\ICT\event-management` |
| Logs | `%USERPROFILE%\dev-logs\{serve,queue,vite}-{out,err}.log` |

The dev DB is PostgreSQL and uses `EXTRACT(HOUR FROM ...)::int` and JSONB — **not**
SQLite-compatible.

## First run only

```powershell
Copy-Item .env.local .env     # then set DB_PASSWORD
composer install
npm install
php artisan migrate
php artisan db:seed --class=RoleSeeder
```

## Health check

```powershell
php artisan dev:check
Get-Content "$env:USERPROFILE\dev-logs\serve-err.log" -Tail 20
```

`dev:check` should report clean HTML and an accessible sessions table. If the response
integrity check fails, the PHP server is corrupt — `.\start.ps1` to restart it.

## Tests

```powershell
php tests/run-with-openssl-env.php     # wrapper sets OPENSSL_CONF for the payment tests
php artisan test --filter=ScanStation  # single suite
```

## Why the script does what it does

- **`-d display_errors=Off`** — PHP's dev server logs to `php://stdout`. When stdout is a
  detached pipe it prepends `Notice: ... Broken pipe` to every response; Livewire JSON then
  fails with `SyntaxError: Unexpected token '<'` and login dies silently.
- **npm via `cmd.exe /c`** — `npm` is a `.cmd` shim, not a Win32 binary; `Start-Process npm`
  fails with "%1 is not a valid Win32 application".
- **Separate out/err log files** — `Start-Process` cannot redirect both streams to one file.