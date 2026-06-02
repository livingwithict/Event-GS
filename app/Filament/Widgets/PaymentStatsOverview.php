<?php

namespace App\Filament\Widgets;

use App\Models\Payment;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PaymentStatsOverview extends BaseWidget
{
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        $successful = Payment::where('payment_status', 'success');
        $pending = Payment::whereIn('payment_status', ['pending', 'initiated']);
        $failed = Payment::whereIn('payment_status', ['failed', 'cancelled', 'expired']);

        $collectedAmount = $successful->sum('amount_paisa') / 100;
        $pendingAmount = $pending->sum('amount_paisa') / 100;

        return [
            Stat::make('Total Collected', 'NPR '.number_format($collectedAmount, 0))
                ->description($successful->count().' transactions')
                ->descriptionIcon('heroicon-o-banknotes')
                ->color('success'),
            Stat::make('Pending Amount', 'NPR '.number_format($pendingAmount, 0))
                ->description($pending->count().' pending transactions')
                ->descriptionIcon('heroicon-o-clock')
                ->color('warning'),
            Stat::make('Failed Payments', $failed->count())
                ->description('Needs attention')
                ->descriptionIcon('heroicon-o-exclamation-triangle')
                ->color('danger'),
        ];
    }
}
