<?php

namespace App\Services;

use App\Models\Registration;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class QRCodeService
{
    public function getPayload(Registration $registration): string
    {
        return config('app.url').'/checkin/t/'.$registration->qr_hash;
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

    public function resolve(string $code): ?Registration
    {
        if (empty($code)) {
            return null;
        }

        if (str_starts_with($code, 'http')) {
            $token = basename(parse_url($code, PHP_URL_PATH));

            return $this->resolveFromToken($token);
        }

        if (str_starts_with($code, '/') || str_contains($code, '/checkin/t/')) {
            $token = basename($code);

            return $this->resolveFromToken($token);
        }

        if (strlen($code) === 36 && str_contains($code, '-')) {
            return Registration::where('unique_code', $code)->first();
        }

        if (str_contains($code, '-G-')) {
            return Registration::where('guest_number', $code)->first();
        }

        return $this->resolveFromToken($code);
    }

    public function resolveFromToken(string $token): ?Registration
    {
        return Registration::where('qr_hash', $token)->first();
    }
}
