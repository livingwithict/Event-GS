<?php

namespace App\Services;

use App\Models\LabelTemplate;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Collection;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class LabelService
{
    public function generateLabelPdf(Collection $registrations, LabelTemplate $template): string
    {
        $dompdf = new Dompdf((new Options)->set([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => false,
        ]));

        // Paper is the sticker itself, one label per page. mm -> pt.
        $mmToPt = 72 / 25.4;
        $dompdf->setPaper([0, 0, $template->width * $mmToPt, $template->height * $mmToPt]);

        $html = $this->generateSheetHtml($registrations, $template);

        $dompdf->loadHtml($html);
        $dompdf->render();

        return $dompdf->output();
    }

    public function generateSheetHtml(Collection $registrations, LabelTemplate $template): string
    {
        $qrService = app(QRCodeService::class);

        $labels = $registrations->map(function ($registration) use ($template, $qrService) {
            $qrCodePng = $template->show_qr ? base64_encode($qrService->generatePng($registration)) : null;

            return [
                'name' => $registration->displayName(),
                'designation' => $template->show_designation ? $registration->designation : null,
                'organization' => $template->show_organization ? $registration->organization : null,
                'guest_number' => $registration->guest_number,
                'category_color' => $template->show_category_color ? ($registration->category?->badge_color ?? '#1a56db') : null,
                'category_name' => $registration->category?->name,
                'qr_code' => $qrCodePng,
            ];
        })->toArray();

        return view('labels.print-sheet', [
            'labels' => $labels,
            'template' => $template,
            'geo' => $this->geometry($template),
        ])->render();
    }

    /**
     * All label geometry in mm, derived from the sticker size so any
     * template dimension lays out without clipping.
     */
    private function geometry(LabelTemplate $template): array
    {
        $w = (float) $template->width;
        $h = (float) $template->height;

        // Horizontal and vertical padding are independent so top/bottom can be
        // zeroed (full-height, vertically centred content) while left/right
        // stay put.
        $padX = max(0, min(
            (float) ($template->margin_left ?? 2),
            (float) ($template->margin_right ?? 2),
            $w / 4,
        ));
        $padY = max(0, min(
            (float) ($template->margin_top ?? 2),
            (float) ($template->margin_bottom ?? 2),
            $h / 4,
        ));

        $titleH = $template->show_category_color ? round($h * 0.14, 1) : 0.0;
        $codeH = 3.8;
        $gap = 2.0;

        $bodyTop = round($padY + $titleH + ($titleH > 0 ? 1.0 : 0), 1);
        $bodyH = round($h - $bodyTop - $padY, 1);

        // QR is square: limited by the column width and by the height left under the title.
        $qr = round(min($w * 0.28, $bodyH - $codeH), 1);
        // Centre the QR + code block in whatever height is left.
        $qrTop = round($bodyTop + max(0, ($bodyH - ($qr + $codeH)) / 2), 1);

        // Fonts scale with the sticker; the template value is a floor, not a cap.
        // Kept modest so long names/designations/organizations wrap without
        // clipping past the bottom of the sticker.
        $nameFont = max((int) $template->font_size_name, (int) round($h * 0.4));
        // Helvetica bold is ~0.58em per character; keep the code inside the QR column.
        $codeFont = max(7, (int) round(min($qr * 0.42, $qr * 2.835 / (11 * 0.58))));

        return [
            'padX' => $padX,
            'padY' => $padY,
            'titleH' => $titleH,
            'codeH' => $codeH,
            'qr' => $qr,
            'qrTop' => $qrTop,
            'bodyTop' => $bodyTop,
            'bodyH' => $bodyH,
            // QR hugs the right edge (no right-side pad), so info only loses the left pad.
            'infoW' => round($w - $padX - $qr - $gap, 1),
            'nameFont' => $nameFont,
            // A bit larger than the guest code under the QR, for readability.
            'orgFont' => $codeFont + 2,
            'codeFont' => $codeFont,
        ];
    }

    /**
     * Shipping label for the envelope, handed to PickAndDrop with the batch.
     * Same sticker size as the ID label (same printer). Never carries the
     * guest's entry/lunch/dinner QR — that must not leave the building on an
     * envelope. A courier order gets its own small QR (the PickAndDrop order
     * id) in the corner instead, for the courier to scan; self-delivered
     * labels (no order created) get no QR at all.
     */
    public function generateDeliveryLabelPdf(Collection $registrations, LabelTemplate $template): string
    {
        $dompdf = new Dompdf((new Options)->set([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => false,
        ]));

        $mmToPt = 72 / 25.4;
        $dompdf->setPaper([0, 0, $template->width * $mmToPt, $template->height * $mmToPt]);

        $dompdf->loadHtml($this->generateDeliverySheetHtml($registrations, $template));
        $dompdf->render();

        return $dompdf->output();
    }

    public function generateDeliverySheetHtml(Collection $registrations, LabelTemplate $template): string
    {
        $labels = $registrations->map(fn ($registration) => [
            'name' => $registration->displayName(),
            'designation' => $template->show_designation ? $registration->designation : null,
            'organization' => $template->show_organization ? $registration->organization : null,
            'phone' => $registration->phone,
            'address' => $registration->address,
            'tracking_number' => $registration->pickndrop_tracking_number,
            'order_qr' => $registration->pickndrop_order_id
                ? base64_encode(QrCode::format('png')->size(200)->margin(0)->generate($registration->pickndrop_order_id))
                : null,
        ])->toArray();

        $pad = max(0, min(
            (float) ($template->margin_left ?? 2),
            (float) ($template->margin_right ?? 2),
            (float) ($template->margin_top ?? 2),
            (float) ($template->margin_bottom ?? 2),
        ));

        return view('labels.delivery-label', [
            'labels' => $labels,
            'template' => $template,
            'pad' => $pad,
        ])->render();
    }

    public function markAsPrinted(Collection $registrations): void
    {
        $userId = auth()->id();

        foreach ($registrations as $registration) {
            $registration->update([
                'label_printed' => true,
                'label_printed_at' => now(),
                'label_printed_by' => $userId,
                'badge_status' => 'printed',
            ]);
        }
    }
}
