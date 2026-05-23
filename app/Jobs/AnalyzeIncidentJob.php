<?php

namespace App\Jobs;

use App\Models\AIAnalysis;
use App\Models\Incident;
use App\Models\IncidentReview;
use App\Services\AIIncidentAnalysisService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class AnalyzeIncidentJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 180;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $aiAnalysisId) {}

    /**
     * Execute the job.
     */
    public function handle(AIIncidentAnalysisService $analysisService): void
    {
        $aiAnalysis = AIAnalysis::query()
            ->with([
                'incident.driver.user',
                'incident.media' => fn ($query) => $query->where('is_active', true)->latest(),
                'incident.vehicle',
            ])
            ->find($this->aiAnalysisId);

        if (! $aiAnalysis instanceof AIAnalysis || ! $aiAnalysis->isActive() || $aiAnalysis->isTerminal()) {
            return;
        }

        try {
            $aiAnalysis->update([
                'status' => AIAnalysis::STATUS_PROCESSING,
            ]);

            $incident = $aiAnalysis->incident;

            if (! $incident instanceof Incident) {
                throw new RuntimeException('The incident for this AI analysis could not be found.');
            }

            $result = $analysisService->analyze(
                $incident,
                function () use ($aiAnalysis): void {
                    $aiAnalysis->update([
                        'status' => AIAnalysis::STATUS_AI_ANALYZING,
                    ]);
                }
            );

            $aiAnalysis->update([
                'summary' => $result['summary'],
                'detected_events' => $result['detected_events'],
                'confidence_score' => $result['confidence_score'],
                'recommendation' => $result['recommendation'],
                'suggested_fault_decision' => $result['suggested_fault_decision'],
                'fault_confidence_score' => $result['fault_confidence_score'],
                'fault_reasoning' => $result['fault_reasoning'],
                'raw_response' => $result['raw_response'],
                'status' => AIAnalysis::STATUS_COMPLETED,
            ]);
        } catch (Throwable $exception) {
            $this->markFailed($aiAnalysis, $exception);
        }
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function failed(Throwable $exception): void
    {
        $aiAnalysis = AIAnalysis::query()->find($this->aiAnalysisId);

        if ($aiAnalysis instanceof AIAnalysis && $aiAnalysis->isActive()) {
            $this->markFailed($aiAnalysis, $exception);
        }
    }

    private function markFailed(AIAnalysis $aiAnalysis, Throwable $exception): void
    {
        $aiAnalysis->update([
            'summary' => 'AI analysis failed. Manual review recommended.',
            'detected_events' => null,
            'confidence_score' => null,
            'recommendation' => 'AI observations are unavailable. Manual review recommended.',
            'suggested_fault_decision' => IncidentReview::FAULT_UNCLEAR,
            'fault_confidence_score' => 0.0,
            'fault_reasoning' => 'AI analysis failed, so no reliable fault suggestion is available. Manual monitor review is required.',
            'raw_response' => [
                'source' => 'openai_responses',
                'error' => [
                    'type' => $exception::class,
                    'message' => Str::limit($exception->getMessage(), 500),
                ],
            ],
            'status' => AIAnalysis::STATUS_FAILED,
        ]);
    }
}
