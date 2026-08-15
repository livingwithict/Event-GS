# Starts (or restarts) the dev stack: web server, queue worker, Vite.
#   .\start.ps1          start everything
#   .\start.ps1 stop     stop everything
param([ValidateSet('start', 'stop')][string]$Action = 'start')

$ErrorActionPreference = 'Stop'
$proj = $PSScriptRoot
$php = if (Get-Command php -ErrorAction SilentlyContinue) { (Get-Command php).Source } else { 'C:\php\php.exe' }
$log = Join-Path $env:USERPROFILE 'dev-logs'
New-Item -ItemType Directory -Path $log -Force | Out-Null

function Stop-Stack {
    Get-CimInstance Win32_Process -Filter "Name='php.exe'" |
        Where-Object { $_.CommandLine -match 'artisan (serve|queue)' -or $_.CommandLine -match 'resources/server\.php' } |
        ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }

    Get-CimInstance Win32_Process -Filter "Name='node.exe'" |
        Where-Object { $_.CommandLine -match 'vite|npm-cli\.js" run dev' } |
        ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }
}

function Start-Bg($file, $arguments, $name) {
    # Separate out/err files: Start-Process cannot point both at one file.
    Start-Process -FilePath $file -ArgumentList $arguments -WorkingDirectory $proj `
        -RedirectStandardOutput "$log\$name-out.log" -RedirectStandardError "$log\$name-err.log" -WindowStyle Hidden
}

Stop-Stack

if ($Action -eq 'stop') {
    Write-Host 'Stopped.' -ForegroundColor Yellow
    exit
}

if ((Get-Service postgresql* -ErrorAction SilentlyContinue | Where-Object Status -eq 'Running').Count -eq 0) {
    Write-Host 'PostgreSQL is not running - start the postgresql-x64-15 service first.' -ForegroundColor Red
    exit 1
}

& $php artisan config:clear | Out-Null
& $php artisan migrate --force

# Warm the caches that cost the most per request. Blade and Filament still
# recompile changed files, so this is safe while developing. Not config:cache —
# that would freeze .env.
& $php artisan filament:optimize | Out-Null
& $php artisan view:cache | Out-Null

# display_errors=Off keeps PHP notices out of Livewire JSON responses (breaks login).
Start-Bg $php '-d display_errors=Off artisan serve --port=8000' 'serve'
# SendBulkEmail dispatches onto "high"; without naming it those jobs never run.
Start-Bg $php 'artisan queue:work --queue=high,default --tries=3' 'queue'
# npm is a .cmd shim, so it has to go through cmd.exe.
Start-Bg 'cmd.exe' '/c npm run dev' 'vite'

Start-Sleep 3

# The first request compiles views and can take 20s+ on a cold start, so retry
# rather than calling it dead.
$ok = $false
foreach ($attempt in 1..3) {
    try {
        $r = Invoke-WebRequest 'http://localhost:8000/admin/login' -UseBasicParsing -TimeoutSec 45
        $ok = $r.StatusCode -eq 200 -and $r.Content.StartsWith('<!DOCTYPE')
        if ($ok) { break }
    } catch { Start-Sleep 3 }
}

if ($ok) {
    Write-Host "`nReady -> http://localhost:8000/admin/login" -ForegroundColor Green
} else {
    Write-Host "`nServer did not respond. Check $log\serve-err.log" -ForegroundColor Red
}