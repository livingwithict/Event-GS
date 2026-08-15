<?php

namespace App\Services;

use App\Models\Registration;
use Dompdf\Dompdf;
use Dompdf\Options;

class TicketService
{
    public function generatePdf(Registration $registration): string
    {
        $registration->loadMissing('event');

        if ($registration->event?->ticketDataUri()) {
            return $this->generateCardPdf($registration);
        }

        $dompdf = new Dompdf((new Options)->set([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => false,
            'defaultPaperSize' => 'a6',
            'defaultPaperOrientation' => 'landscape',
        ]));

        $dompdf->loadHtml($this->generateHtml($registration));
        $dompdf->render();

        return $dompdf->output();
    }

    /** The event's own artwork with this guest's name and QR printed onto it. */
    public function generateCardPdf(Registration $registration): string
    {
        $layout = $registration->event->ticketLayout();

        $dompdf = new Dompdf((new Options)->set([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => false,
            'dpi' => 96,
        ]));

        // CSS px -> pt is 0.75, so the page matches the artwork exactly.
        $dompdf->setPaper([0, 0, $layout['width'] * 0.75, $layout['height'] * 0.75]);
        $dompdf->loadHtml($this->generateCardHtml($registration, $layout));
        $dompdf->render();

        return $dompdf->output();
    }

    public function generateCardHtml(Registration $registration, ?array $layout = null): string
    {
        $event = $registration->event;
        $layout ??= $event->ticketLayout();
        $name = mb_strtoupper($registration->displayName());
        $fontSize = $this->fitFontSize($name, (int) $layout['name_w'], (int) $layout['name_h']);

        return view('tickets.invitation-card', [
            'registration' => $registration,
            'layout' => $layout,
            'cardDataUri' => $event->ticketDataUri(),
            'name' => $name,
            'nameFontSize' => $fontSize,
            'nameTop' => $this->baselineTop($layout, $fontSize),
            // Render at 2x the printed size so the QR stays crisp on paper.
            'qrPng' => base64_encode(app(QRCodeService::class)->generatePng($registration, (int) $layout['qr_size'] * 2)),
        ])->render();
    }

    /**
     * Top of the text block that puts the baseline on the bottom edge of the
     * placeholder. With line-height equal to font-size, dompdf puts the baseline
     * 0.813em below the block's top — measured from its own output, not derived.
     */
    private function baselineTop(array $layout, int $fontSize): float
    {
        return round(($layout['name_y'] + $layout['name_h']) - 0.813 * $fontSize, 1);
    }

    /**
     * Largest size that keeps the name on one line inside its box.
     * Helvetica Bold capitals average ~0.66em per character.
     */
    private function fitFontSize(string $name, int $boxWidth, int $boxHeight): int
    {
        $max = (int) floor($boxHeight * 0.85);
        $chars = max(1, mb_strlen($name));

        return max(10, min($max, (int) floor($boxWidth / ($chars * 0.66))));
    }

    public function generateHtml(Registration $registration): string
    {
        $registration->loadMissing('event');

        if ($registration->event?->ticketDataUri()) {
            return $this->generateCardHtml($registration);
        }

        return view('tickets.event-ticket', $this->getTicketViewData($registration))->render();
    }

    public function getTicketViewData(Registration $registration): array
    {
        $registration->load(['event', 'category']);
        $event = $registration->event;

        $qrService = app(QRCodeService::class);
        $qrSvg = $qrService->generateSvg($registration, 500);

        return [
            'event' => $event,
            'registration' => $registration,
            'category' => $registration->category,
            'qrSvg' => $qrSvg,
            'ticketUrl' => config('app.url').'/ticket/'.$registration->qr_hash,
        ];
    }
}
