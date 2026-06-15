<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateMySqlToPostgres extends Command
{
    protected $signature = 'db:migrate-mysql-to-pgsql {--chunk=500 : Chunk size for batch processing}';

    protected $description = 'Migrate data from MySQL to PostgreSQL connection';

    private array $order = [
        'users',
        'events',
        'participant_categories',
        'registrations',
        'payments',
        'scan_action_types',
        'scan_logs',
        'communications',
        'label_templates',
        'import_batches',
        'import_errors',
    ];

    public function handle(): int
    {
        $mysql = DB::connection('mysql');
        $pgsql = DB::connection('pgsql');

        if ($pgsql->table('users')->count() > 0) {
            if (! $this->confirm('PostgreSQL already has data. Continue and skip duplicates?')) {
                $this->info('Aborted.');

                return 0;
            }
        }

        $chunkSize = (int) $this->option('chunk');

        foreach ($this->order as $table) {
            $this->info("Migrating {$table}...");

            $count = $mysql->table($table)->count();
            $bar = $this->output->createProgressBar($count);
            $inserted = 0;

            $mysql->table($table)->orderBy('id')->chunk($chunkSize, function ($rows) use ($pgsql, $table, $bar, &$inserted) {
                $data = collect($rows)->map(fn ($r) => (array) $r)->toArray();

                foreach ($data as $row) {
                    try {
                        $pgsql->table($table)->insert($row);
                        $inserted++;
                    } catch (\Throwable $e) {
                        // Skip duplicate primary keys silently
                        if (! str_contains($e->getMessage(), 'duplicate key')) {
                            $this->warn("  Error on {$table} row: ".$e->getMessage());
                        }
                    }
                }

                $bar->advance(count($rows));
            });

            $bar->finish();
            $this->newLine();
            $this->info("  Inserted {$inserted} rows into {$table}.");
        }

        // Reset PostgreSQL sequences to avoid key conflicts on future inserts
        foreach ($this->order as $table) {
            $maxId = $pgsql->table($table)->max('id') ?? 0;
            DB::statement("SELECT setval('{$table}_id_seq', {$maxId})");
        }

        $this->info('Migration complete. Verify data integrity before switching connections.');

        return 0;
    }
}
