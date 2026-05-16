<?php

namespace Database\Factories;

use App\Models\Driver;
use App\Models\Incident;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Incident>
 */
class IncidentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'driver_id' => Driver::factory(),
            'vehicle_id' => null,
            'type' => fake()->randomElement(Incident::TYPES),
            'description' => fake()->paragraph(),
            'status' => Incident::STATUS_PENDING,
            'reported_by' => User::factory(),
            'is_active' => true,
            'deactivated_at' => null,
            'deactivated_by' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Incident::STATUS_INACTIVE,
            'is_active' => false,
            'deactivated_at' => now(),
        ]);
    }
}
