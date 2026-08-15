<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every role must be able to log in and land on a working dashboard. This is the
 * check that catches a stale helper call in a blade partial, which no unit test
 * of the role matrix would ever reach.
 */
class PanelAccessTest extends TestCase
{
    use RefreshDatabase;

    public static function roles(): array
    {
        return array_map(fn (Role $role) => [$role], Role::cases());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('roles')]
    public function test_each_role_can_load_the_dashboard(Role $role): void
    {
        $event = Event::factory()->create(['status' => 'published']);
        $user = User::factory()->create(['role' => $role->value]);

        if ($role->isEventScoped()) {
            $user->assignedEvents()->attach($event);
        }

        $this->actingAs($user)
            ->get('/admin')
            ->assertSuccessful();
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('roles')]
    public function test_each_role_can_load_every_screen_it_is_allowed(Role $role): void
    {
        $event = Event::factory()->create(['status' => 'published']);
        $user = User::factory()->create(['role' => $role->value]);

        if ($role->isEventScoped()) {
            $user->assignedEvents()->attach($event);
        }

        $screens = [
            \App\Filament\Resources\EventResource::class,
            \App\Filament\Resources\RegistrationResource::class,
            \App\Filament\Resources\PaymentResource::class,
            \App\Filament\Resources\CommunicationResource::class,
            \App\Filament\Resources\ImportBatchResource::class,
            \App\Filament\Resources\UserResource::class,
            \App\Filament\Resources\ParticipantCategoryResource::class,
            \App\Filament\Resources\LabelTemplateResource::class,
            \App\Filament\Resources\PromoCodeResource::class,
            \App\Filament\Resources\ScanActionTypeResource::class,
        ];

        $this->actingAs($user);

        foreach ($screens as $resource) {
            if (! $resource::canAccess()) {
                continue;
            }

            $this->get($resource::getUrl('index'))
                ->assertSuccessful();
        }

        foreach ([\App\Filament\Pages\ScanStation::class, \App\Filament\Pages\SendInvitations::class, \App\Filament\Pages\ImportPreview::class] as $page) {
            if (! $page::canAccess()) {
                continue;
            }

            $this->get($page::getUrl())->assertSuccessful();
        }
    }

    public function test_a_user_with_an_unrecognised_role_cannot_enter_the_panel(): void
    {
        $user = User::factory()->create(['role' => 'legacy_manager']);

        $this->actingAs($user)
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_the_login_page_loads_for_a_guest(): void
    {
        $this->get('/admin/login')->assertSuccessful();
    }
}
