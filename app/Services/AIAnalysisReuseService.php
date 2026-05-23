<?php

namespace App\Services;

use App\Models\AIAnalysis;

class AIAnalysisReuseService
{
    /**
     * @return array<string, mixed>
     */
    public function attributesFor(?string $fingerprint): array
    {
        $reusableAnalysis = $fingerprint === null
            ? null
            : AIAnalysis::query()
                ->active()
                ->where('status', AIAnalysis::STATUS_COMPLETED)
                ->where('media_fingerprint', $fingerprint)
                ->latest()
                ->first();

        if (! $reusableAnalysis instanceof AIAnalysis) {
            return [
                'media_fingerprint' => $fingerprint,
                'status' => AIAnalysis::STATUS_PROCESSING,
                'is_active' => true,
            ];
        }

        $rawResponse = json_decode((string) $reusableAnalysis->getRawOriginal('raw_response'), true);
        $rawResponse = is_array($rawResponse) ? $rawResponse : [];
        $rawResponse['reuse'] = [
            'reused_from_analysis_id' => $reusableAnalysis->id,
            'reason' => 'matching_media_fingerprint',
        ];

        return [
            'media_fingerprint' => $fingerprint,
            'summary' => $reusableAnalysis->summary,
            'detected_events' => $reusableAnalysis->detected_events,
            'confidence_score' => $reusableAnalysis->confidence_score,
            'recommendation' => $reusableAnalysis->recommendation,
            'suggested_fault_decision' => $reusableAnalysis->suggested_fault_decision,
            'fault_confidence_score' => $reusableAnalysis->fault_confidence_score,
            'fault_reasoning' => $reusableAnalysis->fault_reasoning,
            'raw_response' => $rawResponse,
            'status' => AIAnalysis::STATUS_COMPLETED,
            'is_active' => true,
        ];
    }
}
