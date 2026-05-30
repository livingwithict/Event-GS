<?php

namespace App\Services;

use App\Models\Registration;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class QRCodeService
{
    public function getPayload(Registration $registration): string
    {
        return $registration->unique_code;
    }

    public function generateSvg(Registration $registration): string
    {
        return QrCode::size(300)
            ->margin(2)
            ->generate($this->getPayload($registration));
    }

    public function generatePng(Registration $registration): string
    {
        return QrCode::format('png')
            ->size(300)
            ->margin(2)
            ->generate($this->getPayload($registration));
    }

    public function resolve(string $uniqueCode): ?Registration
    {
        return Registration::where('unique_code', $uniqueCode)->first();
    }
}
