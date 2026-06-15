<?php

namespace App\Console\Commands;

use App\Models\Payment;
use Illuminate\Console\Command;

class ExpirePayments extends Command
{
    protected $signature = 'payment:expire';

    protected $description = 'Mark pending/initiated payments as expired if past their expiry time';

    public function handle(): int
    {
        $expired = Payment::whereIn('payment_status', ['pending', 'initiated'])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->update(['payment_status' => 'expired']);

        if ($expired > 0) {
            $this->info("Marked {$expired} payments as expired.");
        }

        return self::SUCCESS;
    }
}
