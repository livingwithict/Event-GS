<?php

namespace App\Jobs;

use App\Models\Registration;
use App\Services\CommunicationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendBulkSMS implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public array $registrationIds,
        public int $eventId,
        public string $message,
        public ?string $emailType = null,
        public int $batchSize = 0,
    ) {
        if ($this->batchSize <= 0) {
            $this->batchSize = (int) config('services.sparrow.batch_size', 50);
        }
        $this->onQueue('high');
    }

    public function handle(CommunicationService $service): void
    {
        // Landlines sit in the same column as mobiles since phone is free text;
        // sending to them just burns credits on a guaranteed rejection.
        $registrationIds = array_filter($this->registrationIds, function ($regId) {
            $reg = Registration::find($regId);

            return $reg && $reg->hasMobileNumber();
        });

        if (empty($registrationIds)) {
            return;
        }

        if ($this->batchSize === 1) {
            foreach ($registrationIds as $regId) {
                $reg = Registration::find($regId);
                $service->sendSms($reg, $this->message, $this->emailType);

            }

            return;
        }

        foreach (array_chunk($registrationIds, $this->batchSize) as $chunk) {
            $service->sendBatchSms($chunk, $this->message, $this->emailType);
        }
    }
}
