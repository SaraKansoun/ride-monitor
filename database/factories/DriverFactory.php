<?php

namespace Database\Factories;

use App\Models\Driver;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Driver>
 */
class DriverFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'license_number' => strtoupper(fake()->unique()->bothify('DRV-#####')),
            'phone' => fake()->phoneNumber(),
            'status' => Driver::STATUS_ACTIVE,
            'is_active' => true,
            'deactivated_at' => null,
            'deactivated_by' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Driver::STATUS_INACTIVE,
            'is_active' => false,
            'deactivated_at' => now(),
        ]);
    }
}
