<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\ParticipantCategory;
use App\Models\Registration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationQrPrintTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_qr_print_download_contains_guest_filename(): void
    {
        $registration = Registration::factory()->create(['name' => 'Printable Guest']);

        $response = $this->get(route('ticket.qr-print', $registration->qr_hash));

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'attachment; filename="'.$registration->guest_number.'-qr-6x8.pdf"');
    }

    public function test_ticket_download_renders_without_image_extension(): void
    {
        $registration = Registration::factory()->create(['name' => 'Ticket Guest']);

        $this->get(route('ticket.download', $registration->qr_hash))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_free_public_registration_success_page_shows_qr_name_and_guest_number(): void
    {
        $event = Event::factory()->create([
            'slug' => 'qr-registration-event',
            'settings' => ['enable_self_registration' => true, 'enable_payment' => false],
        ]);
        ParticipantCategory::factory()->create([
            'event_id' => $event->id,
            'is_paid' => false,
            'price' => 0,
        ]);

        $response = $this->post("/event/{$event->slug}/register", [
            'name' => 'Public QR Guest',
            'email' => 'public-qr@example.com',
            'phone' => '+9779800000001',
            'category_id' => ParticipantCategory::where('event_id', $event->id)->value('id'),
            'consent' => '1',
        ]);

        $response->assertRedirect(route('register.success', $event->slug));
        $success = $this->get(route('register.success', $event->slug));
        $registration = Registration::where('email', 'public-qr@example.com')->firstOrFail();

        $success->assertOk()
            ->assertSee('Public QR Guest')
            ->assertSee($registration->guest_number)
            ->assertSee('<svg', false)
            ->assertSee(route('ticket.qr-print', $registration->qr_hash), false);
    }
}
