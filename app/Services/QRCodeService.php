<?php

namespace App\Services;

use App\Models\Registration;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class QRCodeService
{
    public function getPayload(Registration $registration): string
    {
        return $registration->guest_number;
    }

    public function generateSvg(Registration $registration, int $size = 300): string
    {
        return QrCode::size($size)
            ->margin(2)
            ->generate($this->getPayload($registration));
    }

    public function generatePng(Registration $registration, int $size = 300): string
    {
        return QrCode::format('png')
            ->size($size)
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

            return $this->resolve($token);
        }

        if (str_starts_with($code, '/') || str_contains($code, '/checkin/t/')) {
            $token = basename($code);

            return $this->resolve($token);
        }

        if (strlen($code) === 36 && str_contains($code, '-')) {
            return Registration::where('unique_code', $code)->first();
        }

        // Codes are uppercase, but a scanner may read an old lowercase payload
        // or a human may type one in, so match case-insensitively.
        return Registration::whereRaw('UPPER(guest_number) = ?', [strtoupper($code)])->first()
            ?? $this->resolveFromToken($code);
    }

    public function resolveFromToken(string $token): ?Registration
    {
        return Registration::where('qr_hash', $token)->first();
    }
}
