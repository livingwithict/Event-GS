<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class ArrivalDistributionWidget extends ChartWidget
{
    protected static ?int $sort = 7;

    protected static bool $isLazy = false;

    public ?string $maxHeight = '320px';

    public function getHeading(): string
    {
        return 'Arrival Time Heatmap';
    }

    public function getDescription(): ?string
    {
        return 'Check-in distribution by hour (darker = more arrivals)';
    }

    public function getType(): string
    {
        return 'bar';
    }

    public function getData(): array
    {
        $eventId = session('active_event_id');

        $checkins = DB::table('scan_logs')
            ->join('scan_action_types', 'scan_logs.action_type_id', '=', 'scan_action_types.id')
            ->where('scan_action_types.action_code', 'CHECKIN')
            ->when($eventId, fn ($q) => $q->where('scan_logs.event_id', $eventId))
            ->selectRaw('EXTRACT(HOUR FROM scan_logs.scanned_at)::int as hour, COUNT(*) as count')
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();

        $maxCount = $checkins->max('count') ?: 1;

        $labels = [];
        $data = [];
        $colors = [];

        for ($h = 6; $h <= 22; $h++) {
            $labels[] = sprintf('%02d:00', $h);
            $hourData = $checkins->firstWhere('hour', $h);
            $count = $hourData?->count ?? 0;
            $data[] = $count;

            if ($count === 0) {
                $colors[] = '#E5E7EB';
            } else {
                $intensity = min(1, $count / max($maxCount, 1));
                $r = (int) (79 * (1 - $intensity) + 99 * $intensity);
                $g = (int) (70 * (1 - $intensity) + 102 * $intensity);
                $b = (int) (229 * (1 - $intensity) + 255 * $intensity);
                $colors[] = sprintf('#%02X%02X%02X', $r, $g, $b);
            }
        }

        return [
            'datasets' => [
                [
                    'label' => 'Check-ins',
                    'data' => $data,
                    'backgroundColor' => $colors,
                    'borderColor' => '#4F46E5',
                    'borderWidth' => 1,
                    'borderRadius' => 4,
                ],
            ],
            'labels' => $labels,
        ];
    }
}
