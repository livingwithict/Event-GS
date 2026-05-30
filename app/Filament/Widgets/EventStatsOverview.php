<?php

namespace App\Filament\Widgets;

use App\Models\Event;
use App\Models\Registration;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class EventStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Total Events', Event::count()),
            Stat::make('Total Registrations', Registration::count()),
            Stat::make('Entries', Registration::whereNotNull('entry_time')->count()),
        ];
    }
}
