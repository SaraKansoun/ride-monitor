<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\DriverScore;
use App\Models\Incident;
use App\Models\IncidentReview;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;

class DriverScoreService
{
    public function ensureDefaultScore(Driver $driver): DriverScore
    {
        return DriverScore::query()->firstOrCreate(
            ['driver_id' => $driver->id],
            [
                'score' => DriverScore::DEFAULT_SCORE,
                'total_incidents' => 0,
                'unsafe_events' => 0,
                'last_updated_at' => now(),
                'is_active' => true,
            ],
        );
    }

    public function recalculateForIncident(Incident $incident): ?DriverScore
    {
        $driver = $incident->driver;

        if (! $driver instanceof Driver) {
            return null;
        }

        return $this->recalculateForDriver($driver);
    }

    public function recalculateForDriver(Driver $driver): DriverScore
    {
        return DB::transaction(function () use ($driver): DriverScore {
            $score = $this->ensureDefaultScore($driver);

            /** @var EloquentCollection<int, IncidentReview> $reviews */
            $reviews = IncidentReview::query()
                ->active()
                ->whereHas('incident', fn (Builder $query): Builder => $query
                    ->where('driver_id', $driver->id)
                    ->where('is_active', true)
                    ->where('status', Incident::STATUS_RESOLVED))
                ->with('incident')
                ->get();

            $totalPenalty = $reviews->sum(fn (IncidentReview $review): int => $this->penaltyForReview($review));
            $totalIncidents = $reviews->count();
            $unsafeEvents = $reviews
                ->filter(function (IncidentReview $review): bool {
                    $incident = $review->incident;

                    return $incident instanceof Incident
                        && $incident->type === Incident::TYPE_UNSAFE_DRIVING;
                })
                ->count();

            $score->update([
                'score' => max(
                    DriverScore::MIN_SCORE,
                    min(DriverScore::MAX_SCORE, DriverScore::DEFAULT_SCORE - $totalPenalty),
                ),
                'total_incidents' => $totalIncidents,
                'unsafe_events' => $unsafeEvents,
                'last_updated_at' => now(),
            ]);

            return $score->refresh();
        });
    }

    /**
     * @return list<array{label: string, value: int}>
     */
    public function scoreTrendForDriver(Driver $driver): array
    {
        /** @var EloquentCollection<int, IncidentReview> $reviews */
        $reviews = IncidentReview::query()
            ->active()
            ->whereHas('incident', fn (Builder $query): Builder => $query
                ->where('driver_id', $driver->id)
                ->where('is_active', true)
                ->where('status', Incident::STATUS_RESOLVED))
            ->with('incident')
            ->orderBy('reviewed_at')
            ->orderBy('id')
            ->get();

        $score = DriverScore::DEFAULT_SCORE;
        $points = [[
            'label' => 'Start',
            'value' => $score,
        ]];

        foreach ($reviews as $review) {
            $score = max(
                DriverScore::MIN_SCORE,
                min(DriverScore::MAX_SCORE, $score - $this->penaltyForReview($review)),
            );
            $reviewedAt = $review->getAttribute('reviewed_at');

            $points[] = [
                'label' => $reviewedAt instanceof CarbonInterface ? $reviewedAt->format('M j') : 'Reviewed',
                'value' => $score,
            ];
        }

        return $points;
    }

    private function penaltyForReview(IncidentReview $review): int
    {
        $incident = $review->incident;

        if (! $incident instanceof Incident) {
            return 0;
        }

        $penalty = match (true) {
            $review->fault_decision === IncidentReview::FAULT_DRIVER
                && $incident->type === Incident::TYPE_CRASH => 20,
            $review->fault_decision === IncidentReview::FAULT_SHARED
                && $incident->type === Incident::TYPE_CRASH => 10,
            default => 0,
        };

        if ($incident->type === Incident::TYPE_UNSAFE_DRIVING) {
            $penalty += 10;
        }

        if ($incident->type === Incident::TYPE_COMPLAINT) {
            $penalty += 5;
        }

        return $penalty;
    }
}
