<?php

namespace App\Console\Commands;

use App\Jobs\SendEventReminders as SendEventRemindersJob;
use Illuminate\Console\Command;

class SendEventReminders extends Command
{
    protected $signature = 'event:send-reminders';

    protected $description = 'Send reminder notifications for events happening within 24 hours';

    public function handle(): int
    {
        SendEventRemindersJob::dispatch();

        $this->info('Event reminder job dispatched successfully.');

        return self::SUCCESS;
    }
}
