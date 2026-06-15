<?php

namespace App\Listeners;

use App\Events\EntryRecorded;
use App\Events\MealUsed;
use Illuminate\Support\Facades\Redis;

class UpdateRedisCache
{
    public function handleEntry(EntryRecorded $event): void
    {
        if (! $this->redisAvailable()) {
            return;
        }

        $reg = $event->registration;
        $eventId = $reg->event_id;

        Redis::setex("qr:{$reg->qr_hash}:entry", 86400, now()->toIso8601String());
        Redis::incr("event:{$eventId}:entries");
    }

    public function handleMeal(MealUsed $event): void
    {
        if (! $this->redisAvailable()) {
            return;
        }

        $reg = $event->registration;
        $eventId = $reg->event_id;
        $type = $event->mealType;

        Redis::setex("qr:{$reg->qr_hash}:{$type}", 86400, now()->toIso8601String());
        Redis::incr("event:{$eventId}:{$type}_used");
    }

    private function redisAvailable(): bool
    {
        return config('queue.default') === 'redis' && extension_loaded('redis');
    }
}
