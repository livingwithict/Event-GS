<?php

namespace Tests\Feature;

use App\Filament\Resources\LogisticsResource\Pages\ListLogistics;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Filament's column manager saves visibility choices to the session by
 * default, which resets on session expiry or a different browser/device.
 * PersistsColumnManagerPerUser redirects that to the user's own record instead.
 */
class ColumnManagerPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_toggling_a_column_persists_to_the_users_record_and_survives_a_fresh_page_load(): void
    {
        $user = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($user);

        $component = Livewire::test(ListLogistics::class);

        $state = $component->instance()->getDefaultTableColumnState();
        $toggleableIndex = collect($state)->search(fn (array $column) => $column['isToggleable']);
        $this->assertNotFalse($toggleableIndex, 'Expected at least one toggleable column on the Logistics table.');

        $state[$toggleableIndex]['isToggled'] = ! $state[$toggleableIndex]['isToggled'];

        $component->call('applyTableColumnManager', $state);

        $this->assertNotEmpty($user->fresh()->column_preferences);

        // A brand new component instance simulates a fresh page load / new session —
        // it should pick up the saved choice, not fall back to the defaults.
        $reloaded = Livewire::test(ListLogistics::class);

        $this->assertSame($state[$toggleableIndex]['isToggled'], $reloaded->get('tableColumns')[$toggleableIndex]['isToggled']);
    }
}
