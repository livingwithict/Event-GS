<?php

namespace Tests\Feature;

use App\Filament\Pages\Tracking;
use App\Models\DeliveryMean;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class TrackingPageTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->event = Event::factory()->create(['status' => 'published']);
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
    }

    private function page(): Testable
    {
        return Livewire::test(Tracking::class)->set('eventId', $this->event->id);
    }

    public function test_scanning_a_guest_assigns_the_selected_delivery_mean(): void
    {
        $mean = DeliveryMean::factory()->create(['event_id' => $this->event->id]);
        $reg = Registration::factory()->create(['event_id' => $this->event->id]);

        $this->page()
            ->set('deliveryMeanId', $mean->id)
            ->set('code', $reg->guest_number)
            ->call('scan')
            ->assertSet('result.status', 'ok');

        $this->assertSame($mean->id, $reg->fresh()->delivery_mean_id);
    }

    public function test_rescanning_with_a_different_delivery_mean_overwrites_the_assignment(): void
    {
        $first = DeliveryMean::factory()->create(['event_id' => $this->event->id]);
        $second = DeliveryMean::factory()->create(['event_id' => $this->event->id]);
        $reg = Registration::factory()->create(['event_id' => $this->event->id]);

        $station = $this->page();
        $station->set('deliveryMeanId', $first->id)->set('code', $reg->guest_number)->call('scan');
        $station->set('deliveryMeanId', $second->id)->set('code', $reg->guest_number)->call('scan')
            ->assertSet('result.status', 'ok');

        $this->assertSame($second->id, $reg->fresh()->delivery_mean_id);
    }

    public function test_scanning_without_selecting_a_delivery_mean_fails(): void
    {
        $reg = Registration::factory()->create(['event_id' => $this->event->id]);

        $this->page()
            ->set('code', $reg->guest_number)
            ->call('scan')
            ->assertSet('result.status', 'error');

        $this->assertNull($reg->fresh()->delivery_mean_id);
    }

    public function test_unknown_code_is_refused(): void
    {
        $mean = DeliveryMean::factory()->create(['event_id' => $this->event->id]);

        $this->page()
            ->set('deliveryMeanId', $mean->id)
            ->set('code', 'NOPE1234')
            ->call('scan')
            ->assertSet('result.status', 'error');
    }

    public function test_guest_from_another_event_is_refused(): void
    {
        $mean = DeliveryMean::factory()->create(['event_id' => $this->event->id]);
        $other = Registration::factory()->create();

        $this->page()
            ->set('deliveryMeanId', $mean->id)
            ->set('code', $other->guest_number)
            ->call('scan')
            ->assertSet('result.status', 'error');

        $this->assertNull($other->fresh()->delivery_mean_id);
    }

    public function test_name_search_shows_a_guests_current_delivery_mean(): void
    {
        $mean = DeliveryMean::factory()->create(['event_id' => $this->event->id, 'name' => 'PickAndDrop']);
        Registration::factory()->create(['event_id' => $this->event->id, 'name' => 'Sita Rai', 'delivery_mean_id' => $mean->id]);
        Registration::factory()->create(['event_id' => $this->event->id, 'name' => 'Sita Gurung']);

        $results = $this->page()->set('nameQuery', 'sita')->get('nameResults');

        $this->assertCount(2, $results);
        $byName = collect($results)->keyBy('label');
        $this->assertSame('PickAndDrop', $byName['Sita Rai']['delivery_mean']);
        $this->assertNull($byName['Sita Gurung']['delivery_mean']);
    }

    public function test_selecting_a_delivery_mean_in_lookup_shows_description_and_assigned_guests(): void
    {
        $mean = DeliveryMean::factory()->create([
            'event_id' => $this->event->id,
            'name' => 'Courier X',
            'description' => 'Third-party courier',
        ]);
        $other = DeliveryMean::factory()->create(['event_id' => $this->event->id]);
        Registration::factory()->create(['event_id' => $this->event->id, 'name' => 'Alpha', 'delivery_mean_id' => $mean->id]);
        Registration::factory()->create(['event_id' => $this->event->id, 'name' => 'Beta', 'delivery_mean_id' => $mean->id]);
        Registration::factory()->create(['event_id' => $this->event->id, 'name' => 'Gamma', 'delivery_mean_id' => $other->id]);

        $station = $this->page()->set('lookupMeanId', $mean->id);

        $this->assertSame('Third-party courier', $station->get('lookupMeanDescription'));
        $this->assertEqualsCanonicalizing(['Alpha', 'Beta'], collect($station->get('lookupMeanGuests'))->pluck('label')->all());
    }

    public function test_delivery_means_are_scoped_to_the_active_event(): void
    {
        DeliveryMean::factory()->create(['event_id' => $this->event->id, 'name' => 'In Event']);
        DeliveryMean::factory()->create(['name' => 'Other Event']);

        $means = $this->page()->instance()->deliveryMeans();

        $this->assertSame(['In Event'], $means->values()->all());
    }

    public function test_creating_a_new_delivery_mean_via_the_modal(): void
    {
        $this->page()
            ->set('newMeanName', 'Self Delivery')
            ->set('newMeanDescription', 'Delivered by our own staff')
            ->call('createMean');

        $this->assertDatabaseHas('delivery_means', [
            'event_id' => $this->event->id,
            'name' => 'Self Delivery',
            'description' => 'Delivered by our own staff',
        ]);
    }

    public function test_editing_a_delivery_mean_updates_its_name_and_description(): void
    {
        $mean = DeliveryMean::factory()->create(['event_id' => $this->event->id, 'name' => 'Old Name']);

        $this->page()
            ->call('startEditMean', $mean->id)
            ->assertSet('editMeanName', 'Old Name')
            ->set('editMeanName', 'New Name')
            ->set('editMeanDescription', 'Updated description')
            ->call('saveEditMean');

        $mean->refresh();
        $this->assertSame('New Name', $mean->name);
        $this->assertSame('Updated description', $mean->description);
    }

    public function test_requesting_delete_reports_how_many_guests_are_assigned(): void
    {
        $mean = DeliveryMean::factory()->create(['event_id' => $this->event->id]);
        Registration::factory()->count(2)->create(['event_id' => $this->event->id, 'delivery_mean_id' => $mean->id]);

        $this->page()
            ->call('requestDeleteMean', $mean->id)
            ->assertSet('confirmingDeleteMeanId', $mean->id)
            ->assertSet('confirmingDeleteMeanGuestCount', 2);
    }

    public function test_confirming_delete_removes_the_mean_and_clears_guest_assignments(): void
    {
        $mean = DeliveryMean::factory()->create(['event_id' => $this->event->id]);
        $reg = Registration::factory()->create(['event_id' => $this->event->id, 'delivery_mean_id' => $mean->id]);

        $this->page()
            ->call('requestDeleteMean', $mean->id)
            ->call('confirmDeleteMean');

        $this->assertDatabaseMissing('delivery_means', ['id' => $mean->id]);
        $this->assertNull($reg->fresh()->delivery_mean_id);
    }
}
