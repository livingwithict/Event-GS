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
            // QR keeps the same right-side pad as everything else, so all 4 sticker edges match.
            'infoW' => round($w - 2 * $padX - $qr - $gap, 1),
            'nameFont' => $nameFont,
            // A bit larger than the guest code under the QR, for readability.
            'orgFont' => $codeFont + 2,
            'codeFont' => $codeFont,
        ];
    }

    /**
     * Shipping label for the envelope, handed to PickAndDrop or the internal team with the batch.
     * Same sticker size as the ID label (same printer). Never carries the
     * guest's entry/lunch/dinner QR — that must not leave the building on an
     * envelope. Team labels QR our own delivery_id (scannable at the Tracking
     * station); P&D labels QR the courier's order id instead, for the courier
     * to scan, falling back to delivery_id if no order was created yet.
     */
    public function generateDeliveryLabelPdf(Collection $registrations, LabelTemplate $template, string $type = 'team'): string
    {
        $dompdf = new Dompdf((new Options)->set([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => false,
        ]));

        $mmToPt = 72 / 25.4;
        $dompdf->setPaper([0, 0, $template->width * $mmToPt, $template->height * $mmToPt]);

        $dompdf->loadHtml($this->generateDeliverySheetHtml($registrations, $template, $type));
        $dompdf->render();

        return $dompdf->output();
    }

    public function generateDeliverySheetHtml(Collection $registrations, LabelTemplate $template, string $type = 'team'): string
    {
        $labels = $registrations->map(function ($registration) use ($template, $type) {
            $qrPayload = $type === 'pnd'
                ? ($registration->pickndrop_order_id ?: $registration->delivery_id)
                : $registration->delivery_id;

            return [
                'name' => $registration->displayName(),
                'designation' => $template->show_designation ? $registration->designation : null,
                'organization' => $template->show_organization ? $registration->organization : null,
                'phone' => $registration->phone,
                'address' => $registration->address,
                'order_qr' => $qrPayload
                    ? base64_encode(QrCode::format('png')->size(200)->margin(0)->generate($qrPayload))
                    : null,
            ];
        })->toArray();

        $w = (float) $template->width;
        $h = (float) $template->height;

        // Same per-axis clamp as the ID label: horizontal and vertical pads are
        // independent, each capped at a quarter of the sticker so they can't eat
        // the whole label, but otherwise using the template's real margins.
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

        return view('labels.delivery-label', [
            'labels' => $labels,
            'template' => $template,
            'padX' => $padX,
            'padY' => $padY,
            'qrLabel' => $type === 'pnd' ? 'PicknDrop' : 'DNC 2026',
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
