<?php

namespace App\Http\Controllers;

use App\Enums\Ability;
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
        $this->authorizeEventAccess($event, Ability::LabelsPrint);

        if (! $event->settingEnabled('enable_label_printing')) {
            abort(403, 'Label printing is not enabled for this event.');
        }

        $template = $this->resolveTemplate($event, $request);
        $service = new LabelService;
        $pdf = $service->generateLabelPdf(collect([$registration]), $template);

        // Preview shows the label inline without burning the "printed" flag.
        $preview = $request->boolean('preview');

        if (! $preview) {
            $service->markAsPrinted(collect([$registration]));
        }

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($preview ? 'inline' : 'attachment').'; filename="label-'.$registration->guest_number.'.pdf"',
        ]);
    }

    /** Wrapper page: loads the label sheet as HTML in an iframe and fires the print dialog. */
    public function printNow(Request $request)
    {
        $registrations = $this->resolveRegistrations($request);

        return view('labels.print-now', [
            'sheetUrl' => route('labels.sheet', ['registrations' => $registrations->pluck('id')->implode(',')]),
            'count' => $registrations->count(),
        ]);
    }

    /**
     * Raw HTML (not PDF) for the auto-print wrapper. Marks the labels as printed.
     * Chrome's PDF viewer mis-scales small custom page sizes when printed from
     * an embedded PDF; printing the same @page-sized HTML directly goes through
     * Chrome's native print pipeline instead, which honors the size correctly.
     */
    public function sheet(Request $request)
    {
        $registrations = $this->resolveRegistrations($request);

        $service = new LabelService;
        $html = $service->generateSheetHtml($registrations, $this->resolveTemplate($registrations->first()->event, $request));

        $service->markAsPrinted($registrations);

        return response($html, 200, ['Content-Type' => 'text/html']);
    }

    /**
     * Registrations named by ?registrations=1,2,3 — all must belong to one event
     * so a single label template and one access check apply to the whole batch.
     */
    private function resolveRegistrations(Request $request): \Illuminate\Database\Eloquent\Collection
    {
        $ids = array_filter(array_map('intval', explode(',', (string) $request->query('registrations'))));

        abort_if(empty($ids), 400, 'No registrations selected.');

        $registrations = Registration::with('event', 'category')->whereIn('id', $ids)->get()
            ->sortBy(fn ($r) => array_search($r->id, $ids))
            ->values();

        abort_if($registrations->isEmpty(), 404, 'Registrations not found.');
        abort_if($registrations->pluck('event_id')->unique()->count() > 1, 422, 'Select registrations from a single event.');

        $event = $registrations->first()->event;
        $this->authorizeEventAccess($event, Ability::LabelsPrint);

        if (! $event->settingEnabled('enable_label_printing')) {
            abort(403, 'Label printing is not enabled for this event.');
        }

        return $registrations;
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
        $this->authorizeEventAccess($event, Ability::LabelsPrint);

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
