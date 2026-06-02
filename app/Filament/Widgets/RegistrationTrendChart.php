<?php

namespace App\Filament\Widgets;

use App\Models\Registration;
use Filament\Widgets\ChartWidget;

class RegistrationTrendChart extends ChartWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'Registration Trend (30 days)';

    protected ?string $maxHeight = '250px';

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $days = collect(range(29, 0))->map(fn ($i) => now()->subDays($i)->format('Y-m-d'));

        $counts = Registration::selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->where('created_at', '>=', now()->subDays(30))
            ->groupByRaw('DATE(created_at)')
            ->pluck('count', 'date');

        return [
            'datasets' => [
                [
                    'label' => 'Registrations',
                    'data' => $days->map(fn ($day) => $counts->get($day, 0))->toArray(),
                    'borderColor' => '#1a56db',
                    'backgroundColor' => 'rgba(26, 86, 219, 0.1)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
            ],
            'labels' => $days->map(fn ($day) => now()->parse($day)->format('M j'))->toArray(),
        ];
    }
}
