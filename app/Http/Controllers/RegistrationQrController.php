<?php

namespace App\Http\Controllers;

use App\Models\Registration;
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
}
