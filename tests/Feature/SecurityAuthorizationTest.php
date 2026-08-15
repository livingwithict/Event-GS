<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_scanner_cannot_access_an_unassigned_event(): void
    {
        $scanner = User::factory()->create(['role' => 'scanner_staff']);
        $assignedEvent = Event::factory()->create();
        $otherEvent = Event::factory()->create();
        $registration = Registration::factory()->create(['event_id' => $otherEvent->id]);
        $scanner->assignedEvents()->attach($assignedEvent->id);

        $this->actingAs($scanner)
            ->postJson('/api/entry', ['registration_id' => $registration->id])
            ->assertForbidden();

        $this->actingAs($scanner)
            ->getJson("/api/guest/search?q={$registration->name}&event_id={$otherEvent->id}")
            ->assertForbidden();
    }

    public function test_manager_cannot_download_an_unassigned_event_report(): void
    {
        $manager = User::factory()->create(['role' => 'event_admin']);
        $assignedEvent = Event::factory()->create();
        $otherEvent = Event::factory()->create();
        $manager->assignedEvents()->attach($assignedEvent->id);

        $this->actingAs($manager)
            ->get("/reports/{$otherEvent->id}/pdf-summary")
            ->assertForbidden();
    }

    public function test_label_printing_requires_authentication(): void
    {
        $registration = Registration::factory()->create();

        $this->get("/labels/{$registration->id}/print")
            ->assertRedirect('/admin/login');
    }
}
