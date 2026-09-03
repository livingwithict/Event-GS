<?php

namespace Tests\Feature;

use App\Filament\Resources\LogisticsResource;
use App\Models\DeliveryMean;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Deliveries tab is the audit view for who has whose card, so the
 * assignment made on the Tracking page must be visible here too.
 */
class LogisticsDeliveryMeanColumnTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_deliveries_table_shows_the_assigned_delivery_means(): void
    {
        $event = Event::factory()->create(['status' => 'published']);
        $mean = DeliveryMean::factory()->create(['event_id' => $event->id, 'name' => 'PickAndDrop']);
        Registration::factory()->create(['event_id' => $event->id, 'delivery_mean_id' => $mean->id]);

        $this->actingAs(User::factory()->create(['role' => 'super_admin']));

        $content = $this->get(LogisticsResource::getUrl('index'))->assertSuccessful()->getContent();

        $this->assertStringContainsString('PickAndDrop', $content);
    }
}
