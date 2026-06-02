<?php

namespace App\Http\Controllers;

use App\Models\Registration;
use App\Services\QRCodeService;

class CheckinController extends Controller
{
    public function show(string $token)
    {
        $reg = Registration::where('qr_hash', $token)->first();

        if (! $reg) {
            return response()->view('checkin.invalid', [], 404);
        }

        $reg->load(['event', 'category']);

        $qrService = new QRCodeService;
        $qrSvg = $qrService->generateSvg($reg);

        return view('checkin.verify', [
            'registration' => $reg,
            'event' => $reg->event,
            'category' => $reg->category,
            'qrSvg' => $qrSvg,
        ]);
    }
}
