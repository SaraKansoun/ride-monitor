<?php

namespace Database\Factories;

use App\Models\Driver;
use App\Models\DriverScore;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DriverScore>
 */
class DriverScoreFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'driver_id' => fn () => Driver::withoutEvents(fn () => Driver::factory()->create())->id,
            'score' => DriverScore::DEFAULT_SCORE,
            'total_incidents' => 0,
            'unsafe_events' => 0,
            'last_updated_at' => now(),
            'is_active' => true,
            'deactivated_at' => null,
            'deactivated_by' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
            'deactivated_at' => now(),
        ]);
    }
}
