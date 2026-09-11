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

    /**
     * @param  array<int, array{sheetId: int, title: string}>  $sheets  the spreadsheet's current tabs
     */
    private function fakeGoogle(array $sheets = [['sheetId' => 0, 'title' => 'Sheet1']], ?callable $onWrite = null): void
    {
        Http::fake(function ($request) use ($sheets, $onWrite) {
            $url = $request->url();

            if (Str::contains($url, 'oauth2.googleapis.com')) {
                return Http::response(['access_token' => 'fake-token']);
            }

            // Metadata call (gid -> tab name resolution): no /values/ segment.
            if ($request->method() === 'GET' && ! Str::contains($url, '/values/')) {
                return Http::response(['sheets' => array_map(fn ($s) => ['properties' => $s], $sheets)]);
            }

            if ($request->method() === 'GET' && Str::contains($url, '/values/')) {
                if ($onWrite) {
                    $onWrite($request);
                }

                return Http::response(['values' => [
                    ['Title', 'Name', 'Guest #', 'Category', 'Email', 'Phone', 'Address', 'Designation', 'Organization', 'Sector'],
                ]]);
            }

            if (Str::contains($url, ':append')) {
                if ($onWrite) {
                    $onWrite($request);
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
        $this->fakeGoogle(onWrite: function ($request) use (&$appendedPayload) {
            if (Str::contains($request->url(), ':append')) {
                $appendedPayload = $request->data();
            }
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

    public function test_sync_writes_to_the_tab_matching_the_configured_gid(): void
    {
        $event = Event::factory()->create(['google_sheet_id' => 'sheet-123', 'google_sheet_tab_gid' => '999']);
        Registration::factory()->create(['event_id' => $event->id, 'sheet_synced_at' => null]);

        $writeUrls = [];
        $this->fakeGoogle(
            sheets: [
                ['sheetId' => 0, 'title' => 'Sheet1'],
                ['sheetId' => 999, 'title' => 'DNC Guests'],
            ],
            onWrite: function ($request) use (&$writeUrls) {
                $writeUrls[] = $request->url();
            },
        );

        app(GoogleSheetsService::class)->syncEvent($event);

        $this->assertNotEmpty($writeUrls);
        foreach ($writeUrls as $url) {
            $this->assertStringContainsString(rawurlencode("'DNC Guests'"), $url);
        }
    }

    public function test_sync_still_finds_the_tab_by_gid_after_it_was_renamed(): void
    {
        // Simulates: the tab was "Old Name" when first linked, later renamed to
        // "New Name" in Google Sheets. gid is the same all along.
        $event = Event::factory()->create(['google_sheet_id' => 'sheet-123', 'google_sheet_tab_gid' => '999']);
        Registration::factory()->create(['event_id' => $event->id, 'sheet_synced_at' => null]);

        $writeUrls = [];
        $this->fakeGoogle(
            sheets: [['sheetId' => 999, 'title' => 'New Name']],
            onWrite: function ($request) use (&$writeUrls) {
                $writeUrls[] = $request->url();
            },
        );

        $count = app(GoogleSheetsService::class)->syncEvent($event);

        $this->assertSame(1, $count);
        $this->assertStringContainsString(rawurlencode("'New Name'"), $writeUrls[0]);
    }

    public function test_sync_falls_back_to_the_first_tab_when_no_gid_is_configured(): void
    {
        $event = Event::factory()->create(['google_sheet_id' => 'sheet-123', 'google_sheet_tab_gid' => null]);
        Registration::factory()->create(['event_id' => $event->id, 'sheet_synced_at' => null]);

        $writeUrls = [];
        $this->fakeGoogle(
            sheets: [
                ['sheetId' => 111, 'title' => 'First Tab'],
                ['sheetId' => 222, 'title' => 'Second Tab'],
            ],
            onWrite: function ($request) use (&$writeUrls) {
                $writeUrls[] = $request->url();
            },
        );

        app(GoogleSheetsService::class)->syncEvent($event);

        $this->assertStringContainsString(rawurlencode("'First Tab'"), $writeUrls[0]);
    }

    public function test_sync_throws_a_clear_error_when_the_configured_tab_no_longer_exists(): void
    {
        $event = Event::factory()->create(['google_sheet_id' => 'sheet-123', 'google_sheet_tab_gid' => '999']);
        Registration::factory()->create(['event_id' => $event->id, 'sheet_synced_at' => null]);

        $this->fakeGoogle(sheets: [['sheetId' => 0, 'title' => 'Sheet1']]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/could not be found/');

        app(GoogleSheetsService::class)->syncEvent($event);
    }
}
