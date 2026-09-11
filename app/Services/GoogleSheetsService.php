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
            $tabTitle = $this->resolveTabTitle($event->google_sheet_id, $event->google_sheet_tab_gid);
            $this->ensureHeaderRow($event->google_sheet_id, $tabTitle);
            $this->appendRows($event->google_sheet_id, $tabTitle, $registrations);
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

    /**
     * The values API addresses tabs by their current name, not their stable
     * gid — so every sync re-resolves gid -> name fresh. That's what makes
     * renaming the tab later safe: the gid never changes, only the lookup.
     */
    private function resolveTabTitle(string $sheetId, ?string $tabGid): string
    {
        $response = $this->client()
            ->get("https://sheets.googleapis.com/v4/spreadsheets/{$sheetId}", [
                'fields' => 'sheets.properties(sheetId,title)',
            ])
            ->throw();

        $sheets = $response->json('sheets') ?? [];

        if (empty($sheets)) {
            throw new \RuntimeException('The linked Google Sheet has no tabs.');
        }

        if ($tabGid === null) {
            return $sheets[0]['properties']['title'];
        }

        foreach ($sheets as $sheet) {
            if ((string) ($sheet['properties']['sheetId'] ?? '') === (string) $tabGid) {
                return $sheet['properties']['title'];
            }
        }

        throw new \RuntimeException('The linked tab could not be found — it may have been deleted. Re-paste the sheet link on the Event edit page.');
    }

    private function ensureHeaderRow(string $sheetId, string $tabTitle): void
    {
        $range = $this->quotedSheetName($tabTitle).'!A1:J1';
        $response = $this->client()->get($this->valuesUrl($sheetId, $range))->throw();

        if (empty($response->json('values'))) {
            $this->client()
                ->put($this->valuesUrl($sheetId, $range).'?valueInputOption=RAW', [
                    'values' => [self::HEADER],
                ])
                ->throw();
        }
    }

    private function appendRows(string $sheetId, string $tabTitle, Collection $registrations): void
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

        $range = $this->quotedSheetName($tabTitle).'!A:J';

        $this->client()
            ->post($this->valuesUrl($sheetId, $range).':append?valueInputOption=RAW&insertDataOption=INSERT_ROWS', [
                'values' => $rows,
            ])
            ->throw();
    }

    private function valuesUrl(string $sheetId, string $range): string
    {
        return "https://sheets.googleapis.com/v4/spreadsheets/{$sheetId}/values/".rawurlencode($range);
    }

    /** A1 notation requires single-quoted sheet names; any literal quote in the name doubles up. */
    private function quotedSheetName(string $title): string
    {
        return "'".str_replace("'", "''", $title)."'";
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
