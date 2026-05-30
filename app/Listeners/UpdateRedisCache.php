<?php

namespace App\Listeners;

use App\Events\EntryRecorded;
use App\Events\MealUsed;
use Illuminate\Support\Facades\Redis;

class UpdateRedisCache
{
    public function handleEntry(EntryRecorded $event): void
    {
        $hash = $event->registration->qr_hash;
        Redis::setex("qr:{$hash}:entry", 86400, now()->toIso8601String());

        $eventId = $event->registration->event_id;
        Redis::incr("event:{$eventId}:entries");
    }

    public function handleMeal(MealUsed $event): void
    {
        $hash = $event->registration->qr_hash;
        $type = $event->mealType;
        Redis::setex("qr:{$hash}:{$type}", 86400, now()->toIso8601String());

        $eventId = $event->registration->event_id;
        Redis::incr("event:{$eventId}:{$type}_used");
    }
}
