<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class ArrivalDistributionWidget extends ChartWidget
{
    protected static ?int $sort = 7;

    protected static bool $isLazy = false;

    public ?string $maxHeight = '300px';

    public function getHeading(): string
    {
        return 'Arrival Time Distribution';
    }

    public function getType(): string
    {
        return 'bar';
    }

    public function getData(): array
    {
        $checkins = DB::table('scan_logs')
            ->join('scan_action_types', 'scan_logs.action_type_id', '=', 'scan_action_types.id')
            ->where('scan_action_types.action_code', 'CHECKIN')
            ->selectRaw('EXTRACT(HOUR FROM scan_logs.scanned_at)::int as hour, COUNT(*) as count')
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();

        $labels = [];
        $data = [];
        for ($h = 6; $h <= 22; $h++) {
            $labels[] = sprintf('%02d:00', $h);
            $hourData = $checkins->firstWhere('hour', $h);
            $data[] = $hourData?->count ?? 0;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Check-ins',
                    'data' => $data,
                    'backgroundColor' => '#4F46E5',
                    'borderRadius' => 4,
                ],
            ],
            'labels' => $labels,
        ];
    }
}
