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

class SendEventReminders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(CommunicationService $service): void
    {
        $now = now();
        $tomorrow = $now->copy()->addDay();

        $events = Event::where('status', 'published')
            ->whereNotNull('start_datetime')
            ->whereBetween('start_datetime', [$now, $tomorrow])
            ->get();

        foreach ($events as $event) {
            if (! $event->settingEnabled('enable_notifications')) {
                continue;
            }

            $registrations = Registration::where('event_id', $event->id)
                ->whereDoesntHave('communications', fn ($q) => $q->where('email_type', 'event_reminder')->where('status', 'sent'))
                ->get();

            foreach ($registrations as $registration) {
                try {
                    $service->sendEventReminder($registration, $event);
                } catch (\Throwable $e) {
                    logger()->error("Failed to send reminder to registration {$registration->id}: ".$e->getMessage());
                }
            }
        }
    }
}
