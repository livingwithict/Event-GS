<?php

namespace App\Providers;

use App\Events\EntryRecorded;
use App\Events\MealUsed;
use App\Listeners\LogAuthenticationEvents;
use App\Listeners\SetDefaultActiveEvent;
use App\Listeners\UpdateRedisCache;
use App\Models\Event;
use App\Observers\EventObserver;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event as EventFacade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Event::observe(EventObserver::class);

        EventFacade::listen(EntryRecorded::class, [UpdateRedisCache::class, 'handleEntry']);
        EventFacade::listen(MealUsed::class, [UpdateRedisCache::class, 'handleMeal']);
        EventFacade::listen(Login::class, SetDefaultActiveEvent::class);
        EventFacade::listen(Login::class, [LogAuthenticationEvents::class, 'handleLogin']);
        EventFacade::listen(Failed::class, [LogAuthenticationEvents::class, 'handleFailed']);
        EventFacade::listen(Logout::class, [LogAuthenticationEvents::class, 'handleLogout']);
    }
}
