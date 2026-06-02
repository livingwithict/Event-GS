<?php

namespace App\Filament\Widgets;

use App\Models\Event;
use App\Models\Payment;
use App\Models\Registration;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class EventStatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $totalRegistrations = Registration::count();
        $totalEntries = Registration::whereNotNull('entry_time')->count();
        $attendanceRate = $totalRegistrations > 0 ? round(($totalEntries / $totalRegistrations) * 100, 1) : 0;

        return [
            Stat::make('Total Events', Event::count())
                ->description(Event::where('status', 'published')->count().' published')
                ->descriptionIcon('heroicon-o-calendar')
                ->color('primary'),
            Stat::make('Total Registrations', $totalRegistrations)
                ->description(Registration::where('registration_source', 'self')->count().' self-registered')
                ->descriptionIcon('heroicon-o-users')
                ->color('info'),
            Stat::make('Revenue Collected', 'NPR '.number_format(Payment::where('payment_status', 'success')->sum('amount_paisa') / 100, 0))
                ->description(Payment::where('payment_status', 'success')->count().' successful payments')
                ->descriptionIcon('heroicon-o-banknotes')
                ->color('success'),
            Stat::make('Attendance Rate', $attendanceRate.'%')
                ->description($totalEntries.' of '.$totalRegistrations.' checked in')
                ->descriptionIcon('heroicon-o-check-circle')
                ->color($attendanceRate >= 70 ? 'success' : ($attendanceRate >= 40 ? 'warning' : 'danger')),
            Stat::make('Pending Payments', Payment::where('payment_status', 'pending')->count())
                ->description(Payment::where('payment_status', 'failed')->count().' failed')
                ->descriptionIcon('heroicon-o-clock')
                ->color('warning'),
            Stat::make('Labels Printed', Registration::where('label_printed', true)->count())
                ->description(Registration::where('label_printed', false)->count().' remaining')
                ->descriptionIcon('heroicon-o-tag')
                ->color('gray'),
        ];
    }
}
