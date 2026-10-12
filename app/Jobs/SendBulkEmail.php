<?php

namespace App\Jobs;

use App\Models\Event;
use App\Models\Registration;
use App\Services\CommunicationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendBulkEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public array $registrationIds,
        public int $eventId,
        public string $subject,
        public string $emailType = 'invitation',
        public ?int $draftId = null,
    ) {
        $this->onQueue('high');
    }

    public function handle(CommunicationService $service): void
    {
        $event = Event::findOrFail($this->eventId);

        $draft = $this->draftId ? $event->emailDrafts()->find($this->draftId) : null;
        $emailType = $draft?->communicationType() ?? $this->emailType;

        $first = true;

        foreach ($this->registrationIds as $regId) {
            $reg = Registration::find($regId);

            if (! $reg || ! $reg->email) {
                continue;
            }

            // Resend allows 2 requests/second; anything faster comes back 429.
            if (! $first) {
                usleep(600_000);
            }
            $first = false;

            $service->sendEmail($reg, $event, $this->subject, $emailType, $draft);
        }
    }
}
