<?php

namespace App\Http\Controllers;

use App\Enums\Ability;
use App\Http\Controllers\Concerns\AuthorizesEventAccess;
use App\Models\LabelTemplate;
use App\Models\Registration;
use App\Services\LabelService;
use Illuminate\Http\Request;

class DeliveryController extends Controller
{
    use AuthorizesEventAccess;

    /** Wrapper page: loads the delivery label sheet as HTML in an iframe and fires the print dialog, same as ID labels. */
    public function labels(Request $request)
    {
        $registrations = $this->resolveRegistrations($request);

        return view('labels.print-now', [
            'sheetUrl' => route('delivery.labels.sheet', ['registrations' => $registrations->pluck('id')->implode(',')]),
            'count' => $registrations->count(),
        ]);
    }

    /** Raw HTML for the auto-print wrapper, same approach as ID labels. */
    public function sheet(Request $request)
    {
        $registrations = $this->resolveRegistrations($request);

        $event = $registrations->first()->event;
        $template = LabelTemplate::where('event_id', $event->id)->first() ?? new LabelTemplate([
            'width' => 100,
            'height' => 60,
            'margin_left' => 2,
            'margin_right' => 2,
            'margin_top' => 2,
            'margin_bottom' => 2,
        ]);

        $html = app(LabelService::class)->generateDeliverySheetHtml($registrations, $template);

        return response($html, 200, ['Content-Type' => 'text/html']);
    }

    private function resolveRegistrations(Request $request): \Illuminate\Database\Eloquent\Collection
    {
        $ids = array_filter(array_map('intval', explode(',', (string) $request->query('registrations'))));

        abort_if(empty($ids), 400, 'No registrations selected.');

        $registrations = Registration::with('event')->whereIn('id', $ids)->orderBy('guest_number')->get();

        abort_if($registrations->isEmpty(), 404, 'Registrations not found.');
        abort_if($registrations->pluck('event_id')->unique()->count() > 1, 422, 'Select registrations from a single event.');

        $event = $registrations->first()->event;
        $this->authorizeEventAccess($event, Ability::DeliveryManage);

        return $registrations;
    }
}
