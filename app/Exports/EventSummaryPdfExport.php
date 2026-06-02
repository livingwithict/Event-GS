<?php

namespace App\Exports;

use App\Models\Event;
use App\Models\Payment;
use Dompdf\Dompdf;
use Dompdf\Options;

class EventSummaryPdfExport
{
    public function generate(Event $event): string
    {
        $event->load(['categories' => fn ($q) => $q->active()->ordered()->withCount('registrations')]);
        $stats = $event->getStats();
        $totalRegistrations = $stats['total_registrations'];
        $noShows = $totalRegistrations - $stats['total_entries'];

        $paymentStats = [
            'collected' => Payment::where('event_id', $event->id)->where('payment_status', 'success')->sum('amount_paisa') / 100,
            'pending' => Payment::where('event_id', $event->id)->whereIn('payment_status', ['pending', 'initiated'])->sum('amount_paisa') / 100,
            'failed' => Payment::where('event_id', $event->id)->whereIn('payment_status', ['failed', 'cancelled'])->count(),
        ];

        $dailyBreakdown = null;
        if ($event->isMultiDay()) {
            $dailyBreakdown = [];
            foreach ($event->getEventDays() as $index => $dayDate) {
                $dayNum = $index + 1;
                $dailyBreakdown[] = [
                    'day' => $dayNum,
                    'date' => $dayDate->format('M j, Y'),
                    'stats' => $event->getStatsForDay($dayNum),
                ];
            }
        }

        $dompdf = new Dompdf((new Options)->set([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => false,
        ]));

        $html = view('reports.event-summary-pdf', [
            'event' => $event,
            'stats' => $stats,
            'noShows' => $noShows,
            'totalRegistrations' => $totalRegistrations,
            'paymentStats' => $paymentStats,
            'categories' => $event->categories,
            'dailyBreakdown' => $dailyBreakdown,
        ])->render();

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }
}
