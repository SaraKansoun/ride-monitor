<?php

namespace Database\Factories;

use App\Models\AIAnalysis;
use App\Models\Incident;
use App\Models\IncidentReview;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AIAnalysis>
 */
class AIAnalysisFactory extends Factory
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
            'media_fingerprint' => null,
            'summary' => null,
            'detected_events' => null,
            'confidence_score' => null,
            'recommendation' => null,
            'suggested_fault_decision' => null,
            'fault_confidence_score' => null,
            'fault_reasoning' => null,
            'raw_response' => null,
            'status' => AIAnalysis::STATUS_PROCESSING,
            'is_active' => true,
            'deactivated_at' => null,
            'deactivated_by' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'summary' => 'The unsafe driving report appears to include uploaded media metadata. Possible safety indicators may indicate follow-up review is needed. Manual review recommended.',
            'detected_events' => 'possible unsafe driving indicators, video metadata available for manual review',
            'confidence_score' => 0.67,
            'recommendation' => 'AI observations are advisory only. A monitor should review the incident details and uploaded media before making a final decision.',
            'suggested_fault_decision' => IncidentReview::FAULT_UNCLEAR,
            'fault_confidence_score' => 0.42,
            'fault_reasoning' => 'The available media appears to need human review before any fault decision is made.',
            'raw_response' => [
                'source' => 'openai_responses',
                'media' => [
                    ['file_type' => 'video', 'mime_type' => 'video/mp4'],
                ],
            ],
            'status' => AIAnalysis::STATUS_COMPLETED,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AIAnalysis::STATUS_INACTIVE,
            'is_active' => false,
            'deactivated_at' => now(),
        ]);
    }
}
