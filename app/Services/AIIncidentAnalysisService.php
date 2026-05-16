<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\Incident;
use App\Models\IncidentMedia;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AIIncidentAnalysisService
{
    /**
     * Analyze incident and media metadata without deciding fault.
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
        /** @var EloquentCollection<int, IncidentMedia> $media */
        $media = $incident->media;
        /** @var Collection<int, string> $mediaTypes */
        $mediaTypes = $media->pluck('file_type')->unique()->values();
        $detectedEvents = $this->detectedEvents($incident, $mediaTypes);
        $confidenceScore = $this->confidenceScore($incident, $mediaTypes);
        $incidentType = str_replace('_', ' ', $incident->type);
        $driver = $incident->driver instanceof Driver ? $incident->driver : null;
        $driverUser = $driver?->user instanceof User ? $driver->user : null;
        $vehicle = $incident->vehicle instanceof Vehicle ? $incident->vehicle : null;

        return [
            'summary' => sprintf(
                'The %s report appears to include %d uploaded media item%s. The available metadata may indicate %s. Manual review recommended.',
                $incidentType,
                $media->count(),
                $media->count() === 1 ? '' : 's',
                Str::lower($detectedEvents),
            ),
            'detected_events' => $detectedEvents,
            'confidence_score' => $confidenceScore,
            'recommendation' => 'AI observations are advisory only. A monitor should review the incident details and uploaded media before making a final decision.',
            'raw_response' => [
                'source' => 'local_metadata',
                'incident' => [
                    'id' => $incident->id,
                    'type' => $incident->type,
                    'status' => $incident->status,
                    'description_length' => mb_strlen($incident->description),
                ],
                'driver' => [
                    'id' => $driver?->id,
                    'name' => $driverUser?->name,
                ],
                'vehicle' => [
                    'id' => $vehicle?->id,
                    'plate_number' => $vehicle?->plate_number,
                ],
                'media' => $media->map(fn (IncidentMedia $item) => [
                    'id' => $item->id,
                    'file_type' => $item->file_type,
                    'mime_type' => $item->mime_type,
                    'size' => $item->size,
                    'original_name' => $item->original_name,
                ])->values()->all(),
            ],
        ];
    }

    /**
     * @param  Collection<int, string>  $mediaTypes
     */
    private function detectedEvents(Incident $incident, Collection $mediaTypes): string
    {
        $events = match ($incident->type) {
            Incident::TYPE_CRASH => ['possible crash impact context'],
            Incident::TYPE_UNSAFE_DRIVING => ['possible unsafe driving indicators'],
            Incident::TYPE_COMPLAINT => ['possible complaint-related safety concern'],
            Incident::TYPE_NEAR_MISS => ['possible near miss context'],
            default => ['possible safety concern'],
        };

        $description = Str::lower($incident->description);

        if (Str::contains($description, ['brak', 'stop'])) {
            $events[] = 'possible sudden braking';
        }

        if (Str::contains($description, ['following', 'tailgat', 'distance'])) {
            $events[] = 'short following distance may indicate elevated risk';
        }

        if ($mediaTypes->contains('video')) {
            $events[] = 'video metadata available for manual review';
        }

        if ($mediaTypes->contains('image')) {
            $events[] = 'image metadata available for manual review';
        }

        if ($mediaTypes->contains('document')) {
            $events[] = 'document metadata available for manual review';
        }

        return implode(', ', array_unique($events));
    }

    /**
     * @param  Collection<int, string>  $mediaTypes
     */
    private function confidenceScore(Incident $incident, Collection $mediaTypes): float
    {
        $score = 0.55;

        if ($mediaTypes->contains('video')) {
            $score += 0.12;
        }

        if ($mediaTypes->contains('image')) {
            $score += 0.07;
        }

        if ($mediaTypes->contains('document')) {
            $score += 0.03;
        }

        if (mb_strlen($incident->description) > 120) {
            $score += 0.05;
        }

        return round(min($score, 0.82), 2);
    }
}
