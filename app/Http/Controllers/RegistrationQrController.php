<?php

namespace App\Http\Controllers;

use App\Models\Registration;
use App\Services\QRCodeService;
use App\Services\RegistrationQrPrintService;

class RegistrationQrController extends Controller
{
    public function download(string $token, RegistrationQrPrintService $printService)
    {
        $registration = Registration::where('qr_hash', $token)->firstOrFail();
        $pdf = $printService->generatePdf($registration);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$registration->guest_number.'-qr-6x8.pdf"',
        ]);
    }

    /** Public PNG so it can be linked from an email <img> without needing an attachment. */
    public function image(string $token, QRCodeService $qrService)
    {
        $registration = Registration::where('qr_hash', $token)->firstOrFail();
        $png = $qrService->generatePng($registration, 400);

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'inline; filename="qr.png"',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
