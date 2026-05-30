<?php

namespace App\Jobs;

use App\Models\Event;
use App\Services\CommunicationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendBulkEmail implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public array $registrationIds,
        public int $eventId,
        public string $subject,
    ) {
        $this->onQueue('high');
    }

    public function handle(CommunicationService $service): void
    {
        $event = Event::findOrFail($this->eventId);

        foreach ($this->registrationIds as $regId) {
            $reg = \App\Models\Registration::find($regId);
            if ($reg && $reg->email) {
                $service->sendEmail($reg, $event, $this->subject);
            }
        }
    }
}
