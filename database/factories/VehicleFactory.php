<?php

namespace Database\Factories;

use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plate_number' => strtoupper(fake()->unique()->bothify('###-???')),
            'model' => fake()->randomElement(['Toyota Prius', 'Ford Transit', 'Hyundai Sonata', 'Nissan Altima']),
            'year' => fake()->numberBetween(2015, (int) now()->year),
            'status' => Vehicle::STATUS_ACTIVE,
            'is_active' => true,
            'deactivated_at' => null,
            'deactivated_by' => null,
        ];
    }

    public function retired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Vehicle::STATUS_RETIRED,
            'is_active' => false,
            'deactivated_at' => now(),
        ]);
    }
}
