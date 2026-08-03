<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEventAccess;
use App\Models\Event;
use App\Models\LabelTemplate;
use App\Models\Registration;
use App\Services\LabelService;
use Illuminate\Http\Request;

class LabelController extends Controller
{
    use AuthorizesEventAccess;

    public function printSingle(Request $request, Registration $registration)
    {
        $event = $registration->event;
        $this->authorizeEventAccess($event, ['super_admin', 'admin', 'manager']);

        if (! $event->settingEnabled('enable_label_printing')) {
            abort(403, 'Label printing is not enabled for this event.');
        }

        $template = $this->resolveTemplate($event, $request);
        $service = new LabelService;
        $pdf = $service->generateLabelPdf(collect([$registration]), $template);

        $service->markAsPrinted(collect([$registration]));

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="label-'.$registration->guest_number.'.pdf"',
        ]);
    }

    public function printBulk(Request $request)
    {
        $validated = $request->validate([
            'event_id' => 'required|exists:events,id',
            'category_id' => 'nullable|exists:participant_categories,id',
            'unprinted_only' => 'nullable|boolean',
            'registration_ids' => 'nullable|array',
            'registration_ids.*' => 'exists:registrations,id',
        ]);

        $event = Event::findOrFail($validated['event_id']);
        $this->authorizeEventAccess($event, ['super_admin', 'admin', 'manager']);

        if (! $event->settingEnabled('enable_label_printing')) {
            abort(403, 'Label printing is not enabled for this event.');
        }

        $query = Registration::where('event_id', $event->id);

        if (! empty($validated['registration_ids'])) {
            $query->whereIn('id', $validated['registration_ids']);
        }

        if (! empty($validated['category_id'])) {
            $query->where('category_id', $validated['category_id']);
        }

        if (! empty($validated['unprinted_only'])) {
            $query->where('label_printed', false);
        }

        $registrations = $query->orderBy('guest_number')->get();

        if ($registrations->isEmpty()) {
            return back()->with('error', 'No registrations found matching the filters.');
        }

        $template = $this->resolveTemplate($event, $request);
        $service = new LabelService;
        $pdf = $service->generateLabelPdf($registrations, $template);

        $service->markAsPrinted($registrations);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="labels-'.$event->slug.'.pdf"',
        ]);
    }

    private function resolveTemplate(Event $event, Request $request): LabelTemplate
    {
        $templateId = $request->input('template_id');

        if ($templateId) {
            return LabelTemplate::where('event_id', $event->id)
                ->where('id', $templateId)
                ->firstOrFail();
        }

        $categoryId = $request->input('category_id');
        if ($categoryId) {
            $categoryTemplate = $event->categories()
                ->where('id', $categoryId)
                ->whereNotNull('label_template_id')
                ->value('label_template_id');

            if ($categoryTemplate) {
                return LabelTemplate::findOrFail($categoryTemplate);
            }
        }

        return LabelTemplate::where('event_id', $event->id)->first() ?? new LabelTemplate([
            'template_name' => 'Default',
            'width' => 100,
            'height' => 60,
            'show_qr' => true,
            'show_designation' => true,
            'show_organization' => true,
            'show_category_color' => true,
            'font_size_name' => 16,
        ]);
    }
}
