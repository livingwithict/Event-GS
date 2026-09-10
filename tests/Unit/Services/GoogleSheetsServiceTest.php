<?php

namespace Tests\Unit\Services;

use App\Models\Event;
use App\Models\Registration;
use App\Services\GoogleSheetsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class GoogleSheetsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $privateKeyPem);

        config()->set('services.google_sheets.client_email', 'test@test.iam.gserviceaccount.com');
        config()->set('services.google_sheets.private_key', $privateKeyPem);
    }

    private function fakeGoogle(?callable $onAppend = null): void
    {
        Http::fake(function ($request) use ($onAppend) {
            if (Str::contains($request->url(), 'oauth2.googleapis.com')) {
                return Http::response(['access_token' => 'fake-token']);
            }

            if ($request->method() === 'GET' && Str::contains($request->url(), 'A1%3AJ1')) {
                return Http::response(['values' => [
                    ['Title', 'Name', 'Guest #', 'Category', 'Email', 'Phone', 'Address', 'Designation', 'Organization', 'Sector'],
                ]]);
            }

            if (Str::contains($request->url(), ':append')) {
                if ($onAppend) {
                    $onAppend($request);
                }

                return Http::response(['updates' => ['updatedRows' => count($request->data()['values'] ?? [])]]);
            }

            return Http::response([], 404);
        });
    }

    public function test_sync_appends_only_unsynced_registrations_and_marks_them_synced(): void
    {
        $event = Event::factory()->create(['google_sheet_id' => 'sheet-123']);

        $already = Registration::factory()->create([
            'event_id' => $event->id,
            'sheet_synced_at' => now()->subDay(),
        ]);
        $originalSyncedAt = $already->sheet_synced_at;

        Registration::factory()->create([
            'event_id' => $event->id,
            'sheet_synced_at' => null,
            'name' => 'Fresh Guest',
        ]);

        $appendedPayload = null;
        $this->fakeGoogle(function ($request) use (&$appendedPayload) {
            $appendedPayload = $request->data();
        });

        $count = app(GoogleSheetsService::class)->syncEvent($event);

        $this->assertSame(1, $count);
        $this->assertSame('Fresh Guest', $appendedPayload['values'][0][1]);
        $this->assertTrue($already->fresh()->sheet_synced_at->equalTo($originalSyncedAt));
    }

    public function test_sync_returns_zero_and_makes_no_http_calls_when_nothing_new(): void
    {
        $event = Event::factory()->create(['google_sheet_id' => 'sheet-123']);
        Registration::factory()->create(['event_id' => $event->id, 'sheet_synced_at' => now()]);

        Http::fake();

        $count = app(GoogleSheetsService::class)->syncEvent($event);

        $this->assertSame(0, $count);
        Http::assertNothingSent();
    }

    public function test_sync_throws_when_no_sheet_configured(): void
    {
        $event = Event::factory()->create(['google_sheet_id' => null]);
        Registration::factory()->create(['event_id' => $event->id]);

        $this->expectException(\RuntimeException::class);

        app(GoogleSheetsService::class)->syncEvent($event);
    }

    public function test_sync_translates_403_into_a_share_the_sheet_message(): void
    {
        $event = Event::factory()->create(['google_sheet_id' => 'sheet-123']);
        Registration::factory()->create(['event_id' => $event->id, 'sheet_synced_at' => null]);

        Http::fake(function ($request) {
            if (Str::contains($request->url(), 'oauth2.googleapis.com')) {
                return Http::response(['access_token' => 'fake-token']);
            }

            return Http::response(['error' => 'Forbidden'], 403);
        });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Share it with test@test\.iam\.gserviceaccount\.com/');

        app(GoogleSheetsService::class)->syncEvent($event);
    }
}
