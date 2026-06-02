<?php

namespace App\Http\Controllers\Api;

use App\Exports\AttendanceExport;
use App\Exports\CardDeliveryExport;
use App\Exports\CategorySummaryExport;
use App\Exports\CommunicationExport;
use App\Exports\EventSummaryPdfExport;
use App\Exports\MealUsageExport;
use App\Exports\NoShowExport;
use App\Exports\PaymentExport;
use App\Exports\ScannerActivityExport;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Registration;
use App\Models\ScanActionType;
use App\Models\ScanLog;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Activitylog\Models\Activity;

class ReportController extends Controller
{
    private function getFormat(): string
    {
        return request('format', 'csv') === 'xlsx'
            ? \Maatwebsite\Excel\Excel::XLSX
            : \Maatwebsite\Excel\Excel::CSV;
    }

    private function getExtension(): string
    {
        return request('format', 'csv') === 'xlsx' ? 'xlsx' : 'csv';
    }

    public function attendance(Event $event)
    {
        $day = request('day');
        $ext = $this->getExtension();

        return Excel::download(new AttendanceExport($event, $day ? (int) $day : null), "attendance-{$event->slug}.{$ext}", $this->getFormat());
    }

    public function noShow(Event $event)
    {
        $ext = $this->getExtension();

        return Excel::download(new NoShowExport($event), "noshow-{$event->slug}.{$ext}", $this->getFormat());
    }

    public function duplicateScans(Event $event)
    {
        $duplicates = Activity::where('subject_type', Registration::class)
            ->where('description', 'like', 'Duplicate%')
            ->whereHasMorph('subject', Registration::class, function ($query) use ($event) {
                $query->where('event_id', $event->id);
            })
            ->get();

        $csv = "Time,Guest Name,Action,Meal Type\n";
        foreach ($duplicates as $log) {
            $csv .= sprintf(
                "%s,%s,%s,%s\n",
                $log->created_at->toDateTimeString(),
                $log->subject->name ?? 'Unknown',
                $log->properties['action'] ?? '',
                $log->properties['meal_type'] ?? ''
            );
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="duplicate-scans-'.$event->slug.'.csv"',
        ]);
    }

    public function communications(Event $event)
    {
        $ext = $this->getExtension();

        return Excel::download(
            new CommunicationExport(eventId: $event->id),
            "communications-{$event->slug}.{$ext}",
            $this->getFormat(),
        );
    }

    public function mealUsage(Event $event)
    {
        $day = request('day');
        $ext = $this->getExtension();

        $export = new MealUsageExport($event, $day ? (int) $day : null);

        return Excel::download($export, "meal-usage-{$event->slug}.{$ext}", $this->getFormat());
    }

    public function eventSummary(Event $event)
    {
        $stats = $event->getStats();
        $total = $stats['total_registrations'];
        $noShows = $total - $stats['total_entries'];

        $duplicates = Activity::where('subject_type', Registration::class)
            ->where('description', 'like', 'Duplicate%')
            ->whereHasMorph('subject', Registration::class, function ($query) use ($event) {
                $query->where('event_id', $event->id);
            })
            ->count();

        $csv = "Event Summary\n\n";
        $csv .= "Event Name,{$event->name}\n";
        $csv .= 'Date,'.($event->start_datetime?->format('Y-m-d') ?? $event->event_date?->format('Y-m-d') ?? '')."\n";
        $csv .= "Venue,{$event->venue}\n\n";
        $csv .= "Statistics\n";
        $csv .= "Metric,Count,Percentage\n";
        $csv .= "Total Registrations,{$total},\n";
        $csv .= "Total Entries,{$stats['total_entries']},".($total > 0 ? round(($stats['total_entries'] / $total) * 100, 1) : 0)."%\n";
        $csv .= "No-Shows,{$noShows},".($total > 0 ? round(($noShows / $total) * 100, 1) : 0)."%\n";
        $csv .= "Lunch Used,{$stats['lunch_used']},".($total > 0 ? round(($stats['lunch_used'] / $total) * 100, 1) : 0)."%\n";
        $csv .= "Dinner Used,{$stats['dinner_used']},".($total > 0 ? round(($stats['dinner_used'] / $total) * 100, 1) : 0)."%\n";
        $csv .= "Duplicate Scan Attempts,{$duplicates},\n";

        if ($event->isMultiDay()) {
            $csv .= "\nDaily Breakdown\n";
            $csv .= 'Day,Date';

            $day1Actions = ScanActionType::where('event_id', $event->id)
                ->where('action_code', 'LIKE', 'DAY1_%')
                ->active()
                ->orderBy('action_code')
                ->get();

            $suffixes = $day1Actions->map(fn ($a) => str_replace('DAY1_', '', $a->action_code));

            foreach ($suffixes as $suffix) {
                $csv .= ",{$suffix}";
            }
            $csv .= "\n";

            foreach ($event->getEventDays() as $index => $dayDate) {
                $dayNum = $index + 1;
                $csv .= "Day {$dayNum},".$dayDate->format('Y-m-d');

                foreach ($suffixes as $suffix) {
                    $code = "DAY{$dayNum}_{$suffix}";
                    $actionId = ScanActionType::where('event_id', $event->id)
                        ->where('action_code', $code)
                        ->value('id');

                    $count = 0;
                    if ($actionId) {
                        $count = ScanLog::where('event_id', $event->id)
                            ->where('action_type_id', $actionId)
                            ->whereBetween('scanned_at', [
                                $dayDate->copy()->startOfDay(),
                                $dayDate->copy()->endOfDay(),
                            ])
                            ->distinct('participant_id')
                            ->count('participant_id');
                    }

                    $csv .= ",{$count}";
                }
                $csv .= "\n";
            }
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="summary-'.$event->slug.'.csv"',
        ]);
    }

    public function eventSummaryPdf(Event $event)
    {
        $export = new EventSummaryPdfExport;
        $pdf = $export->generate($event);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="summary-'.$event->slug.'.pdf"',
        ]);
    }

    public function payments(Event $event)
    {
        $ext = $this->getExtension();

        return Excel::download(
            new PaymentExport(eventId: $event->id),
            "payments-{$event->slug}.{$ext}",
            $this->getFormat(),
        );
    }

    public function scannerActivity(Event $event)
    {
        $day = request('day');
        $ext = $this->getExtension();

        return Excel::download(
            new ScannerActivityExport($event->id, $day ? (int) $day : null, $event->id),
            "scanner-activity-{$event->slug}.{$ext}",
            $this->getFormat(),
        );
    }

    public function categorySummary(Event $event)
    {
        $ext = $this->getExtension();

        return Excel::download(
            new CategorySummaryExport($event->id),
            "category-summary-{$event->slug}.{$ext}",
            $this->getFormat(),
        );
    }

    public function cardDelivery(Event $event)
    {
        $ext = $this->getExtension();

        return Excel::download(
            new CardDeliveryExport($event),
            "card-delivery-{$event->slug}.{$ext}",
            $this->getFormat(),
        );
    }
}
