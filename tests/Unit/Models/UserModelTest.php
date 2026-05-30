<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_has_default_scanner_role(): void
    {
        $user = User::factory()->create();
        $this->assertEquals('scanner', $user->role);
    }

    public function test_user_role_check_methods(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $manager = User::factory()->create(['role' => 'event_manager']);
        $scanner = User::factory()->create(['role' => 'scanner']);
        $viewer = User::factory()->create(['role' => 'viewer']);

        $this->assertTrue($admin->isSuperAdmin());
        $this->assertTrue($manager->isEventManager());
        $this->assertTrue($scanner->isScanner());
        $this->assertTrue($viewer->isViewer());
    }

    public function test_user_can_be_scoped_by_role(): void
    {
        User::factory()->count(2)->create(['role' => 'scanner']);
        User::factory()->create(['role' => 'super_admin']);

        $this->assertCount(2, User::withRole('scanner')->get());
    }
}
