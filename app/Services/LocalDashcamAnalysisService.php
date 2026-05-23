<?php

namespace App\Services;

use App\Models\Incident;
use App\Models\IncidentMedia;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class LocalDashcamAnalysisService
{
    /**
     * Run local OpenCV/YOLO dashcam screening before spending OpenAI tokens.
     */
    public function analyze(Incident $incident): DashcamAnalysisResult
    {
        if (! (bool) config('services.dashcam.local_analysis_enabled', true)) {
            return DashcamAnalysisResult::openAIOnly('AI_LOCAL_ANALYSIS_ENABLED is disabled.');
        }

        $media = $this->visualMedia($incident);

        if (! $media instanceof IncidentMedia) {
            throw new RuntimeException('No active dashcam image or video media is available for local analysis.');
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($media->file_path)) {
            throw new RuntimeException('The dashcam media file could not be found for local analysis.');
        }

        $outputDirectory = storage_path('app/ai-analysis/local-frames/'.$media->id.'-'.Str::uuid());
        File::ensureDirectoryExists($outputDirectory);

        $process = new Process([
            (string) config('services.dashcam.python_binary', 'python'),
            (string) config('services.dashcam.script_path', resource_path('ai/dashcam_analyzer.py')),
            '--input',
            $disk->path($media->file_path),
            '--output-dir',
            $outputDirectory,
            '--max-frames',
            (string) max(1, (int) config('services.openai.frame_count', 3)),
            '--model',
            (string) config('services.dashcam.model_path', storage_path('app/ai-models/yolo11n.pt')),
            '--media-type',
            $media->file_type,
        ]);
        $process->setTimeout((int) config('services.dashcam.timeout', 120));

        try {
            $process->run();
        } catch (Throwable $exception) {
            File::deleteDirectory($outputDirectory);

            throw new RuntimeException('Unable to start local dashcam analyzer. Confirm YOLO_PYTHON_BINARY is configured.', 0, $exception);
        }

        if (! $process->isSuccessful()) {
            $error = trim($process->getErrorOutput()) ?: trim($process->getOutput()) ?: 'local analyzer failed.';
            File::deleteDirectory($outputDirectory);

            throw new RuntimeException('Local dashcam analyzer failed: '.Str::limit($error, 500));
        }

        $rawResponse = json_decode(trim($process->getOutput()), true);

        if (! is_array($rawResponse)) {
            File::deleteDirectory($outputDirectory);

            throw new RuntimeException('Local dashcam analyzer did not return valid JSON.');
        }

        $riskScore = $this->score($rawResponse['risk_score'] ?? null);
        $confidenceScore = $this->score($rawResponse['confidence_score'] ?? null);
        $selectedFrames = $this->selectedFrames($rawResponse, $outputDirectory);
        $shouldUseOpenAI = $this->shouldUseOpenAI($riskScore, $confidenceScore);

        if ($selectedFrames === []) {
            File::deleteDirectory($outputDirectory);
        }

        $rawResponse['media_id'] = $media->id;
        $rawResponse['openai_escalation'] = $shouldUseOpenAI ? 'selected_frames' : 'skipped_low_risk';

        return new DashcamAnalysisResult(
            localAnalysisEnabled: true,
            shouldUseOpenAI: $shouldUseOpenAI,
            mediaId: $media->id,
            riskScore: $riskScore,
            confidenceScore: $confidenceScore,
            summary: $this->summary($rawResponse),
            detectedEvents: $this->detectedEvents($rawResponse),
            selectedFrames: $selectedFrames,
            temporaryDirectories: $selectedFrames !== [] ? [$outputDirectory] : [],
            rawResponse: $rawResponse,
        );
    }

    private function visualMedia(Incident $incident): ?IncidentMedia
    {
        $incident->loadMissing('media');

        return $incident->media
            ->filter(fn (IncidentMedia $media): bool => $media->isActive()
                && in_array($media->file_type, [IncidentMedia::TYPE_VIDEO, IncidentMedia::TYPE_IMAGE], true))
            ->sortBy(fn (IncidentMedia $media): int => $media->file_type === IncidentMedia::TYPE_VIDEO ? 0 : 1)
            ->first();
    }

    private function score(mixed $value): float
    {
        return round(max(0, min(1, is_numeric($value) ? (float) $value : 0)), 2);
    }

    /**
     * @return list<array{path: string, timestamp_seconds?: float|int|null, score?: float|int|null, reasons?: list<string>}>
     */
    private function selectedFrames(array $rawResponse, string $outputDirectory): array
    {
        $frames = $rawResponse['selected_frames'] ?? [];

        if (! is_array($frames)) {
            return [];
        }

        return collect($frames)
            ->filter(fn (mixed $frame): bool => is_array($frame) && is_string($frame['path'] ?? null))
            ->map(function (array $frame) use ($outputDirectory): ?array {
                $path = $frame['path'];

                if (! str_starts_with($path, $outputDirectory)) {
                    $path = $outputDirectory.DIRECTORY_SEPARATOR.basename($path);
                }

                if (! is_file($path)) {
                    return null;
                }

                return [
                    'path' => $path,
                    'timestamp_seconds' => is_numeric($frame['timestamp_seconds'] ?? null) ? (float) $frame['timestamp_seconds'] : null,
                    'score' => is_numeric($frame['score'] ?? null) ? (float) $frame['score'] : null,
                    'reasons' => collect($frame['reasons'] ?? [])
                        ->filter(fn (mixed $reason): bool => is_string($reason) && trim($reason) !== '')
                        ->values()
                        ->all(),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function shouldUseOpenAI(float $riskScore, float $confidenceScore): bool
    {
        $mode = (string) config('services.dashcam.openai_escalation_mode', 'strict');

        if ($mode === 'off') {
            return false;
        }

        if ($mode === 'always') {
            return true;
        }

        return $riskScore >= (float) config('services.dashcam.openai_risk_threshold', 0.65)
            || $confidenceScore < (float) config('services.dashcam.local_confidence_threshold', 0.55);
    }

    private function summary(array $rawResponse): string
    {
        $summary = $rawResponse['summary'] ?? null;

        if (is_string($summary) && trim($summary) !== '') {
            return Str::of($summary)->squish()->toString();
        }

        return 'Local dashcam screening appears to have completed. Manual review recommended.';
    }

    /**
     * @return list<string>
     */
    private function detectedEvents(array $rawResponse): array
    {
        $events = $rawResponse['detected_events'] ?? [];

        if (is_string($events)) {
            return collect(explode(',', $events))
                ->map(fn (string $event): string => trim($event))
                ->filter()
                ->values()
                ->all();
        }

        if (! is_array($events)) {
            return [];
        }

        return collect($events)
            ->filter(fn (mixed $event): bool => is_string($event) && trim($event) !== '')
            ->map(fn (string $event): string => Str::of($event)->squish()->toString())
            ->values()
            ->all();
    }
}
