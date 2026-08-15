<?php

namespace Tests\Unit\Models;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_has_default_scanner_role(): void
    {
        $user = User::factory()->create();
        $this->assertEquals('scanner_staff', $user->role);
    }

    public function test_user_role_check_methods(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $eventAdmin = User::factory()->create(['role' => 'event_admin']);
        $desk = User::factory()->create(['role' => 'registration_staff']);
        $scanner = User::factory()->create(['role' => 'scanner_staff']);
        $viewer = User::factory()->create(['role' => 'viewer']);

        $this->assertTrue($superAdmin->isSuperAdmin());
        $this->assertTrue($eventAdmin->isEventAdmin());
        $this->assertTrue($desk->isRegistrationStaff());
        $this->assertTrue($scanner->isScanner());
        $this->assertTrue($viewer->isViewer());
    }

    public function test_user_can_be_scoped_by_role(): void
    {
        User::factory()->count(2)->create(['role' => 'scanner_staff']);
        User::factory()->create(['role' => 'super_admin']);

        $this->assertCount(2, User::withRole('scanner_staff')->get());
    }
}
