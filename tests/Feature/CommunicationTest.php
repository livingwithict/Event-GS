<?php

use App\Jobs\SendBulkEmail;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CommunicationTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = User::factory()->create(['role' => 'event_manager']);
    }

    public function test_send_invites_dispatches_bulk_email_job(): void
    {
        Queue::fake();
        $event = Event::factory()->create();
        $regs = Registration::factory()->count(3)->create(['event_id' => $event->id, 'email' => fake()->safeEmail()]);

        $response = $this->actingAs($this->manager)
            ->postJson("/api/event/{$event->id}/send-invites", [
                'type' => 'email',
                'subject' => 'You are invited!',
                'registration_ids' => $regs->pluck('id')->toArray(),
            ]);

        $response->assertOk();
        $response->assertJson(['message' => 'Email jobs dispatched.']);
        Queue::assertPushed(SendBulkEmail::class);
    }

    public function test_send_invites_validates_type(): void
    {
        $event = Event::factory()->create();

        $response = $this->actingAs($this->manager)
            ->postJson("/api/event/{$event->id}/send-invites", [
                'type' => 'whatsapp',
            ]);

        $response->assertStatus(422);
    }

    public function test_communication_log_created_on_send(): void
    {
        $event = Event::factory()->create();
        $reg = Registration::factory()->create(['event_id' => $event->id, 'email' => 'test@example.com']);

        // Dispatch job synchronously for testing
        $job = new \App\Jobs\SendBulkEmail([$reg->id], $event->id, 'Test Subject');
        $job->handle(app(\App\Services\CommunicationService::class));

        $this->assertDatabaseHas('communications', [
            'registration_id' => $reg->id,
            'type' => 'email',
        ]);
    }
}
