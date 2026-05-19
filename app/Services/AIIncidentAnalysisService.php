<?php

namespace App\Services;

use App\Models\Incident;

class AIIncidentAnalysisService
{
    public function __construct(
        private IncidentVisualInputBuilder $visualInputBuilder,
        private OpenAIIncidentAnalysisClient $openAIIncidentAnalysisClient
    ) {}

    /**
     * Analyze incident visual media without deciding fault.
     *
     * @return array{
     *     summary: string,
     *     detected_events: string,
     *     confidence_score: float,
     *     recommendation: string,
     *     raw_response: array<string, mixed>
     * }
     */
    public function analyze(Incident $incident): array
    {
        $visualInputs = $this->visualInputBuilder->build($incident);

        return $this->openAIIncidentAnalysisClient->analyze(
            $incident,
            $visualInputs['content_parts'],
            $visualInputs['media']
        );
    }
}
