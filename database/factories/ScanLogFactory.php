<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Registration;
use App\Models\ScanActionType;
use App\Models\ScanLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScanLog>
 */
class ScanLogFactory extends Factory
{
    protected $model = ScanLog::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'participant_id' => Registration::factory(),
            'action_type_id' => ScanActionType::factory(),
            'scanned_by' => User::factory(),
            'scan_device' => fake()->optional()->userAgent(),
            'scan_location' => fake()->optional()->city(),
            'remarks' => fake()->optional()->sentence(),
            'scanned_at' => now(),
        ];
    }
}
