<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('db:backup --retain=90')->dailyAt('02:00');

Schedule::command('event:send-reminders')->dailyAt('09:00');
Schedule::command('event:send-thankyou')->dailyAt('10:00');
Schedule::command('payment:expire')->everyFiveMinutes();
