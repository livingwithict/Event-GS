<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class BackupDatabase extends Command
{
    protected $signature = 'db:backup {--retain=90 : Days to retain backups}';

    protected $description = 'Create a PostgreSQL database backup';

    public function handle(): int
    {
        $retain = (int) $this->option('retain');
        $filename = 'backup-'.now()->format('Y-m-d-His').'.sql.gz';
        $path = storage_path('app/backups');

        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }

        $db = config('database.connections.pgsql');
        $filepath = "{$path}/{$filename}";

        $command = sprintf(
            'PGPASSWORD=%s pg_dump -h %s -p %s -U %s %s | gzip > %s',
            escapeshellarg($db['password'] ?? ''),
            escapeshellarg($db['host'] ?? '127.0.0.1'),
            escapeshellarg($db['port'] ?? '5432'),
            escapeshellarg($db['username'] ?? ''),
            escapeshellarg($db['database'] ?? ''),
            escapeshellarg($filepath)
        );

        $result = null;
        system($command, $result);

        if ($result === 0 && file_exists($filepath)) {
            $this->info("Backup created: {$filepath}");

            // Clean old backups
            $this->cleanOldBackups($path, $retain);

            return self::SUCCESS;
        }

        $this->error('Backup failed. Ensure pg_dump is installed and DB credentials are correct.');

        return self::FAILURE;
    }

    private function cleanOldBackups(string $path, int $retainDays): void
    {
        $cutoff = now()->subDays($retainDays)->timestamp;
        $deleted = 0;

        foreach (glob("{$path}/backup-*.sql.gz") as $file) {
            if (filemtime($file) < $cutoff) {
                unlink($file);
                $deleted++;
            }
        }

        if ($deleted > 0) {
            $this->info("Cleaned {$deleted} old backup(s) beyond {$retainDays} days.");
        }
    }
}
