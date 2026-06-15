<?php

use App\Jobs\SendBulkEmail;
use App\Jobs\SendBulkSMS;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use App\Services\CommunicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
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

    public function test_send_invites_dispatches_bulk_sms_job(): void
    {
        Queue::fake();
        $event = Event::factory()->create();
        $regs = Registration::factory()->count(3)->create(['event_id' => $event->id, 'phone' => '9800000001']);

        $response = $this->actingAs($this->manager)
            ->postJson("/api/event/{$event->id}/send-invites", [
                'type' => 'sms',
                'message' => 'Test SMS message',
                'registration_ids' => $regs->pluck('id')->toArray(),
            ]);

        $response->assertOk();
        $response->assertJson(['message' => 'Sms jobs dispatched.']);
        Queue::assertPushed(SendBulkSMS::class);
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

        $job = new SendBulkEmail([$reg->id], $event->id, 'Test Subject');
        $job->handle(app(CommunicationService::class));

        $this->assertDatabaseHas('communications', [
            'registration_id' => $reg->id,
            'type' => 'email',
        ]);
    }

    public function test_sms_with_log_driver_creates_communication(): void
    {
        Config::set('services.sparrow.driver', 'log');
        Config::set('services.sparrow.token', '');

        $event = Event::factory()->create();
        $reg = Registration::factory()->create([
            'event_id' => $event->id,
            'phone' => '9800000001',
        ]);

        $service = app(CommunicationService::class);
        $comm = $service->sendSms($reg, 'Test SMS', 'registration_confirmation');

        $this->assertEquals('sent', $comm->status);
        $this->assertDatabaseHas('communications', [
            'registration_id' => $reg->id,
            'type' => 'sms',
            'email_type' => 'registration_confirmation',
            'status' => 'sent',
        ]);
    }

    public function test_send_batch_sms_creates_individual_records(): void
    {
        Config::set('services.sparrow.driver', 'log');

        $event = Event::factory()->create();
        $regs = Registration::factory()->count(3)->create([
            'event_id' => $event->id,
            'phone' => '9800000001',
        ]);

        $service = app(CommunicationService::class);
        $comms = $service->sendBatchSms($regs->pluck('id')->toArray(), 'Batch SMS', 'event_reminder');

        $this->assertCount(3, $comms);
        foreach ($comms as $comm) {
            $this->assertEquals('sent', $comm->status);
        }
        $this->assertDatabaseCount('communications', 3);
    }

    public function test_bulk_sms_job_with_batch_size_1_sends_individually(): void
    {
        Config::set('services.sparrow.driver', 'log');

        $event = Event::factory()->create();
        $regs = Registration::factory()->count(3)->create([
            'event_id' => $event->id,
            'phone' => '9800000001',
        ]);

        $job = new SendBulkSMS(
            $regs->pluck('id')->toArray(),
            $event->id,
            'Individual SMS',
            null,
            1
        );
        $job->handle(app(CommunicationService::class));

        $this->assertDatabaseCount('communications', 3);
        $this->assertDatabaseHas('communications', ['type' => 'sms', 'status' => 'sent']);
    }

    public function test_bulk_sms_job_with_batch_size_gt_1_uses_batch(): void
    {
        Config::set('services.sparrow.driver', 'log');
        Config::set('services.sparrow.batch_size', 2);

        $event = Event::factory()->create();
        $regs = Registration::factory()->count(5)->create([
            'event_id' => $event->id,
            'phone' => '9800000001',
        ]);

        $job = new SendBulkSMS(
            $regs->pluck('id')->toArray(),
            $event->id,
            'Batch SMS',
            null,
            2
        );
        $job->handle(app(CommunicationService::class));

        $this->assertDatabaseCount('communications', 5);
        $this->assertDatabaseHas('communications', ['type' => 'sms', 'status' => 'sent']);
    }

    public function test_send_sms_skips_registrations_without_phone(): void
    {
        Config::set('services.sparrow.driver', 'log');

        $event = Event::factory()->create();
        $regWithPhone = Registration::factory()->create([
            'event_id' => $event->id,
            'phone' => '9800000001',
        ]);
        $regWithoutPhone = Registration::factory()->create([
            'event_id' => $event->id,
            'phone' => null,
        ]);

        $job = new SendBulkSMS(
            [$regWithPhone->id, $regWithoutPhone->id],
            $event->id,
            'Test SMS',
            null,
            1
        );
        $job->handle(app(CommunicationService::class));

        $this->assertDatabaseCount('communications', 1);
        $this->assertDatabaseHas('communications', [
            'registration_id' => $regWithPhone->id,
            'type' => 'sms',
        ]);
        $this->assertDatabaseMissing('communications', [
            'registration_id' => $regWithoutPhone->id,
        ]);
    }
}
