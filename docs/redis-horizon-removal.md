# Redis & Horizon Removal Reference

> **Removed on:** 2026-06-01
> **Reason:** Shared hosting deployment (Nest Nepal Cloud Babaal) has no Redis server. PHP Redis extension is available but no Redis daemon runs.
> **Current replacement:** Database driver for both queue and cache. Scanning uses direct PostgreSQL lookup.

---

## What Was Removed

### Packages (from `composer.json`)
- `laravel/horizon ^5.47` -- queue supervision dashboard
- `predis/predis ^3.4` -- Redis PHP client

### Deleted Files
- `app/Providers/HorizonServiceProvider.php` -- Horizon service registration
- `app/Listeners/UpdateRedisCache.php` -- cached QR scan results and event stats in Redis
- `config/horizon.php` -- Horizon supervisor configuration

### Modified Files
- `bootstrap/providers.php` -- removed `HorizonServiceProvider`
- `app/Providers/AppServiceProvider.php` -- removed `UpdateRedisCache` event listeners
- `.env.example` -- `QUEUE_CONNECTION=database` (was `redis`), removed all `REDIS_*` vars
- `docker-compose.yml` -- removed redis service, changed horizon to `queue:work`
- `composer.json` dev script -- `queue:work --tries=3 --stop-when-empty` (was `queue:listen`)

### Current Replacements
| Feature | Before (Redis) | After (Database) |
|---------|---------------|-------------------|
| Queue driver | Redis | Database (`jobs` table) |
| Cache driver | Redis | Database (`cache` table) |
| Queue monitoring | Horizon dashboard | `php artisan queue:failed` |
| QR scanning lookup | Redis first, then DB | Direct PostgreSQL |
| Event stats cache | `Redis::incr` counters | `Event::getStats()` DB query |
| Cron-based processing | Horizon supervisor | `php artisan queue:work --stop-when-empty` via cron |

---

## How to Re-add When Migrating to VPS

### Step 1: Reinstall packages

```bash
composer require laravel/horizon predis/predis
```

### Step 2: Recreate `app/Providers/HorizonServiceProvider.php`

```php
<?php

namespace App\Providers;

use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider {}
```

### Step 3: Recreate `app/Listeners/UpdateRedisCache.php`

```php
<?php

namespace App\Listeners;

use App\Events\EntryRecorded;
use App\Events\MealUsed;
use Illuminate\Support\Facades\Redis;

class UpdateRedisCache
{
    public function handleEntry(EntryRecorded $event): void
    {
        $reg = $event->registration;
        $eventId = $reg->event_id;

        Redis::setex("qr:{$reg->qr_hash}:entry", 86400, now()->toIso8601String());
        Redis::incr("event:{$eventId}:entries");
    }

    public function handleMeal(MealUsed $event): void
    {
        $reg = $event->registration;
        $eventId = $reg->event_id;
        $type = $event->mealType;

        Redis::setex("qr:{$reg->qr_hash}:{$type}", 86400, now()->toIso8601String());
        Redis::incr("event:{$eventId}:{$type}_used");
    }
}
```

### Step 4: Publish Horizon config

```bash
php artisan vendor:publish --tag=laravel-horizon
```

### Step 5: Re-register in `bootstrap/providers.php`

Add this line:

```php
App\Providers\HorizonServiceProvider::class,
```

### Step 6: Re-register listeners in `app/Providers/AppServiceProvider.php`

Add to `boot()` method:

```php
use App\Events\EntryRecorded;
use App\Events\MealUsed;
use App\Listeners\UpdateRedisCache;
use Illuminate\Support\Facades\Event;

Event::listen(EntryRecorded::class, [UpdateRedisCache::class, 'handleEntry']);
Event::listen(MealUsed::class, [UpdateRedisCache::class, 'handleMeal']);
```

### Step 7: Update `.env`

```env
QUEUE_CONNECTION=redis
CACHE_STORE=redis

REDIS_CLIENT=predis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

### Step 8: Update `composer.json` dev script

Change the queue command back to:

```
"php artisan queue:listen --tries=1 --timeout=0"
```

### Step 9: Update `docker-compose.yml`

Add Redis service back and change queue command to `php artisan horizon`.

---

## Redis Keys Reference

Keys used by `UpdateRedisCache` listener:

| Key Pattern | TTL | Purpose |
|-------------|-----|---------|
| `qr:{hash}:entry` | 24h | Entry timestamp for a QR code |
| `qr:{hash}:{meal_type}` | 24h | Meal usage timestamp for a QR code |
| `event:{id}:entries` | No expiry | Total entry counter for an event |
| `event:{id}:{meal_type}_used` | No expiry | Meal usage counter for an event |

Additional keys used by `IdempotentScan` middleware (already using `Cache` facade, works with both Redis and database):

| Key Pattern | TTL | Purpose |
|-------------|-----|---------|
| `scan:idempotent:{request_id}` | 5s | Deduplicate scan requests |
