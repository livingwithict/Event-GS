<?php

namespace Tests\Unit\Models;

use App\Models\Registration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_generates_uuid_and_qr_hash_on_create(): void
    {
        $reg = Registration::factory()->create();
        $this->assertNotNull($reg->unique_code);
        $this->assertNotEmpty($reg->qr_hash);
        $this->assertEquals(36, strlen($reg->unique_code));
    }

    public function test_registration_qr_hash_is_deterministic(): void
    {
        $reg = Registration::factory()->create();
        $expected = hash_hmac('sha256', $reg->unique_code, config('app.key'));
        $this->assertEquals($expected, $reg->qr_hash);
    }

    public function test_has_entered_attribute(): void
    {
        $entered = Registration::factory()->create(['entry_time' => now()]);
        $notEntered = Registration::factory()->create(['entry_time' => null]);
        $this->assertTrue($entered->hasEntered());
        $this->assertFalse($notEntered->hasEntered());
    }

    public function test_meal_used_methods(): void
    {
        $reg = Registration::factory()->create(['lunch_used_at' => now(), 'dinner_used_at' => null]);
        $this->assertTrue($reg->hasUsedMeal('lunch'));
        $this->assertFalse($reg->hasUsedMeal('dinner'));
    }

    public function test_record_entry_is_idempotent(): void
    {
        $reg = Registration::factory()->create(['entry_time' => null]);
        $first = $reg->recordEntry();
        $second = $reg->recordEntry();
        $this->assertTrue($first);
        $this->assertFalse($second);
        $this->assertNotNull($reg->fresh()->entry_time);
    }

    public function test_record_meal_is_idempotent(): void
    {
        $reg = Registration::factory()->create(['lunch_used_at' => null]);
        $first = $reg->recordMeal('lunch');
        $second = $reg->recordMeal('lunch');
        $this->assertTrue($first);
        $this->assertFalse($second);
        $this->assertNotNull($reg->fresh()->lunch_used_at);
    }
}
