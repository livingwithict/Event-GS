<?php

namespace App\Console\Commands;

use App\Jobs\SendPostEventThankYou as SendPostEventThankYouJob;
use Illuminate\Console\Command;

class SendPostEventThankYou extends Command
{
    protected $signature = 'event:send-thankyou';

    protected $description = 'Send post-event thank you notifications for recently ended events';

    public function handle(): int
    {
        SendPostEventThankYouJob::dispatch();

        $this->info('Post-event thank you job dispatched successfully.');

        return self::SUCCESS;
    }
}
