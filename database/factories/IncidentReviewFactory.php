<?php

namespace Database\Factories;

use App\Models\Incident;
use App\Models\IncidentReview;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IncidentReview>
 */
class IncidentReviewFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'incident_id' => Incident::factory(),
            'reviewed_by' => User::factory(),
            'fault_decision' => fake()->randomElement(IncidentReview::FAULT_DECISIONS),
            'notes' => fake()->paragraph(),
            'reviewed_at' => now(),
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
