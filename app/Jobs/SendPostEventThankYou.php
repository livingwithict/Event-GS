<?php

namespace App\Jobs;

use App\Models\Event;
use App\Models\Registration;
use App\Services\CommunicationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendPostEventThankYou implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public function handle(CommunicationService $service): void
    {
        $now = now();
        $yesterday = $now->copy()->subDay();

        $events = Event::whereNotNull('end_datetime')
            ->whereBetween('end_datetime', [$yesterday, $now])
            ->get();

        foreach ($events as $event) {
            if (! $event->settingEnabled('enable_notifications')) {
                continue;
            }

            $registrations = Registration::where('event_id', $event->id)
                ->whereDoesntHave('communications', fn ($q) => $q->where('email_type', 'post_event_thank_you')->where('status', 'sent'))
                ->get();

            foreach ($registrations as $registration) {
                try {
                    $service->sendPostEventThankYou($registration, $event);
                } catch (\Throwable $e) {
                    logger()->error("Failed to send thank you to registration {$registration->id}: ".$e->getMessage());
                }
            }
        }
    }
}
