<?php

namespace App\Services;

use App\Models\Registration;
use Dompdf\Dompdf;
use Dompdf\Options;

class RegistrationQrPrintService
{
    public function generatePdf(Registration $registration): string
    {
        $registration->load(['event', 'category']);
        $qrSvg = app(QRCodeService::class)->generateSvg($registration, 1200);
        $html = view('qr.registration-print', [
            'registration' => $registration,
            'event' => $registration->event,
            'category' => $registration->category,
            'qrSvg' => $qrSvg,
        ])->render();

        $dompdf = new Dompdf((new Options)->set([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => false,
            'defaultPaperSize' => [0, 0, 432, 576],
            'defaultPaperOrientation' => 'portrait',
        ]));
        $dompdf->loadHtml($html);
        $dompdf->setPaper([0, 0, 432, 576]);
        $dompdf->render();

        return $dompdf->output();
    }
}
