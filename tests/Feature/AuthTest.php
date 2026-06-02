<?php

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_and_receive_token(): void
    {
        $user = User::factory()->create([
            'email' => 'scanner@test.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'scanner@test.com',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'user']);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create(['email' => 'test@test.com', 'password' => bcrypt('password')]);

        $this->postJson('/api/login', [
            'email' => 'test@test.com',
            'password' => 'wrong',
        ])->assertStatus(422);
    }

    public function test_authenticated_user_can_fetch_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('email', $user->email);
    }

    public function test_scanner_cannot_access_manager_endpoints(): void
    {
        $scanner = User::factory()->create(['role' => 'scanner']);

        $event = Event::factory()->create();

        $this->actingAs($scanner)
            ->postJson("/api/event/{$event->id}/import", [])
            ->assertForbidden();
    }

    public function test_manager_can_access_import_endpoint(): void
    {
        $manager = User::factory()->create(['role' => 'event_manager']);
        $event = Event::factory()->create();

        // Manager passes authorization but gets 422 validation (file required)
        // This proves the role middleware allows access; scanner would get 403
        $this->actingAs($manager)
            ->postJson("/api/event/{$event->id}/import", [])
            ->assertStatus(422);
    }
}
