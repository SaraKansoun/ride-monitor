<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use App\Models\DriverScore;
use App\Models\Incident;
use App\Models\IncidentReview;
use App\Models\User;
use App\Services\CsvExportService;
use App\Services\DeactivationService;
use App\Services\DriverScoreService;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DriverScoreController extends Controller
{
    public function __construct(
        private CsvExportService $csvExportService,
        private DeactivationService $deactivationService,
        private DriverScoreService $driverScoreService
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', DriverScore::class);

        $status = $this->statusFilter($request);
        $scoreBand = $this->scoreBandFilter($request);
        $search = $this->searchTerm($request);
        $drivers = $this->filteredDrivers($status, $scoreBand, $search)
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $drivers->getCollection()->each(function (Driver $driver): void {
            $driver->setRelation('score', $this->driverScoreService->ensureDefaultScore($driver));
        });

        return view('safety-scores.index', [
            'drivers' => $drivers,
            'scoreBand' => $scoreBand,
            'search' => $search,
            'status' => $status,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('viewAny', DriverScore::class);

        $status = $this->statusFilter($request);
        $scoreBand = $this->scoreBandFilter($request);
        $search = $this->searchTerm($request);
        $drivers = $this->filteredDrivers($status, $scoreBand, $search)
            ->latest()
            ->get();

        $drivers->each(function (Driver $driver): void {
            $driver->setRelation('score', $this->driverScoreService->ensureDefaultScore($driver));
        });

        return $this->csvExportService->download(
            'safety-scores.csv',
            ['Driver', 'Driver Status', 'Total Incidents', 'Unsafe Events', 'Current Score', 'Score Status', 'Last Updated'],
            $this->csvExportService->rows($drivers, function (Driver $driver): array {
                $score = $driver->score;
                $lastUpdated = $score instanceof DriverScore ? $score->getAttribute('last_updated_at') : null;

                return [
                    data_get($driver, 'user.name', 'Unassigned user'),
                    $driver->status,
                    $score instanceof DriverScore ? $score->total_incidents : 0,
                    $score instanceof DriverScore ? $score->unsafe_events : 0,
                    $score instanceof DriverScore ? $score->score : DriverScore::DEFAULT_SCORE,
                    $score instanceof DriverScore && $score->is_active ? 'active' : 'inactive',
                    $lastUpdated instanceof CarbonInterface ? $lastUpdated->format('Y-m-d H:i') : 'Not updated',
                ];
            })
        );
    }

    public function deactivate(Request $request, DriverScore $driverScore): RedirectResponse
    {
        Gate::authorize('deactivate', $driverScore);

        $user = $request->user();

        abort_unless($user instanceof User, 403);

        $this->deactivationService->deactivateDriverScore($driverScore, $user);

        return back()->with('status', 'Driver score deactivated.');
    }

    public function reactivate(DriverScore $driverScore): RedirectResponse
    {
        Gate::authorize('reactivate', $driverScore);

        $this->deactivationService->reactivateDriverScore($driverScore);

        return back()->with('status', 'Driver score reactivated.');
    }

    public function performance(Request $request): View
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        $driver = $user->driverProfile()
            ->with(['currentAssignments.vehicle', 'user'])
            ->first();

        abort_unless($driver instanceof Driver, 404);

        $score = $this->driverScoreService->ensureDefaultScore($driver);
        $scoreValue = (int) $score->score;
        $pendingIncidents = $this->activeIncidentCount($driver, Incident::STATUS_PENDING);
        $underReviewIncidents = $this->activeIncidentCount($driver, Incident::STATUS_UNDER_REVIEW);
        $resolvedIncidents = $this->activeIncidentCount($driver, Incident::STATUS_RESOLVED);

        Gate::authorize('view', $score);

        return view('driver-performance.show', [
            'driver' => $driver,
            'incidentStatusCounts' => [
                Incident::STATUS_PENDING => $pendingIncidents,
                Incident::STATUS_UNDER_REVIEW => $underReviewIncidents,
                Incident::STATUS_RESOLVED => $resolvedIncidents,
            ],
            'incidentTypeCounts' => $this->activeIncidentCountsBy($driver, 'type', Incident::TYPES),
            'latestIncident' => Incident::query()
                ->where('driver_id', $driver->id)
                ->active()
                ->with('vehicle')
                ->latest()
                ->first(),
            'latestReviews' => IncidentReview::query()
                ->active()
                ->whereHas('incident', fn (Builder $query): Builder => $query
                    ->where('driver_id', $driver->id)
                    ->where('is_active', true)
                    ->where('status', Incident::STATUS_RESOLVED))
                ->with(['incident.vehicle', 'reviewer'])
                ->latest('reviewed_at')
                ->limit(5)
                ->get(),
            'pointsLost' => max(0, DriverScore::DEFAULT_SCORE - $scoreValue),
            'scoreBand' => $this->scoreBand($scoreValue),
            'scoreMax' => DriverScore::DEFAULT_SCORE,
            'scoreValue' => $scoreValue,
            'severityCounts' => $this->activeIncidentCountsBy($driver, 'severity', Incident::SEVERITIES),
            'score' => $score,
        ]);
    }

    private function activeIncidentCount(Driver $driver, string $status): int
    {
        return Incident::query()
            ->where('driver_id', $driver->id)
            ->active()
            ->where('status', $status)
            ->count();
    }

    /**
     * @param  list<string>  $keys
     * @return array<string, int>
     */
    private function activeIncidentCountsBy(Driver $driver, string $column, array $keys): array
    {
        $counts = Incident::query()
            ->where('driver_id', $driver->id)
            ->active()
            ->select($column)
            ->selectRaw('count(*) as aggregate')
            ->groupBy($column)
            ->pluck('aggregate', $column);

        return collect($keys)
            ->mapWithKeys(fn (string $key): array => [$key => (int) ($counts[$key] ?? 0)])
            ->all();
    }

    /**
     * @return array{label: string, status: string, copy: string}
     */
    private function scoreBand(int $score): array
    {
        if ($score >= 80) {
            return [
                'copy' => 'Your current score is in the strongest range.',
                'label' => 'Strong performance',
                'status' => 'active',
            ];
        }

        if ($score >= 50) {
            return [
                'copy' => 'Your current score shows room for safer driving habits.',
                'label' => 'Needs attention',
                'status' => 'warning',
            ];
        }

        return [
            'copy' => 'Your current score is in the high-risk range after final reviews.',
            'label' => 'High risk',
            'status' => 'critical',
        ];
    }

    private function statusFilter(Request $request): string
    {
        $status = $request->string('status', 'active')->toString();

        return in_array($status, ['active', 'inactive', 'all'], true) ? $status : 'active';
    }

    private function scoreBandFilter(Request $request): string
    {
        $scoreBand = $request->string('score_band', 'all')->toString();

        return in_array($scoreBand, ['all', 'strong', 'attention', 'risk'], true) ? $scoreBand : 'all';
    }

    private function searchTerm(Request $request): string
    {
        return trim($request->string('q')->toString());
    }

    /**
     * @return Builder<Driver>
     */
    private function filteredDrivers(string $status, string $scoreBand, string $search): Builder
    {
        return Driver::query()
            ->with(['score', 'user'])
            ->when($status === 'active', function (Builder $query): void {
                $query
                    ->active()
                    ->where(function (Builder $query): void {
                        $query
                            ->whereDoesntHave('score')
                            ->orWhereHas('score', fn (Builder $query) => $query->where('is_active', true));
                    });
            })
            ->when($status === 'inactive', function (Builder $query): void {
                $query->where(function (Builder $query): void {
                    $query
                        ->inactive()
                        ->orWhereHas('score', fn (Builder $query) => $query->where('is_active', false));
                });
            })
            ->when($scoreBand === 'strong', fn (Builder $query) => $query->whereHas('score', fn (Builder $query) => $query->where('score', '>=', 80)))
            ->when($scoreBand === 'attention', fn (Builder $query) => $query->whereHas('score', fn (Builder $query) => $query->whereBetween('score', [50, 79])))
            ->when($scoreBand === 'risk', fn (Builder $query) => $query->whereHas('score', fn (Builder $query) => $query->where('score', '<', 50)))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query
                        ->where('license_number', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhereHas('user', function (Builder $query) use ($search): void {
                            $query
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            });
    }
}
