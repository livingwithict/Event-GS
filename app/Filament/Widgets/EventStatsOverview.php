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
        $eventId = session('active_event_id');
        $regQuery = Registration::when($eventId, fn ($q) => $q->where('event_id', $eventId));
        $payQuery = Payment::when($eventId, fn ($q) => $q->where('event_id', $eventId));
        $eventQuery = Event::when($eventId, fn ($q) => $q->where('id', $eventId));

        $totalRegistrations = (clone $regQuery)->count();
        $totalEntries = (clone $regQuery)->whereNotNull('entry_time')->count();
        $attendanceRate = $totalRegistrations > 0 ? round(($totalEntries / $totalRegistrations) * 100, 1) : 0;

        return [
            Stat::make('Total Events', (clone $eventQuery)->count())
                ->description($eventId ? Event::find($eventId)?->status ?? 'N/A' : Event::where('status', 'published')->count().' published')
                ->descriptionIcon('heroicon-o-calendar')
                ->color('primary'),
            Stat::make('Total Registrations', $totalRegistrations)
                ->description((clone $regQuery)->where('registration_source', 'self')->count().' self-registered')
                ->descriptionIcon('heroicon-o-users')
                ->color('info'),
            Stat::make('Revenue Collected', 'NPR '.number_format((clone $payQuery)->where('payment_status', 'success')->sum('amount_paisa') / 100, 0))
                ->description((clone $payQuery)->where('payment_status', 'success')->count().' successful payments')
                ->descriptionIcon('heroicon-o-banknotes')
                ->color('success'),
            Stat::make('Attendance Rate', $attendanceRate.'%')
                ->description($totalEntries.' of '.$totalRegistrations.' checked in')
                ->descriptionIcon('heroicon-o-check-circle')
                ->color($attendanceRate >= 70 ? 'success' : ($attendanceRate >= 40 ? 'warning' : 'danger')),
            Stat::make('Pending Payments', (clone $payQuery)->where('payment_status', 'pending')->count())
                ->description((clone $payQuery)->where('payment_status', 'failed')->count().' failed')
                ->descriptionIcon('heroicon-o-clock')
                ->color('warning'),
            Stat::make('Labels Printed', (clone $regQuery)->where('label_printed', true)->count())
                ->description((clone $regQuery)->where('label_printed', false)->count().' remaining')
                ->descriptionIcon('heroicon-o-tag')
                ->color('gray'),
        ];
    }
}
