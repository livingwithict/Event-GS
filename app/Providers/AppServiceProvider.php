<?php

namespace App\Providers;

use App\Enums\Ability;
use App\Events\EntryRecorded;
use App\Events\MealUsed;
use App\Listeners\LogAuthenticationEvents;
use App\Listeners\SetDefaultActiveEvent;
use App\Listeners\UpdateRedisCache;
use App\Models\Event;
use App\Models\User;
use App\Observers\EventObserver;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event as EventFacade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Behind the reverse proxy the app only ever sees a plain HTTP hop, so
        // generated URLs (assets, favicon, redirects) come out http:// and the
        // browser blocks them as mixed content. Follow APP_URL instead.
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        $this->registerAbilities();

        Event::observe(EventObserver::class);

        EventFacade::listen(EntryRecorded::class, [UpdateRedisCache::class, 'handleEntry']);
        EventFacade::listen(MealUsed::class, [UpdateRedisCache::class, 'handleMeal']);
        EventFacade::listen(Login::class, SetDefaultActiveEvent::class);
        EventFacade::listen(Login::class, [LogAuthenticationEvents::class, 'handleLogin']);
        EventFacade::listen(Failed::class, [LogAuthenticationEvents::class, 'handleFailed']);
        EventFacade::listen(Logout::class, [LogAuthenticationEvents::class, 'handleLogout']);
    }

    /**
     * Turn the role/ability matrix into gates, so authorisation everywhere reads
     * `$user->can('guests.edit')` rather than a hardcoded list of role names.
     */
    private function registerAbilities(): void
    {
        foreach (Ability::all() as $ability) {
            Gate::define($ability, fn (User $user) => $user->roleEnum()?->can($ability) ?? false);
        }
    }
}
