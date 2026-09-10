<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Registration;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Appends new registrants to an event's linked Google Sheet. Never touches
 * existing rows, so whatever a person does in the sheet (color, remarks,
 * reordering columns after the header) survives every sync.
 */
class GoogleSheetsService
{
    private const HEADER = ['Title', 'Name', 'Guest #', 'Category', 'Email', 'Phone', 'Address', 'Designation', 'Organization', 'Sector'];

    /** @return int Number of registrations appended. */
    public function syncEvent(Event $event): int
    {
        if (blank($event->google_sheet_id)) {
            throw new \RuntimeException('This event has no Google Sheet configured. Add one on the Event edit page.');
        }

        $registrations = $event->registrations()
            ->whereNull('sheet_synced_at')
            ->with(['category', 'sectors'])
            ->orderBy('id')
            ->get();

        if ($registrations->isEmpty()) {
            return 0;
        }

        try {
            $this->ensureHeaderRow($event->google_sheet_id);
            $this->appendRows($event->google_sheet_id, $registrations);
        } catch (RequestException $e) {
            logger()->error('Google Sheets sync failed: '.$e->getMessage());

            if ($e->response->status() === 403) {
                throw new \RuntimeException(
                    'Google denied access to the sheet. Share it with '
                    .config('services.google_sheets.client_email').' as an Editor.'
                );
            }

            throw new \RuntimeException('Google Sheets sync failed: '.$e->getMessage());
        }

        Registration::whereIn('id', $registrations->pluck('id'))->update(['sheet_synced_at' => now()]);

        return $registrations->count();
    }

    private function ensureHeaderRow(string $sheetId): void
    {
        $response = $this->client()->get($this->valuesUrl($sheetId, 'Sheet1!A1:J1'))->throw();

        if (empty($response->json('values'))) {
            $this->client()
                ->put($this->valuesUrl($sheetId, 'Sheet1!A1:J1').'?valueInputOption=RAW', [
                    'values' => [self::HEADER],
                ])
                ->throw();
        }
    }

    private function appendRows(string $sheetId, Collection $registrations): void
    {
        $rows = $registrations->map(fn (Registration $r) => [
            $r->salutation,
            $r->name,
            $r->guest_number,
            $r->category?->name,
            $r->email,
            $r->phone,
            $r->address,
            $r->designation,
            $r->organization,
            $r->sectors->pluck('name')->join(', '),
        ])->all();

        $this->client()
            ->post($this->valuesUrl($sheetId, 'Sheet1!A:J').':append?valueInputOption=RAW&insertDataOption=INSERT_ROWS', [
                'values' => $rows,
            ])
            ->throw();
    }

    private function valuesUrl(string $sheetId, string $range): string
    {
        return "https://sheets.googleapis.com/v4/spreadsheets/{$sheetId}/values/".rawurlencode($range);
    }

    private function client(): PendingRequest
    {
        return Http::withToken($this->getAccessToken())->acceptJson();
    }

    private function getAccessToken(): string
    {
        return Cache::remember('google_sheets.access_token', now()->addMinutes(55), function () {
            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $this->buildJwt(),
            ])->throw();

            return $response->json('access_token');
        });
    }

    private function buildJwt(): string
    {
        $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));

        $now = time();
        $claims = $this->base64UrlEncode(json_encode([
            'iss' => config('services.google_sheets.client_email'),
            'scope' => 'https://www.googleapis.com/auth/spreadsheets',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ]));

        $signingInput = "{$header}.{$claims}";

        $privateKey = openssl_pkey_get_private((string) config('services.google_sheets.private_key'));

        if (! $privateKey) {
            throw new \RuntimeException('Google Sheets: failed to load service account private key. Check GOOGLE_SHEETS_PRIVATE_KEY.');
        }

        $signature = '';
        $ok = openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        if (! $ok) {
            throw new \RuntimeException('Google Sheets: openssl_sign failed: '.openssl_error_string());
        }

        return "{$signingInput}.".$this->base64UrlEncode($signature);
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
