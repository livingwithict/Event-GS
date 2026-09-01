<?php

namespace Tests\Feature;

use App\Filament\Pages\ScanStation;
use App\Models\Event;
use App\Models\ParticipantCategory;
use App\Models\Registration;
use App\Models\ScanActionType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ScanStationTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->event = Event::factory()->create(['status' => 'published']);
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
    }

    private function action(string $code): ScanActionType
    {
        return ScanActionType::where('event_id', $this->event->id)->where('action_code', $code)->firstOrFail();
    }

    private function station(): \Livewire\Features\SupportTesting\Testable
    {
        return Livewire::test(ScanStation::class)->set('eventId', $this->event->id);
    }

    public function test_event_gets_entrance_lunch_and_dinner_actions(): void
    {
        $this->assertEqualsCanonicalizing(
            ['CHECKIN', 'LUNCH', 'DINNER'],
            ScanActionType::where('event_id', $this->event->id)->pluck('action_code')->all(),
        );
        $this->assertSame('entry_time', $this->action('CHECKIN')->column_mapping);
    }

    public function test_scanning_the_invitation_code_records_entry(): void
    {
        $reg = Registration::factory()->create(['event_id' => $this->event->id, 'approval_status' => 'approved']);

        $this->station()
            ->set('actionTypeId', $this->action('CHECKIN')->id)
            ->set('code', $reg->guest_number)
            ->call('scan')
            ->assertSet('result.status', 'ok')
            ->assertSet('code', '');

        $this->assertNotNull($reg->fresh()->entry_time);
        $this->assertDatabaseHas('scan_logs', [
            'participant_id' => $reg->id,
            'action_type_id' => $this->action('CHECKIN')->id,
        ]);
    }

    public function test_second_scan_of_the_same_action_warns_instead_of_recording_twice(): void
    {
        $reg = Registration::factory()->create(['event_id' => $this->event->id, 'approval_status' => 'approved']);
        $checkin = $this->action('CHECKIN')->id;

        $station = $this->station()->set('actionTypeId', $checkin);
        $station->set('code', $reg->guest_number)->call('scan')->assertSet('result.status', 'ok');
        $station->set('code', $reg->guest_number)->call('scan')->assertSet('result.status', 'warning');

        $this->assertSame(1, $reg->scanLogs()->where('action_type_id', $checkin)->count());
    }

    public function test_lunch_is_separate_from_entrance(): void
    {
        $reg = Registration::factory()->create(['event_id' => $this->event->id, 'approval_status' => 'approved']);

        $station = $this->station();
        $station->set('actionTypeId', $this->action('CHECKIN')->id)->set('code', $reg->guest_number)->call('scan');
        $station->set('actionTypeId', $this->action('LUNCH')->id)->set('code', $reg->guest_number)->call('scan')
            ->assertSet('result.status', 'ok');

        $this->assertNotNull($reg->fresh()->lunch_used_at);
    }

    public function test_unapproved_guest_is_refused_at_the_entrance(): void
    {
        $reg = Registration::factory()->create(['event_id' => $this->event->id, 'approval_status' => 'pending']);

        $this->station()
            ->set('actionTypeId', $this->action('CHECKIN')->id)
            ->set('code', $reg->guest_number)
            ->call('scan')
            ->assertSet('result.status', 'error');

        $this->assertNull($reg->fresh()->entry_time);
    }

    public function test_lowercase_code_from_an_old_qr_still_resolves(): void
    {
        $reg = Registration::factory()->create(['event_id' => $this->event->id, 'approval_status' => 'approved']);

        $this->station()
            ->set('actionTypeId', $this->action('CHECKIN')->id)
            ->set('code', strtolower($reg->guest_number))
            ->call('scan')
            ->assertSet('result.status', 'ok');

        $this->assertNotNull($reg->fresh()->entry_time);
    }

    public function test_unknown_code_is_refused(): void
    {
        $this->station()
            ->set('actionTypeId', $this->action('CHECKIN')->id)
            ->set('code', 'DNC26-NOPE1')
            ->call('scan')
            ->assertSet('result.status', 'error');
    }

    public function test_guest_from_another_event_is_refused(): void
    {
        $other = Registration::factory()->create(['approval_status' => 'approved']);

        $this->station()
            ->set('actionTypeId', $this->action('CHECKIN')->id)
            ->set('code', $other->guest_number)
            ->call('scan')
            ->assertSet('result.status', 'error');

        $this->assertNull($other->fresh()->entry_time);
    }

    public function test_typing_a_name_live_searches_within_the_event(): void
    {
        Registration::factory()->create(['event_id' => $this->event->id, 'name' => 'Sita Rai']);
        $other = Registration::factory()->create(['name' => 'Sita Gurung']); // different event

        $station = $this->station()
            ->set('actionTypeId', 'VIEW_STATUS')
            ->set('nameQuery', 'sita');

        $this->assertCount(1, $station->get('nameResults'));
        $this->assertSame('Sita Rai', $station->get('nameResults')[0]['label']);
        $this->assertNotSame($other->id, $station->get('nameResults')[0]['id']);
    }

    public function test_short_name_query_returns_no_results(): void
    {
        Registration::factory()->create(['event_id' => $this->event->id, 'name' => 'Sita Rai']);

        $station = $this->station()
            ->set('actionTypeId', 'VIEW_STATUS')
            ->set('nameQuery', 's');

        $this->assertSame([], $station->get('nameResults'));
    }

    public function test_selecting_a_name_result_shows_that_guests_status(): void
    {
        $reg = Registration::factory()->create(['event_id' => $this->event->id, 'name' => 'Sita Rai']);
        Registration::factory()->create(['event_id' => $this->event->id, 'name' => 'Sita Gurung']);

        $this->station()
            ->set('actionTypeId', 'VIEW_STATUS')
            ->set('nameQuery', 'sita')
            ->call('selectNameResult', $reg->id)
            ->assertSet('result.status', 'view')
            ->assertSet('result.title', 'Sita Rai')
            ->assertSet('nameQuery', '')
            ->assertSet('nameResults', []);
    }

    public function test_the_code_field_still_requires_an_exact_match(): void
    {
        Registration::factory()->create(['event_id' => $this->event->id, 'name' => 'Sita Rai']);

        $this->station()
            ->set('actionTypeId', 'VIEW_STATUS')
            ->set('code', 'sita')
            ->call('scan')
            ->assertSet('result.status', 'error');
    }

    public function test_category_permissions_block_a_disallowed_action(): void
    {
        $category = ParticipantCategory::factory()->create([
            'event_id' => $this->event->id,
            'qr_access_permissions' => ['CHECKIN'],
        ]);
        $reg = Registration::factory()->create([
            'event_id' => $this->event->id,
            'category_id' => $category->id,
            'approval_status' => 'approved',
        ]);

        $this->station()
            ->set('actionTypeId', $this->action('LUNCH')->id)
            ->set('code', $reg->guest_number)
            ->call('scan')
            ->assertSet('result.status', 'error');

        $this->assertNull($reg->fresh()->lunch_used_at);
    }
}
