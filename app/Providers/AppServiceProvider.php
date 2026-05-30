<?php

namespace App\Providers;

use App\Events\EntryRecorded;
use App\Events\MealUsed;
use App\Listeners\UpdateRedisCache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(EntryRecorded::class, [UpdateRedisCache::class, 'handleEntry']);
        Event::listen(MealUsed::class, [UpdateRedisCache::class, 'handleMeal']);
    }
}
