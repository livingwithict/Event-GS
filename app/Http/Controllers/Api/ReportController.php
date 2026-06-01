<?php

namespace App\Http\Controllers\Api;

use App\Exports\AttendanceExport;
use App\Exports\CommunicationExport;
use App\Exports\MealUsageExport;
use App\Exports\NoShowExport;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Registration;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Activitylog\Models\Activity;

class ReportController extends Controller
{
    public function attendance(Event $event)
    {
        return Excel::download(new AttendanceExport($event), "attendance-{$event->slug}.csv", \Maatwebsite\Excel\Excel::CSV);
    }

    public function noShow(Event $event)
    {
        return Excel::download(new NoShowExport($event), "noshow-{$event->slug}.csv", \Maatwebsite\Excel\Excel::CSV);
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
        return Excel::download(
            new CommunicationExport(eventId: $event->id),
            "communications-{$event->slug}.csv",
            \Maatwebsite\Excel\Excel::CSV,
        );
    }

    public function mealUsage(Event $event)
    {
        return Excel::download(
            new MealUsageExport($event),
            "meal-usage-{$event->slug}.csv",
            \Maatwebsite\Excel\Excel::CSV,
        );
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
        $csv .= "Date,{$event->event_date->format('Y-m-d')}\n";
        $csv .= "Venue,{$event->venue}\n\n";
        $csv .= "Statistics\n";
        $csv .= "Metric,Count,Percentage\n";
        $csv .= "Total Registrations,{$total},\n";
        $csv .= "Total Entries,{$stats['total_entries']},".($total > 0 ? round(($stats['total_entries'] / $total) * 100, 1) : 0)."%\n";
        $csv .= "No-Shows,{$noShows},".($total > 0 ? round(($noShows / $total) * 100, 1) : 0)."%\n";
        $csv .= "Lunch Used,{$stats['lunch_used']},".($total > 0 ? round(($stats['lunch_used'] / $total) * 100, 1) : 0)."%\n";
        $csv .= "Dinner Used,{$stats['dinner_used']},".($total > 0 ? round(($stats['dinner_used'] / $total) * 100, 1) : 0)."%\n";
        $csv .= "Duplicate Scan Attempts,{$duplicates},\n";

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="summary-'.$event->slug.'.csv"',
        ]);
    }
}
