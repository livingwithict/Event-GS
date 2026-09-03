<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Filament\Pages\ImportPreview;
use App\Filament\Pages\ScanStation;
use App\Filament\Pages\SendInvitations;
use App\Filament\Pages\Tracking;
use App\Filament\Resources\CommunicationResource;
use App\Filament\Resources\EventResource;
use App\Filament\Resources\ImportBatchResource;
use App\Filament\Resources\LabelTemplateResource;
use App\Filament\Resources\LogisticsResource;
use App\Filament\Resources\ParticipantCategoryResource;
use App\Filament\Resources\PaymentResource;
use App\Filament\Resources\PromoCodeResource;
use App\Filament\Resources\RegistrationResource;
use App\Filament\Resources\ScanActionTypeResource;
use App\Filament\Resources\SectorResource;
use App\Filament\Resources\UserResource;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
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

    #[DataProvider('roles')]
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

    #[DataProvider('roles')]
    public function test_each_role_can_load_every_screen_it_is_allowed(Role $role): void
    {
        $event = Event::factory()->create(['status' => 'published']);
        $user = User::factory()->create(['role' => $role->value]);

        if ($role->isEventScoped()) {
            $user->assignedEvents()->attach($event);
        }

        $screens = [
            EventResource::class,
            RegistrationResource::class,
            PaymentResource::class,
            CommunicationResource::class,
            ImportBatchResource::class,
            UserResource::class,
            ParticipantCategoryResource::class,
            SectorResource::class,
            LogisticsResource::class,
            LabelTemplateResource::class,
            PromoCodeResource::class,
            ScanActionTypeResource::class,
        ];

        $this->actingAs($user);

        foreach ($screens as $resource) {
            if (! $resource::canAccess()) {
                continue;
            }

            $this->get($resource::getUrl('index'))
                ->assertSuccessful();
        }

        foreach ([ScanStation::class, SendInvitations::class, ImportPreview::class, Tracking::class] as $page) {
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
