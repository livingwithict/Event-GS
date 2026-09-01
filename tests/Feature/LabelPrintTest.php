<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LabelPrintTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'super_admin']);
    }

    public function test_print_now_page_loads_the_sheet_for_the_selected_registrations(): void
    {
        $event = Event::factory()->create();
        $regs = Registration::factory()->count(3)->create(['event_id' => $event->id]);

        $this->actingAs($this->admin())
            ->get(route('labels.print-now', ['registrations' => $regs->pluck('id')->implode(',')]))
            ->assertOk()
            ->assertSee('3 labels')
            ->assertSee('/labels/sheet?registrations=', false);
    }

    public function test_sheet_is_inline_and_marks_labels_printed(): void
    {
        $registration = Registration::factory()->create(['label_printed' => false]);

        $this->actingAs($this->admin())
            ->get(route('labels.sheet', ['registrations' => $registration->id]))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8');

        $this->assertTrue($registration->fresh()->label_printed);
    }

    public function test_registrations_from_two_events_are_rejected(): void
    {
        $a = Registration::factory()->create();
        $b = Registration::factory()->create();

        $this->actingAs($this->admin())
            ->get(route('labels.print-now', ['registrations' => $a->id.','.$b->id]))
            ->assertStatus(422);
    }

    public function test_empty_selection_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->get(route('labels.print-now', ['registrations' => '']))
            ->assertStatus(400);
    }
}
