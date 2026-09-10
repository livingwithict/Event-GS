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
