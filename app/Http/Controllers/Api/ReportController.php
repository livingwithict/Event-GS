<?php

namespace App\Http\Controllers\Api;

use App\Exports\AttendanceExport;
use App\Exports\NoShowExport;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Registration;
use Maatwebsite\Excel\Facades\Excel;

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
        $duplicates = \Spatie\Activitylog\Models\Activity::where('subject_type', Registration::class)
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
            'Content-Disposition' => 'attachment; filename="duplicate-scans-' . $event->slug . '.csv"',
        ]);
    }
}
