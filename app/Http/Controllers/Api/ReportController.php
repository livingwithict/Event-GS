<?php

namespace App\Http\Controllers\Api;

use App\Exports\AttendanceExport;
use App\Exports\NoShowExport;
use App\Http\Controllers\Controller;
use App\Models\Event;
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
}
