<?php

namespace App\Services;

use App\Models\LabelTemplate;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Collection;

class LabelService
{
    public function generateLabelPdf(Collection $registrations, LabelTemplate $template): string
    {
        $dompdf = new Dompdf((new Options)->set([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => false,
            'defaultPaperSize' => 'a4',
            'defaultPaperOrientation' => 'portrait',
        ]));

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
                'name' => $registration->name,
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
            ]);
        }
    }
}
