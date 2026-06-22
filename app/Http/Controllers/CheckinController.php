<?php

namespace App\Http\Controllers;

use App\Services\QRCodeService;

class CheckinController extends Controller
{
    public function show(string $token)
    {
        $qrService = new QRCodeService;
        $reg = $qrService->resolve($token);

        if (! $reg) {
            return response()->view('checkin.invalid', [], 404);
        }

        $reg->load(['event', 'category']);

        $qrSvg = $qrService->generateSvg($reg);

        return view('checkin.verify', [
            'registration' => $reg,
            'event' => $reg->event,
            'category' => $reg->category,
            'qrSvg' => $qrSvg,
        ]);
    }
}
