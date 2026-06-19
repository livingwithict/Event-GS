<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class HorizonServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (! extension_loaded('redis') || config('queue.default') !== 'redis') {
            return;
        }

        $this->app->register(\Laravel\Horizon\HorizonServiceProvider::class);
    }

    public function boot(): void
    {
        if (! extension_loaded('redis') || config('queue.default') !== 'redis') {
            return;
        }

        \Horizon::auth(function ($request) {
            $user = $request->user();

            return $user && in_array($user->role, ['super_admin', 'admin']);
        });
    }
}
