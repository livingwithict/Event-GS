<?php

namespace App\Jobs;

use App\Services\CommunicationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendBulkSMS implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public array $registrationIds,
        public int $eventId,
        public string $message,
    ) {
        $this->onQueue('high');
    }

    public function handle(CommunicationService $service): void
    {
        foreach ($this->registrationIds as $regId) {
            $reg = \App\Models\Registration::find($regId);
            if ($reg && $reg->phone) {
                $service->sendSms($reg, $this->message);
            }
        }
    }
}
