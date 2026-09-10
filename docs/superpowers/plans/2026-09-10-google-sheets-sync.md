# Google Sheets Registration Sync Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add an "Update Spreadsheet" button to the Registrations list that appends newly-added registrants for the active event to that event's linked Google Sheet, never touching rows already there.

**Architecture:** A service-account JWT (signed with PHP's built-in `openssl`, no new Composer package) authenticates to the Sheets API v4 REST endpoints via Laravel's `Http` facade. Each `Event` stores a `google_sheet_id`; each `Registration` stores a `sheet_synced_at` timestamp that marks it as already appended — `whereNull('sheet_synced_at')` is the entire "what's new" query, and only rows that append successfully get marked, so a failed sync is retried automatically on the next click.

**Tech Stack:** Laravel 13, Filament 5, PHPUnit (via `php artisan test`), PHP's `openssl` extension, Laravel `Http` client — no new Composer dependencies.

**Spec:** `docs/superpowers/specs/2026-09-10-google-sheets-sync-design.md`

## Global Constraints

- No new Composer dependency — auth and HTTP calls use only `openssl_*` functions and Laravel's `Http` facade.
- Sync is per-event, scoped by `google_sheet_id` on the `events` table — never a single global spreadsheet.
- Sync is append-only: existing sheet rows (and any manual edits/coloring on them) must never be overwritten or re-sent.
- Sheet columns, in order: Title, Name, Guest #, Category, Email, Phone, Address, Designation, Organization, Sector.
- On Windows, PHPUnit must run via `composer test` (not raw `php artisan test`), because `tests/run-with-openssl-env.php` sets `OPENSSL_CONF` — without it, `openssl_pkey_new()` fails with "BIO routines::no such file" in this environment.

---

### Task 1: Schema — `google_sheet_id` on events, `sheet_synced_at` on registrations

**Files:**
- Create: `database/migrations/2026_09_10_120000_add_google_sheet_id_to_events_table.php`
- Create: `database/migrations/2026_09_10_120001_add_sheet_synced_at_to_registrations_table.php`
- Modify: `app/Models/Event.php` (fillable + new static helper)
- Modify: `app/Models/Registration.php` (cast for `sheet_synced_at`)
- Test: `tests/Unit/Models/EventModelTest.php`

**Interfaces:**
- Produces: `Event::extractGoogleSheetId(?string $value): ?string` — later tasks (the Event form) call this directly.
- Produces: `events.google_sheet_id` (nullable string), `registrations.sheet_synced_at` (nullable datetime, cast) — later tasks query/set these.

- [ ] **Step 1: Write the failing test for `extractGoogleSheetId`**

Add to `tests/Unit/Models/EventModelTest.php`:

```php
    public function test_extract_google_sheet_id_from_full_url(): void
    {
        $url = 'https://docs.google.com/spreadsheets/d/1AbC-dEf_23XYZ/edit#gid=0';
        $this->assertEquals('1AbC-dEf_23XYZ', Event::extractGoogleSheetId($url));
    }

    public function test_extract_google_sheet_id_from_bare_id(): void
    {
        $this->assertEquals('1AbC-dEf_23XYZ', Event::extractGoogleSheetId('1AbC-dEf_23XYZ'));
    }

    public function test_extract_google_sheet_id_returns_null_for_blank_input(): void
    {
        $this->assertNull(Event::extractGoogleSheetId(''));
        $this->assertNull(Event::extractGoogleSheetId(null));
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer test -- --filter=test_extract_google_sheet_id`
Expected: FAIL with "Call to undefined method App\Models\Event::extractGoogleSheetId()"

- [ ] **Step 3: Create the events migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('google_sheet_id')->nullable()->after('settings');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('google_sheet_id');
        });
    }
};
```

- [ ] **Step 4: Create the registrations migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->timestamp('sheet_synced_at')->nullable()->after('thirdfactor_enrolled_at');
        });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropColumn('sheet_synced_at');
        });
    }
};
```

- [ ] **Step 5: Add `google_sheet_id` to Event's fillable and add the extraction helper**

In `app/Models/Event.php`, add `'google_sheet_id'` to the `$fillable` array (after `'created_by'`), and add this method after `imageDataUri()`:

```php
    /**
     * Accepts either a bare spreadsheet ID or a full Google Sheets URL
     * (https://docs.google.com/spreadsheets/d/{id}/edit#gid=0) and returns
     * just the ID, so the Event form field can take whatever a person pastes.
     */
    public static function extractGoogleSheetId(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (preg_match('/\/spreadsheets\/d\/([a-zA-Z0-9_-]+)/', $value, $matches)) {
            return $matches[1];
        }

        return $value;
    }
```

- [ ] **Step 6: Add the `sheet_synced_at` cast to Registration**

In `app/Models/Registration.php`, add `'sheet_synced_at' => 'datetime',` to the `casts()` array (alongside `'thirdfactor_enrolled_at' => 'datetime',`).

- [ ] **Step 7: Run migrations and the test again**

Run: `php artisan migrate`
Run: `composer test -- --filter=test_extract_google_sheet_id`
Expected: PASS (all 3 assertions)

- [ ] **Step 8: Commit**

```bash
git add database/migrations app/Models/Event.php app/Models/Registration.php tests/Unit/Models/EventModelTest.php
git commit -m "$(cat <<'EOF'
Add google_sheet_id and sheet_synced_at columns for sheet sync

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 2: `GoogleSheetsService` — auth, header check, append, credentials config

**Files:**
- Modify: `config/services.php`
- Modify: `.env.example`
- Create: `app/Services/GoogleSheetsService.php`
- Test: `tests/Unit/Services/GoogleSheetsServiceTest.php`

**Interfaces:**
- Consumes: `Event::extractGoogleSheetId()` is not used here (only in the form task); this task consumes `$event->google_sheet_id` directly and `$event->registrations()`.
- Produces: `GoogleSheetsService::syncEvent(Event $event): int` — returns the count appended; throws `\RuntimeException` with a user-readable message on any failure (no sheet configured, Google 403, any other HTTP failure). Task 4 (the button) calls this and catches `\RuntimeException`.

- [ ] **Step 1: Add credentials to `config/services.php`**

Add this entry (matching the existing `pickndrop`/`thirdfactor` style):

```php
    'google_sheets' => [
        'client_email' => env('GOOGLE_SHEETS_CLIENT_EMAIL'),
        'private_key' => env('GOOGLE_SHEETS_PRIVATE_KEY'),
    ],
```

- [ ] **Step 2: Add the env vars to `.env.example`**

Add near the `PICKNDROP_*` block:

```
# Service account for Google Sheets sync. private_key is the PEM from the
# downloaded JSON key, with real newlines replaced by \n so it fits one line.
GOOGLE_SHEETS_CLIENT_EMAIL=
GOOGLE_SHEETS_PRIVATE_KEY=
```

- [ ] **Step 3: Write the failing test**

Create `tests/Unit/Services/GoogleSheetsServiceTest.php`:

```php
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
```

- [ ] **Step 4: Run tests to verify they fail**

Run: `composer test -- --filter=GoogleSheetsServiceTest`
Expected: FAIL with "Class App\Services\GoogleSheetsService not found"

- [ ] **Step 5: Implement `GoogleSheetsService`**

Create `app/Services/GoogleSheetsService.php`:

```php
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
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `composer test -- --filter=GoogleSheetsServiceTest`
Expected: PASS (4 tests)

- [ ] **Step 7: Commit**

```bash
git add config/services.php .env.example app/Services/GoogleSheetsService.php tests/Unit/Services/GoogleSheetsServiceTest.php
git commit -m "$(cat <<'EOF'
Add GoogleSheetsService for one-way append-only sheet sync

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 3: Event form field to link a Google Sheet

**Files:**
- Modify: `app/Filament/Resources/EventResource.php`

**Interfaces:**
- Consumes: `Event::extractGoogleSheetId(?string $value): ?string` from Task 1.
- Produces: nothing new consumed by later tasks — this is a leaf UI change. (Task 4 reads `$event->google_sheet_id` directly, which Task 1 already produced.)

- [ ] **Step 1: Add the field to the "Event Details" section**

In `app/Filament/Resources/EventResource.php`, inside the `Section::make('Event Details')` schema (after the `contact_info` `TextInput`), add:

```php
                        TextInput::make('google_sheet_id')
                            ->label('Google Sheet URL')
                            ->maxLength(255)
                            ->dehydrateStateUsing(fn (?string $state) => Event::extractGoogleSheetId($state))
                            ->helperText(fn () => 'Paste the sheet URL, then share it as an Editor with: '
                                .(config('services.google_sheets.client_email') ?: '(service account email not configured)')),
```

This binds directly to the `google_sheet_id` model attribute — no separate virtual field needed. Whatever gets typed (a full URL or a bare ID) is normalized through `extractGoogleSheetId()` on save; on re-open, the raw stored ID is shown (acceptable — it's still a valid value to re-paste-over, and the helper text explains what's expected).

- [ ] **Step 2: Manually verify in the browser**

Run: `php artisan serve` (or your existing dev server), open an Event's edit page, confirm the "Google Sheet URL" field appears under Event Details with the helper text showing the service account email from `.env`.

- [ ] **Step 3: Commit**

```bash
git add app/Filament/Resources/EventResource.php
git commit -m "$(cat <<'EOF'
Add Google Sheet URL field to the Event form

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 4: "Update Spreadsheet" header action on the Registrations list

**Files:**
- Modify: `app/Filament/Resources/RegistrationResource/Pages/ListRegistrations.php`
- Test: `tests/Feature/UpdateSpreadsheetActionTest.php`

**Interfaces:**
- Consumes: `GoogleSheetsService::syncEvent(Event $event): int` (Task 2), `$event->google_sheet_id` (Task 1).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/UpdateSpreadsheetActionTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Filament\Resources\RegistrationResource\Pages\ListRegistrations;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class UpdateSpreadsheetActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $privateKeyPem);

        config()->set('services.google_sheets.client_email', 'test@test.iam.gserviceaccount.com');
        config()->set('services.google_sheets.private_key', $privateKeyPem);

        Http::fake(function ($request) {
            if (Str::contains($request->url(), 'oauth2.googleapis.com')) {
                return Http::response(['access_token' => 'fake-token']);
            }

            if ($request->method() === 'GET' && Str::contains($request->url(), 'A1%3AJ1')) {
                return Http::response(['values' => [['Title', 'Name']]]);
            }

            if (Str::contains($request->url(), ':append')) {
                return Http::response(['updates' => ['updatedRows' => 1]]);
            }

            return Http::response([], 404);
        });

        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
    }

    public function test_update_spreadsheet_appends_new_registrations_and_marks_them_synced(): void
    {
        $event = Event::factory()->create(['google_sheet_id' => 'sheet-123']);
        session(['active_event_id' => $event->id]);

        $registration = Registration::factory()->create(['event_id' => $event->id, 'sheet_synced_at' => null]);

        Livewire::test(ListRegistrations::class)
            ->callAction('update_spreadsheet')
            ->assertNotified();

        $this->assertNotNull($registration->fresh()->sheet_synced_at);
    }

    public function test_update_spreadsheet_warns_when_event_has_no_sheet_configured(): void
    {
        $event = Event::factory()->create(['google_sheet_id' => null]);
        session(['active_event_id' => $event->id]);

        Registration::factory()->create(['event_id' => $event->id]);

        Livewire::test(ListRegistrations::class)
            ->callAction('update_spreadsheet')
            ->assertNotified();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer test -- --filter=UpdateSpreadsheetActionTest`
Expected: FAIL — "Action [update_spreadsheet] does not exist" (or similar)

- [ ] **Step 3: Add imports and the header action**

In `app/Filament/Resources/RegistrationResource/Pages/ListRegistrations.php`, add these imports:

```php
use App\Enums\Ability;
use App\Models\Event;
use App\Services\GoogleSheetsService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
```

Replace the `getHeaderActions()` method with:

```php
    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('update_spreadsheet')
                ->label('Update Spreadsheet')
                ->icon('heroicon-o-table-cells')
                ->visible(fn () => Auth::user()?->hasAbility(Ability::GuestsEdit))
                ->action(function () {
                    $event = Event::find(session('active_event_id'));

                    if (! $event) {
                        Notification::make()
                            ->warning()
                            ->title('No active event selected')
                            ->send();

                        return;
                    }

                    try {
                        $count = app(GoogleSheetsService::class)->syncEvent($event);
                    } catch (\RuntimeException $e) {
                        Notification::make()
                            ->danger()
                            ->title('Spreadsheet sync failed')
                            ->body($e->getMessage())
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->success()
                        ->title($count > 0 ? "Appended {$count} new registrant(s)" : 'Nothing new to sync')
                        ->send();
                }),
            Actions\CreateAction::make(),
        ];
    }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `composer test -- --filter=UpdateSpreadsheetActionTest`
Expected: PASS (2 tests)

- [ ] **Step 5: Run the full test suite**

Run: `composer test`
Expected: All tests pass (no regressions in `RolePermissionTest`, `PanelAccessTest`, etc.)

- [ ] **Step 6: Manually verify in the browser**

1. Set your real `GOOGLE_SHEETS_CLIENT_EMAIL` / `GOOGLE_SHEETS_PRIVATE_KEY` in `.env`, run `php artisan config:clear`.
2. Create/open a test Google Sheet, share it with the service account email as Editor.
3. On the Event edit page, paste the sheet URL into "Google Sheet URL", save.
4. Go to the Registrations list for that event, click "Update Spreadsheet".
5. Confirm the header row and new registrant rows appear in the sheet.
6. Manually color a cell / add a remark in the sheet, add one more registrant in the app, click "Update Spreadsheet" again — confirm only the new row is appended and your edit is untouched.

- [ ] **Step 7: Commit**

```bash
git add app/Filament/Resources/RegistrationResource/Pages/ListRegistrations.php tests/Feature/UpdateSpreadsheetActionTest.php
git commit -m "$(cat <<'EOF'
Add Update Spreadsheet button to the Registrations list

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```
