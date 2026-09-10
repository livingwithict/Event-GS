<?php

namespace Tests\Feature;

use App\Filament\Pages\ImportPreview;
use App\Models\Event;
use App\Models\ImportStaging;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ImportPreviewSectorTest extends TestCase
{
    use RefreshDatabase;

    public function test_registering_a_staged_contact_attaches_sectors_named_in_the_csv(): void
    {
        $event = Event::factory()->create(['status' => 'published']);
        Sector::create(['event_id' => $event->id, 'name' => 'Hall A']);
        Sector::create(['event_id' => $event->id, 'name' => 'VIP']);

        $staging = ImportStaging::create([
            'event_id' => $event->id,
            'row_number' => 2,
            'name' => 'Guest One',
            'status' => 'pending',
            'raw_data' => ['name' => 'Guest One', 'sector' => 'Hall A, VIP'],
        ]);

        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        session(['active_event_id' => $event->id]);

        Livewire::test(ImportPreview::class)
            ->assertSeeText('Guest One')
            ->callTableAction('register', $staging);

        $registration = $staging->fresh()->registration;

        $this->assertNotNull($registration);
        $this->assertEqualsCanonicalizing(
            ['Hall A', 'VIP'],
            $registration->sectors->pluck('name')->all()
        );
    }
}
