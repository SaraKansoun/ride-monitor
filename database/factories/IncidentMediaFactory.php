<?php

namespace Database\Factories;

use App\Models\Incident;
use App\Models\IncidentMedia;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IncidentMedia>
 */
class IncidentMediaFactory extends Factory
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
            'file_path' => 'incident-media/'.fake()->uuid().'.jpg',
            'original_name' => fake()->word().'.jpg',
            'file_type' => IncidentMedia::TYPE_IMAGE,
            'mime_type' => 'image/jpeg',
            'size' => fake()->numberBetween(50_000, 2_000_000),
            'uploaded_by' => User::factory(),
            'is_active' => true,
            'deactivated_at' => null,
            'deactivated_by' => null,
        ];
    }
}
