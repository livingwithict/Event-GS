<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class DevCheck extends Command
{
    protected $signature = 'dev:check
                            {--kill : Kill orphan PHP processes}
                            {--ports=8000,8001,8002,5173 : Comma-separated ports to scan}';

    protected $description = 'Check dev environment health — find orphan PHP servers, verify server is serving clean responses';

    private array $results = [];

    public function handle(): int
    {
        $ports = explode(',', $this->option('ports'));
        $ports = array_map('intval', $ports);
        $kill = $this->option('kill');

        $this->info("\n=== Dev Environment Health Check ===\n");

        // 1. PHP version
        $this->line('[1] PHP version');
        $this->line('    '.PHP_VERSION.' ('.PHP_OS.')');
        $this->line('    memory_limit: '.ini_get('memory_limit'));
        $this->line('    display_errors: '.ini_get('display_errors'));

        // 2. Scan all dev ports for PHP processes
        $this->line('[2] Dev server ports');
        $this->table(
            ['Port', 'PID', 'Command', 'Status'],
            collect($ports)->map(function (int $port) {
                $pid = $this->getPidOnPort($port);
                if (! $pid) {
                    return ['<fg=gray>'.$port.'</>', '—', '—', '<fg=gray>free</>'];
                }

                $cmd = $this->getProcessCommand($pid);
                $isPhpServe = str_contains($cmd, 'artisan serve') || str_contains($cmd, '-S');
                $age = $this->getProcessAge($pid);

                return [
                    $port,
                    (string) $pid,
                    substr($cmd, 0, 60),
                    $isPhpServe
                        ? '<fg=yellow>PHP dev server ('.$age.')</>'
                        : '<fg=cyan>other ('.$age.')</>',
                ];
            })->toArray()
        );

        // 3. Check for unexpected orphans (ports other than what we know about)
        $this->line('[3] Orphan detection');
        $orphans = $this->findOrphans($ports);
        if (empty($orphans)) {
            $this->line('    <fg=green>No orphans found ✓</>');
        } else {
            foreach ($orphans as $orphan) {
                $this->line("    <fg=red>Orphan PID {$orphan['pid']} on port {$orphan['port']}: {$orphan['command']} (started {$orphan['age']})</>");
            }
        }

        // 4. Verify each active dev server serves clean HTML (no PHP notices)
        $this->line('[4] Response integrity check');
        foreach ($ports as $port) {
            $pid = $this->getPidOnPort($port);
            if (! $pid) {
                continue;
            }

            try {
                $response = Http::withOptions(['verify' => false])
                    ->timeout(3)
                    ->get("http://127.0.0.1:{$port}/admin/login");

                if ($response->failed()) {
                    $this->line("    <fg=red>Port {$port}: HTTP {$response->status()} — not serving correctly</>");

                    continue;
                }

                $body = $response->body();
                $firstBytes = substr($body, 0, 100);

                if (str_contains($firstBytes, '<br') || str_contains($firstBytes, '<b>Notice</b>') || str_contains($firstBytes, '<b>Warning</b>')) {
                    $this->line("    <fg=red>Port {$port}: RESPONSE CORRUPTED — PHP Notice/Warning in output</>");
                    $this->line('    First 150 bytes: '.substr($firstBytes, 0, 150));
                } elseif (str_starts_with($firstBytes, '<!DOCTYPE')) {
                    $this->line("    <fg=green>Port {$port}: Clean HTML ✓ (starts with DOCTYPE)</>");
                } else {
                    $this->line("    <fg=yellow>Port {$port}: Unexpected response start: ".substr($firstBytes, 0, 80).'</>');
                }
            } catch (\Throwable $e) {
                $this->line("    <fg=red>Port {$port}: Connection failed — ".$e->getMessage().'</>');
            }
        }

        // 5. Session/database health
        $this->line('[5] Session store');
        $driver = config('session.driver');
        $this->line("    Driver: {$driver}");
        if ($driver === 'database') {
            try {
                $count = DB::table('sessions')->count();
                $this->line("    <fg=green>Sessions table accessible ({$count} active sessions) ✓</>");
            } catch (\Throwable $e) {
                $this->line('    <fg=red>Sessions table error: '.$e->getMessage().'</>');
            }
        }

        // 6. Cache state
        $this->line('[6] Cache state');
        $configCached = file_exists(app()->bootstrapPath('cache/config.php'));
        $routesCached = file_exists(app()->bootstrapPath('cache/routes-v7.php'));
        $eventsCached = file_exists(app()->bootstrapPath('cache/events.php'));
        $cached = [];
        if ($configCached) {
            $cached[] = 'config';
        }
        if ($routesCached) {
            $cached[] = 'routes';
        }
        if ($eventsCached) {
            $cached[] = 'events';
        }

        if (empty($cached)) {
            $this->line('    <fg=yellow>No caches — run php artisan optimize in production</>');
        } else {
            $this->line('    <fg=green>Cached: '.implode(', ', $cached).' ✓</>');
        }

        // 7. Log files
        $this->line('[7] Log files');
        $logDir = storage_path('logs');
        $logFiles = glob($logDir.'/*.log');
        $today = date('Y-m-d');
        foreach ($logFiles as $logFile) {
            $name = basename($logFile);
            $size = $this->humanFilesize(filesize($logFile));
            $pattern = '/\d{4}-\d{2}-\d{2}/';
            if (preg_match($pattern, $name, $m)) {
                $active = $name === 'laravel-'.$today.'.log';
                $marker = $active ? '<fg=green>ACTIVE</>' : '';
                $this->line("    {$name}: {$size} {$marker}");
            }
        }

        // 8. Cleanup advice
        if (! empty($orphans)) {
            $this->newLine();
            if ($kill) {
                $this->line('<fg=yellow>Killing orphan processes...</>');
                foreach ($orphans as $orphan) {
                    $result = posix_kill($orphan['pid'], 9);
                    $status = $result ? '<fg=green>KILLED</>' : '<fg=red>FAILED to kill</>';
                    $this->line("    PID {$orphan['pid']} (port {$orphan['port']}): {$status}");
                }
            } else {
                $this->line('<fg=yellow>Run with --kill to terminate orphan processes</>');
            }
        }

        // 9. Summary
        $this->newLine();
        $activeCount = count(array_filter($ports, fn ($p) => $this->getPidOnPort($p)));
        $this->info(" Summary: {$activeCount} active dev servers, ".count($orphans).' orphans, '.count($logFiles)." log files\n");

        return self::SUCCESS;
    }

    private function getPidOnPort(int $port): ?int
    {
        if (PHP_OS_FAMILY === 'Darwin' || PHP_OS_FAMILY === 'Linux') {
            $output = [];
            exec("lsof -i :{$port} -t 2>/dev/null", $output, $code);
            if ($code === 0 && ! empty($output)) {
                return (int) trim($output[0]);
            }
        }

        return null;
    }

    private function getProcessCommand(int $pid): string
    {
        if (PHP_OS_FAMILY === 'Darwin') {
            $output = [];
            exec("ps -p {$pid} -o command= 2>/dev/null", $output);

            return trim(implode(' ', $output));
        }
        if (PHP_OS_FAMILY === 'Linux') {
            $cmd = @file_get_contents("/proc/{$pid}/cmdline");
            if ($cmd !== false && $cmd !== '') {
                return str_replace("\0", ' ', trim($cmd));
            }
        }

        return '(unknown)';
    }

    private function getProcessAge(int $pid): string
    {
        if (PHP_OS_FAMILY === 'Darwin') {
            $output = [];
            exec("ps -p {$pid} -o lstart= 2>/dev/null", $output);
            $startTime = trim(implode(' ', $output));
            if ($startTime) {
                $start = strtotime($startTime);
                $diff = time() - $start;

                return $this->humanDuration($diff);
            }
        }

        return 'unknown';
    }

    private function findOrphans(array $knownPorts): array
    {
        if (PHP_OS_FAMILY !== 'Darwin' && PHP_OS_FAMILY !== 'Linux') {
            return [];
        }

        $output = [];
        exec("lsof -i -P -n 2>/dev/null | grep LISTEN | grep -E ':800[0-9]|:5173'", $output);

        $orphans = [];
        foreach ($output as $line) {
            if (preg_match('/^(\S+)\s+(\d+).*?:(\d+)\s+\(LISTEN\)/', $line, $m)) {
                $port = (int) $m[3];
                $pid = (int) $m[2];

                if (! in_array($port, $knownPorts)) {
                    $orphans[] = [
                        'port' => $port,
                        'pid' => $pid,
                        'command' => $this->getProcessCommand($pid),
                        'age' => $this->getProcessAge($pid),
                    ];
                }
            }
        }

        return $orphans;
    }

    private function humanDuration(int $seconds): string
    {
        $units = [
            'd' => 86400,
            'h' => 3600,
            'm' => 60,
            's' => 1,
        ];

        $parts = [];
        foreach ($units as $label => $div) {
            $q = intdiv($seconds, $div);
            if ($q > 0) {
                $parts[] = $q.$label;
                $seconds %= $div;
            }
        }

        return implode(' ', $parts) ?: 'just now';
    }

    private function humanFilesize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < 3) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 1).' '.$units[$i];
    }
}
