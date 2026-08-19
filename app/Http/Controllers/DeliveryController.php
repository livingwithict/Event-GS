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

    /** Inline PDF of delivery labels for the named registrations, to paste on envelopes. */
    public function labels(Request $request)
    {
        $ids = array_filter(array_map('intval', explode(',', (string) $request->query('registrations'))));

        abort_if(empty($ids), 400, 'No registrations selected.');

        $registrations = Registration::with('event')->whereIn('id', $ids)->orderBy('guest_number')->get();

        abort_if($registrations->isEmpty(), 404, 'Registrations not found.');
        abort_if($registrations->pluck('event_id')->unique()->count() > 1, 422, 'Select registrations from a single event.');

        $event = $registrations->first()->event;
        $this->authorizeEventAccess($event, Ability::DeliveryManage);

        // Same sticker as the ID label: the event's configured template, or
        // the same built-in default LabelController falls back to.
        $template = LabelTemplate::where('event_id', $event->id)->first() ?? new LabelTemplate([
            'width' => 100,
            'height' => 60,
            'margin_left' => 2,
            'margin_right' => 2,
            'margin_top' => 2,
            'margin_bottom' => 2,
        ]);

        $pdf = app(LabelService::class)->generateDeliveryLabelPdf($registrations, $template);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="delivery-labels.pdf"',
        ]);
    }
}
