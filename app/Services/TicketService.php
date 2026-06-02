<?php

namespace App\Services;

use App\Models\Registration;
use Dompdf\Dompdf;
use Dompdf\Options;

class TicketService
{
    public function generatePdf(Registration $registration): string
    {
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

    public function generateHtml(Registration $registration): string
    {
        $data = $this->getTicketViewData($registration);

        return view('tickets.event-ticket', $data)->render();
    }

    public function getTicketViewData(Registration $registration): array
    {
        $registration->load(['event', 'category']);
        $event = $registration->event;

        $qrService = app(QRCodeService::class);
        $qrCodePng = base64_encode($qrService->generatePng($registration));

        return [
            'event' => $event,
            'registration' => $registration,
            'category' => $registration->category,
            'qrCodePng' => $qrCodePng,
            'ticketUrl' => config('app.url').'/ticket/'.$registration->qr_hash,
        ];
    }
}
