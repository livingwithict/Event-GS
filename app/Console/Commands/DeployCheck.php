<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DeployCheck extends Command
{
    protected $signature = 'deploy:check';

    protected $description = 'Run all pre-deployment checks: config, migrations, cache, and database connectivity';

    public function handle(): int
    {
        $this->info('=== Pre-Deployment Checklist ===');
        $this->newLine();
        $allPassed = true;

        // 1. Config cache check
        $this->line('[1] Config cache');
        $configCached = app()->configurationIsCached();
        if (! $configCached) {
            $this->warn('    Config is NOT cached. Run: php artisan config:cache');
        } else {
            $this->line('    <fg=green>Config cached ✓</>');
        }

        // 2. Route cache check
        $this->line('[2] Route cache');
        $routesCached = app()->routesAreCached();
        if (! $routesCached) {
            $this->warn('    Routes are NOT cached. Run: php artisan route:cache');
        } else {
            $this->line('    <fg=green>Routes cached ✓</>');
        }

        // 3. Event cache check
        $this->line('[3] Event cache');
        $eventsCached = app()->eventsAreCached();
        if (! $eventsCached) {
            $this->warn('    Events are NOT cached. Run: php artisan event:cache');
        } else {
            $this->line('    <fg=green>Events cached ✓</>');
        }

        // 4. View cache check
        $this->line('[4] View cache');
        $viewsCached = file_exists(storage_path('framework/views/compile.php'));
        if (! $viewsCached) {
            $this->warn('    Views are NOT cached. Run: php artisan view:cache');
        } else {
            $this->line('    <fg=green>Views cached ✓</>');
        }

        // 5. Database connection
        $this->line('[5] Database connection');
        try {
            DB::connection()->getPdo();
            $this->line('    <fg=green>Connected ✓</> ('.config('database.default').')');
        } catch (\Throwable $e) {
            $this->error('    Connection FAILED: '.$e->getMessage());
            $allPassed = false;
        }

        // 6. Pending migrations
        $this->line('[6] Pending migrations');
        try {
            $repository = app('migration.repository');
            $ran = $repository->getRan();
            $files = glob(database_path('migrations').'/*.php');
            $pending = array_values(array_filter($files, function ($file) use ($ran) {
                $name = basename($file, '.php');

                return ! in_array($name, $ran);
            }));
            $count = count($pending);
            if ($count > 0) {
                $this->warn("    {$count} pending migration(s):");
                foreach ($pending as $m) {
                    $this->line('    - '.basename($m));
                }
            } else {
                $this->line('    <fg=green>All migrations applied ✓</>');
            }
        } catch (\Throwable $e) {
            $this->error('    Check FAILED: '.$e->getMessage());
            $allPassed = false;
        }

        // 7. APP_KEY check
        $this->line('[7] APP_KEY');
        if (empty(env('APP_KEY')) || env('APP_KEY') === '') {
            $this->error('    APP_KEY is missing! Run: php artisan key:generate');
            $allPassed = false;
        } else {
            $this->line('    <fg=green>Present ✓</>');
        }

        // 8. APP_DEBUG check
        $this->line('[8] APP_DEBUG');
        if (app()->hasDebugModeEnabled()) {
            $this->error('    Debug mode is ON. Set APP_DEBUG=false in production!');
            $allPassed = false;
        } else {
            $this->line('    <fg=green>Debug OFF ✓</>');
        }

        // 9. Storage link
        $this->line('[9] Storage link');
        $linkPath = public_path('storage');
        if (file_exists($linkPath) && is_link($linkPath)) {
            $target = readlink($linkPath);
            $this->line('    <fg=green>Linked ✓</> (public/storage → '.$target.')');
        } else {
            $this->warn('    Not linked. Run: php artisan storage:link');
        }

        // 10. Required env vars present
        $this->line('[10] Required environment variables');
        $required = [
            'APP_NAME', 'APP_URL', 'DB_CONNECTION', 'DB_HOST', 'DB_PORT', 'DB_DATABASE',
            'DB_USERNAME', 'MAIL_MAILER', 'MAIL_FROM_ADDRESS',
        ];
        $missing = [];
        foreach ($required as $var) {
            if (empty(env($var))) {
                $missing[] = $var;
            }
        }
        if (! empty($missing)) {
            $this->warn('    Missing: '.implode(', ', $missing));
        } else {
            $this->line('    <fg=green>All present ✓</>');
        }

        // 11. ConnectIPS vars
        $this->line('[11] ConnectIPS configuration');
        $cipsVars = ['CONNECTIPS_BASE_URL', 'CONNECTIPS_MERCHANT_ID', 'CONNECTIPS_APP_ID',
            'CONNECTIPS_APP_PASSWORD', 'CONNECTIPS_PRIVATE_KEY_PATH'];
        $cipsMissing = [];
        foreach ($cipsVars as $var) {
            if (empty(env($var))) {
                $cipsMissing[] = $var;
            }
        }
        if (! empty($cipsMissing)) {
            $this->warn('    Missing: '.implode(', ', $cipsMissing));
        } else {
            $this->line('    <fg=green>All present ✓</>');
        }

        // Verify key file exists
        $keyPath = env('CONNECTIPS_PRIVATE_KEY_PATH');
        if (! empty($keyPath)) {
            if (file_exists($keyPath)) {
                $perms = decoct(fileperms($keyPath) & 0777);
                $this->line('    Key file: '.$keyPath.' (perms: '.$perms.')');
                if ($perms !== '600') {
                    $this->warn('    Key file permissions should be 600. Run: chmod 600 '.$keyPath);
                }
            } else {
                $this->error('    Key file NOT FOUND: '.$keyPath);
                $allPassed = false;
            }
        }

        // 12. Queue connection
        $this->line('[12] Queue connection');
        $queueConn = config('queue.default');
        $this->line('    Driver: '.$queueConn);
        if ($queueConn === 'sync') {
            $this->warn('    Sync driver in use — emails/SMS will block requests!');
        }

        // 13. PHP extensions
        $this->line('[13] Required PHP extensions');
        $extensions = ['pdo', 'pdo_pgsql', 'pdo_mysql', 'openssl', 'fileinfo', 'mbstring',
            'tokenizer', 'xml', 'ctype', 'json', 'bcmath', 'gd', 'curl'];
        $missingExt = [];
        foreach ($extensions as $ext) {
            if (! extension_loaded($ext)) {
                $missingExt[] = $ext;
            }
        }
        if (! empty($missingExt)) {
            $this->warn('    Missing: '.implode(', ', $missingExt));
        } else {
            $this->line('    <fg=green>All loaded ✓</>');
        }

        // 14. PHP memory limit
        $this->line('[14] PHP memory limit');
        $memory = ini_get('memory_limit');
        $memoryBytes = $this->toBytes($memory);
        if ($memoryBytes < 128 * 1024 * 1024) {
            $this->warn('    Low memory limit: '.$memory.' (recommend ≥128M)');
        } else {
            $this->line('    <fg=green>'.$memory.' ✓</>');
        }

        // 15. Git status
        $this->line('[15] Git status');
        exec('cd '.base_path().' && git status --short 2>/dev/null', $gitOutput, $gitCode);
        if ($gitCode === 0 && count($gitOutput) > 0) {
            $this->warn('    Uncommitted changes: '.count($gitOutput).' file(s)');
        } elseif ($gitCode === 0) {
            $this->line('    <fg=green>Clean ✓</>');
        } else {
            $this->line('    <fg=yellow>Not a git repo or git not found</>');
        }

        $this->newLine();
        if ($allPassed) {
            $this->info(' All critical checks passed. Ready to deploy.');
        } else {
            $this->error(' Some critical checks failed. Fix them before deploying.');
        }

        return $allPassed ? self::SUCCESS : self::FAILURE;
    }

    private function toBytes(string $value): int
    {
        $value = trim($value);
        $unit = strtolower(substr($value, -1));
        $number = (int) substr($value, 0, -1);
        switch ($unit) {
            case 'g': return $number * 1024 * 1024 * 1024;
            case 'm': return $number * 1024 * 1024;
            case 'k': return $number * 1024;
            default: return (int) $value;
        }
    }
}
