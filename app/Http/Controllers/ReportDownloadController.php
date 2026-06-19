<?php

namespace App\Http\Controllers;

use App\Exports\CardDeliveryExport;
use App\Exports\CategorySummaryExport;
use App\Exports\EventSummaryPdfExport;
use App\Exports\PaymentExport;
use App\Exports\ScannerActivityExport;
use App\Models\Event;
use Maatwebsite\Excel\Facades\Excel;

class ReportDownloadController extends Controller
{
    public function pdfSummary(Event $event)
    {
        $pdf = (new EventSummaryPdfExport)->generate($event);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="event-summary-'.$event->slug.'.pdf"',
        ]);
    }

    public function payments(Event $event)
    {
        return Excel::download(
            new PaymentExport($event->id),
            'payments-'.$event->slug.'.csv'
        );
    }

    public function scannerActivity(Event $event)
    {
        return Excel::download(
            new ScannerActivityExport($event->id),
            'scanner-activity-'.$event->slug.'.csv'
        );
    }

    public function categorySummary(Event $event)
    {
        return Excel::download(
            new CategorySummaryExport($event->id),
            'category-summary-'.$event->slug.'.csv'
        );
    }

    public function cardDelivery(Event $event)
    {
        return Excel::download(
            new CardDeliveryExport($event),
            'card-delivery-'.$event->slug.'.csv'
        );
    }
}
