<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Filament\Resources\UserResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_open_the_create_user_screen(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin->value]));

        $this->assertTrue(UserResource::canCreate());
        $this->get(UserResource::getUrl('create'))->assertSuccessful();
    }

    public function test_nobody_else_can_create_users(): void
    {
        foreach ([Role::EventAdmin, Role::RegistrationStaff, Role::ScannerStaff, Role::Finance, Role::Viewer] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role->value]));

            $this->assertFalse(UserResource::canCreate(), "{$role->value} must not create users");
            $this->get(UserResource::getUrl('create'))->assertForbidden();
        }
    }

    public function test_a_created_user_can_sign_in_with_the_password_set(): void
    {
        $password = UserResource::generatePassword();

        $user = User::create([
            'name' => 'Desk Two',
            'email' => 'desk2@ictfoundation.org.np',
            'password' => Hash::make($password),
            'role' => Role::RegistrationStaff->value,
        ]);

        $this->assertTrue(Hash::check($password, $user->fresh()->password));
        $this->assertSame(Role::RegistrationStaff, $user->roleEnum());
    }

    public function test_generated_passwords_are_ten_characters_and_unambiguous(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $password = UserResource::generatePassword();

            $this->assertSame(10, strlen($password));
            $this->assertDoesNotMatchRegularExpression('/[0O1lI]/', $password);
        }
    }
}
