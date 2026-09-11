<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Filament\Resources\RegistrationResource;
use App\Filament\Resources\RegistrationResource\Pages\ListRegistrations;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RegistrationCreateAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_invitation_staff_do_not_see_or_reach_the_create_action(): void
    {
        $event = Event::factory()->create();
        $user = User::factory()->create(['role' => Role::InvitationStaff->value]);
        $user->assignedEvents()->attach($event);
        session(['active_event_id' => $event->id]);

        $this->actingAs($user);

        $this->assertFalse(RegistrationResource::canCreate());

        Livewire::test(ListRegistrations::class)->assertActionHidden('create');
    }

    public function test_registration_staff_can_still_reach_the_create_action(): void
    {
        $event = Event::factory()->create();
        $user = User::factory()->create(['role' => Role::RegistrationStaff->value]);
        $user->assignedEvents()->attach($event);
        session(['active_event_id' => $event->id]);

        $this->actingAs($user);

        $this->assertTrue(RegistrationResource::canCreate());

        Livewire::test(ListRegistrations::class)->assertActionVisible('create');
    }
}
