<?php

namespace Database\Factories;

use App\Models\Driver;
use App\Models\DriverVehicle;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DriverVehicle>
 */
class DriverVehicleFactory extends Factory
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
            'vehicle_id' => Vehicle::factory(),
            'assigned_at' => now(),
            'unassigned_at' => null,
        ];
    }

    public function ended(): static
    {
        return $this->state(fn (array $attributes) => [
            'unassigned_at' => now(),
        ]);
    }
}
