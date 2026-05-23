<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

class DashcamAnalysisResult
{
    /**
     * @param  list<string>  $detectedEvents
     * @param  list<array{path: string, timestamp_seconds?: float|int|null, score?: float|int|null, reasons?: list<string>}>  $selectedFrames
     * @param  list<string>  $temporaryDirectories
     * @param  array<string, mixed>  $rawResponse
     */
    public function __construct(
        public readonly bool $localAnalysisEnabled,
        public readonly bool $shouldUseOpenAI,
        public readonly ?int $mediaId,
        public readonly float $riskScore,
        public readonly float $confidenceScore,
        public readonly string $summary,
        public readonly array $detectedEvents,
        public readonly array $selectedFrames,
        public readonly array $temporaryDirectories,
        public readonly array $rawResponse,
    ) {}

    public static function openAIOnly(string $reason): self
    {
        return new self(
            localAnalysisEnabled: false,
            shouldUseOpenAI: true,
            mediaId: null,
            riskScore: 1,
            confidenceScore: 0,
            summary: 'Local dashcam analysis is disabled. OpenAI visual review is required.',
            detectedEvents: ['local analysis disabled'],
            selectedFrames: [],
            temporaryDirectories: [],
            rawResponse: [
                'source' => 'local_dashcam_screening',
                'status' => 'skipped',
                'reason' => $reason,
            ],
        );
    }

    public function detectedEventsText(): string
    {
        return $this->detectedEvents === []
            ? 'No high-risk local indicators detected'
            : implode(', ', $this->detectedEvents);
    }

    public function cleanup(): void
    {
        foreach ($this->temporaryDirectories as $directory) {
            File::deleteDirectory($directory);
        }
    }
}
