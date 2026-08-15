<?php

namespace Tests\Feature;

use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MealTest extends TestCase
{
    use RefreshDatabase;

    private User $scanner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scanner = User::factory()->create(['role' => 'scanner_staff']);
    }

    public function test_record_lunch_first_time(): void
    {
        $reg = Registration::factory()->create(['lunch_used_at' => null]);
        $this->scanner->assignedEvents()->attach($reg->event_id);

        $response = $this->actingAs($this->scanner)
            ->postJson('/api/meal', ['registration_id' => $reg->id, 'meal_type' => 'lunch']);

        $response->assertOk();
        $this->assertNotNull($reg->fresh()->lunch_used_at);
    }

    public function test_record_lunch_duplicate_returns_conflict(): void
    {
        $reg = Registration::factory()->create(['lunch_used_at' => now()]);
        $this->scanner->assignedEvents()->attach($reg->event_id);

        $response = $this->actingAs($this->scanner)
            ->postJson('/api/meal', ['registration_id' => $reg->id, 'meal_type' => 'lunch']);

        $response->assertStatus(409)
            ->assertJsonPath('message', 'Lunch already recorded for this guest.');
    }

    public function test_invalid_meal_type_returns_422(): void
    {
        $reg = Registration::factory()->create();
        $this->scanner->assignedEvents()->attach($reg->event_id);

        $response = $this->actingAs($this->scanner)
            ->postJson('/api/meal', ['registration_id' => $reg->id, 'meal_type' => 'breakfast']);

        $response->assertStatus(422);
    }
}
