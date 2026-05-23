<?php

namespace App\Services;

use App\Models\Incident;
use App\Models\IncidentReview;

class AIIncidentAnalysisService
{
    public function __construct(
        private LocalDashcamAnalysisService $localDashcamAnalysisService,
        private IncidentVisualInputBuilder $visualInputBuilder,
        private OpenAIIncidentAnalysisClient $openAIIncidentAnalysisClient
    ) {}

    /**
     * Analyze incident visual media without making the final human fault decision.
     *
     * @param  null|callable(): void  $markAiAnalyzing
     * @return array{
     *     summary: string,
     *     detected_events: string,
     *     confidence_score: float,
     *     recommendation: string,
     *     suggested_fault_decision: string,
     *     fault_confidence_score: float,
     *     fault_reasoning: string,
     *     raw_response: array<string, mixed>
     * }
     */
    public function analyze(Incident $incident, ?callable $markAiAnalyzing = null): array
    {
        $localResult = $this->localDashcamAnalysisService->analyze($incident);

        try {
            if (! $localResult->shouldUseOpenAI) {
                return [
                    'summary' => $localResult->summary,
                    'detected_events' => $localResult->detectedEventsText(),
                    'confidence_score' => $localResult->confidenceScore,
                    'recommendation' => 'Local AI observations are advisory only. A monitor should review the incident details and uploaded media before making a final decision.',
                    'suggested_fault_decision' => IncidentReview::FAULT_UNCLEAR,
                    'fault_confidence_score' => 0.0,
                    'fault_reasoning' => 'Local screening does not decide fault. The monitor should review the media and submit the final human decision.',
                    'raw_response' => [
                        'source' => 'local_dashcam_screening',
                        'openai' => [
                            'skipped' => true,
                            'reason' => 'local_screening_low_risk',
                        ],
                        'local_analysis' => $localResult->rawResponse,
                    ],
                ];
            }

            $visualInputs = $this->visualInputBuilder->build($incident, $localResult);

            if ($markAiAnalyzing !== null) {
                $markAiAnalyzing();
            }

            $result = $this->openAIIncidentAnalysisClient->analyze(
                $incident,
                $visualInputs['content_parts'],
                $visualInputs['media'],
                $localResult
            );

            $result['raw_response']['local_analysis'] = $localResult->rawResponse;

            return $result;
        } finally {
            $localResult->cleanup();
        }
    }
}
