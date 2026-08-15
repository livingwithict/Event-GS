<?php

namespace Tests\Feature;

use App\Enums\Ability;
use App\Enums\Role;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    private function user(Role $role): User
    {
        return User::factory()->create(['role' => $role->value]);
    }

    public function test_super_admin_can_do_everything(): void
    {
        $user = $this->user(Role::SuperAdmin);

        foreach (Ability::all() as $ability) {
            $this->assertTrue($user->hasAbility($ability), "super admin should have {$ability}");
        }
    }

    /** The reason Registration Staff exists: desk work without event administration. */
    public function test_registration_staff_runs_the_desk_but_cannot_administer(): void
    {
        $user = $this->user(Role::RegistrationStaff);

        foreach ([Ability::GuestsRegister, Ability::GuestsEdit, Ability::LabelsPrint, Ability::Scan] as $allowed) {
            $this->assertTrue($user->hasAbility($allowed), "registration staff should have {$allowed}");
        }

        foreach ([Ability::EventsManage, Ability::CommunicationsSend, Ability::ImportsManage, Ability::PaymentsView, Ability::UsersManage] as $denied) {
            $this->assertFalse($user->hasAbility($denied), "registration staff must not have {$denied}");
        }
    }

    /** Door staff see the guest they scanned and nothing else — no guest list. */
    public function test_scanner_staff_can_only_scan(): void
    {
        $user = $this->user(Role::ScannerStaff);

        $this->assertSame([Ability::Scan], $user->roleEnum()->abilities());
        $this->assertTrue($user->hasAbility(Ability::Scan));
        $this->assertFalse($user->hasAbility(Ability::GuestsView));
        $this->assertFalse($user->hasAbility(Ability::LabelsPrint));
    }

    public function test_only_roles_that_import_can_reach_the_import_screens(): void
    {
        foreach ([Role::SuperAdmin, Role::EventAdmin] as $role) {
            $this->assertTrue($this->user($role)->hasAbility(Ability::ImportsManage));
        }

        foreach ([Role::RegistrationStaff, Role::ScannerStaff, Role::Finance, Role::Viewer] as $role) {
            $this->assertFalse($this->user($role)->hasAbility(Ability::ImportsManage), "{$role->value} must not import guests");
        }
    }

    /** Settings-style resources (categories, labels, promo codes, scan actions). */
    public function test_only_administrators_configure_an_event(): void
    {
        $this->assertTrue($this->user(Role::SuperAdmin)->hasAbility(Ability::SettingsManage));
        $this->assertTrue($this->user(Role::EventAdmin)->hasAbility(Ability::SettingsManage));

        foreach ([Role::RegistrationStaff, Role::ScannerStaff, Role::Finance, Role::Viewer] as $role) {
            $this->assertFalse($this->user($role)->hasAbility(Ability::SettingsManage), "{$role->value} must not configure the event");
        }
    }

    public function test_viewer_sees_no_money_and_changes_nothing(): void
    {
        $user = $this->user(Role::Viewer);

        $this->assertTrue($user->hasAbility(Ability::GuestsView));
        $this->assertTrue($user->hasAbility(Ability::ReportsView));
        $this->assertFalse($user->hasAbility(Ability::PaymentsView));
        $this->assertFalse($user->hasAbility(Ability::GuestsEdit));
        $this->assertFalse($user->hasAbility(Ability::Scan));
    }

    public function test_finance_sees_payments_but_cannot_edit_guests(): void
    {
        $user = $this->user(Role::Finance);

        $this->assertTrue($user->hasAbility(Ability::PaymentsManage));
        $this->assertFalse($user->hasAbility(Ability::GuestsEdit));
        $this->assertFalse($user->hasAbility(Ability::Scan));
    }

    public function test_only_super_admin_manages_users(): void
    {
        $this->assertTrue($this->user(Role::SuperAdmin)->hasAbility(Ability::UsersManage));

        foreach ([Role::EventAdmin, Role::RegistrationStaff, Role::ScannerStaff, Role::Finance, Role::Viewer] as $role) {
            $this->assertFalse($this->user($role)->hasAbility(Ability::UsersManage), "{$role->value} must not manage users");
        }
    }

    public function test_every_role_except_super_admin_is_limited_to_assigned_events(): void
    {
        $mine = Event::factory()->create();
        $theirs = Event::factory()->create();

        $superAdmin = $this->user(Role::SuperAdmin);
        $this->assertTrue($superAdmin->canAccessEvent($mine));
        $this->assertTrue($superAdmin->canAccessEvent($theirs));

        foreach ([Role::EventAdmin, Role::RegistrationStaff, Role::ScannerStaff, Role::Finance, Role::Viewer] as $role) {
            $user = $this->user($role);
            $user->assignedEvents()->attach($mine);

            $this->assertTrue($user->canAccessEvent($mine), "{$role->value} should reach an assigned event");
            $this->assertFalse($user->canAccessEvent($theirs), "{$role->value} must not reach an unassigned event");
        }
    }

    public function test_gates_are_registered_for_every_ability(): void
    {
        $this->actingAs($this->user(Role::RegistrationStaff));

        $this->assertTrue(auth()->user()->can(Ability::GuestsRegister));
        $this->assertFalse(auth()->user()->can(Ability::UsersManage));
    }

    public function test_an_unknown_role_gets_nothing(): void
    {
        $user = User::factory()->create(['role' => 'legacy_manager']);

        $this->assertNull($user->roleEnum());
        $this->assertFalse($user->hasAbility(Ability::GuestsView));
        $this->assertFalse($user->canAccessEvent(Event::factory()->create()));
    }
}
