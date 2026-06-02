<?php

namespace App\Http\Controllers;

use App\Models\AIAnalysis;
use App\Models\Driver;
use App\Models\DriverScore;
use App\Models\Incident;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\DriverScoreService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private DriverScoreService $driverScoreService) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        if ($user->hasRole('admin')) {
            return $this->admin();
        }

        if ($user->hasRole('monitor')) {
            return $this->monitor($request);
        }

        if ($user->hasRole('driver')) {
            return $this->driver($request);
        }

        return view('dashboard.index');
    }

    public function admin(): View
    {
        $activeUsers = User::query()->active()->count();
        $activeDrivers = Driver::query()->active()->count();
        $activeVehicles = Vehicle::query()->active()->count();
        $activeIncidents = Incident::query()->active()->count();
        $pendingIncidents = Incident::query()->active()->where('status', Incident::STATUS_PENDING)->count();
        $underReviewIncidents = Incident::query()->active()->where('status', Incident::STATUS_UNDER_REVIEW)->count();
        $resolvedIncidents = Incident::query()->active()->where('status', Incident::STATUS_RESOLVED)->count();
        $maintenanceVehicles = Vehicle::query()->where('status', Vehicle::STATUS_MAINTENANCE)->count();
        $lowScoreDrivers = DriverScore::query()->active()->where('score', '<', 50)->count();
        $pendingAiAnalyses = AIAnalysis::query()
            ->active()
            ->whereIn('status', [AIAnalysis::STATUS_PENDING, AIAnalysis::STATUS_PROCESSING, AIAnalysis::STATUS_AI_ANALYZING])
            ->count();
        $failedAiAnalyses = AIAnalysis::query()->active()->where('status', AIAnalysis::STATUS_FAILED)->count();

        return view('dashboard.admin', [
            'metrics' => [
                [
                    'label' => 'Total active users',
                    'value' => $activeUsers,
                ],
                [
                    'label' => 'Total active drivers',
                    'value' => $activeDrivers,
                ],
                [
                    'label' => 'Total active vehicles',
                    'value' => $activeVehicles,
                ],
                [
                    'label' => 'Total active incidents',
                    'value' => $activeIncidents,
                ],
                [
                    'label' => 'Pending incidents',
                    'value' => $pendingIncidents,
                ],
                [
                    'label' => 'Resolved incidents',
                    'value' => $resolvedIncidents,
                ],
            ],
            'fleetHealth' => [
                [
                    'label' => 'Active drivers',
                    'value' => $activeDrivers,
                    'status' => 'active',
                ],
                [
                    'label' => 'Active vehicles',
                    'value' => $activeVehicles,
                    'status' => 'active',
                ],
                [
                    'label' => 'Maintenance vehicles',
                    'value' => $maintenanceVehicles,
                    'status' => 'maintenance',
                ],
                [
                    'label' => 'Open reviews',
                    'value' => $pendingIncidents + $underReviewIncidents,
                    'status' => 'under_review',
                ],
            ],
            'incidentTrendPoints' => $this->incidentTrendPoints(),
            'notificationItems' => [
                [
                    'title' => 'Review workload',
                    'copy' => $pendingIncidents + $underReviewIncidents > 0
                        ? ($pendingIncidents + $underReviewIncidents).' active incident reports need monitor attention.'
                        : 'No open review workload is waiting right now.',
                    'status' => $pendingIncidents + $underReviewIncidents > 0 ? 'under_review' : 'completed',
                ],
                [
                    'title' => 'Low score drivers',
                    'copy' => $lowScoreDrivers > 0
                        ? "{$lowScoreDrivers} active score records are below 50 and need attention."
                        : 'No active driver score is currently in the high-risk band.',
                    'status' => $lowScoreDrivers > 0 ? 'warning' : 'completed',
                ],
                [
                    'title' => 'AI analysis queue',
                    'copy' => "{$pendingAiAnalyses} pending and {$failedAiAnalyses} failed active AI analyses are visible to staff.",
                    'status' => $failedAiAnalyses > 0 ? 'failed' : ($pendingAiAnalyses > 0 ? 'pending' : 'completed'),
                ],
                [
                    'title' => 'Vehicle availability',
                    'copy' => "{$maintenanceVehicles} vehicles are marked for maintenance and hidden from active fleet readiness.",
                    'status' => $maintenanceVehicles > 0 ? 'maintenance' : 'completed',
                ],
            ],
            'recentIncidents' => Incident::query()
                ->active()
                ->with(['driver.user', 'vehicle'])
                ->latest()
                ->limit(5)
                ->get(),
        ]);
    }

    public function monitor(Request $request): View
    {
        $status = $this->incidentStatusFilter($request);
        $driverTable = (new Driver)->getTable();
        $scoreTable = (new DriverScore)->getTable();
        $pendingIncidents = Incident::query()->active()->where('status', Incident::STATUS_PENDING)->count();
        $underReviewIncidents = Incident::query()->active()->where('status', Incident::STATUS_UNDER_REVIEW)->count();
        $resolvedIncidents = Incident::query()->active()->where('status', Incident::STATUS_RESOLVED)->count();
        $failedAiAnalyses = AIAnalysis::query()->active()->where('status', AIAnalysis::STATUS_FAILED)->count();
        $lowScoreDrivers = DriverScore::query()->active()->where('score', '<', 50)->count();

        return view('dashboard.monitor', [
            'metrics' => [
                [
                    'label' => 'Pending incidents',
                    'value' => $pendingIncidents,
                ],
                [
                    'label' => 'Incidents under review',
                    'value' => $underReviewIncidents,
                ],
                [
                    'label' => 'Resolved incidents',
                    'value' => $resolvedIncidents,
                ],
            ],
            'incidentTrendPoints' => $this->incidentTrendPoints(),
            'notificationItems' => [
                [
                    'title' => 'Pending reviews',
                    'copy' => $pendingIncidents > 0
                        ? "{$pendingIncidents} pending incidents are waiting for review."
                        : 'No pending incidents are waiting for review.',
                    'status' => $pendingIncidents > 0 ? 'pending' : 'completed',
                ],
                [
                    'title' => 'Risk watchlist',
                    'copy' => $lowScoreDrivers > 0
                        ? "{$lowScoreDrivers} active driver scores are below 50."
                        : 'No high-risk driver scores are active.',
                    'status' => $lowScoreDrivers > 0 ? 'warning' : 'completed',
                ],
                [
                    'title' => 'AI follow-up',
                    'copy' => $failedAiAnalyses > 0
                        ? "{$failedAiAnalyses} active AI analyses failed and may need manual review."
                        : 'No failed active AI analyses need attention.',
                    'status' => $failedAiAnalyses > 0 ? 'failed' : 'completed',
                ],
            ],
            'recentIncidents' => Incident::query()
                ->with(['driver.user', 'vehicle'])
                ->when($status === 'active', fn (Builder $query) => $query->active())
                ->when($status === 'inactive', fn (Builder $query) => $query->inactive())
                ->latest()
                ->limit(10)
                ->get(),
            'riskyDrivers' => Driver::query()
                ->select("{$driverTable}.*")
                ->join($scoreTable, "{$scoreTable}.driver_id", '=', "{$driverTable}.id")
                ->where("{$driverTable}.is_active", true)
                ->where("{$driverTable}.status", Driver::STATUS_ACTIVE)
                ->where("{$scoreTable}.is_active", true)
                ->with(['score', 'user'])
                ->orderBy("{$scoreTable}.score")
                ->orderBy("{$driverTable}.id")
                ->limit(5)
                ->get(),
            'pendingReviewIncidents' => Incident::query()
                ->with(['driver.user', 'vehicle'])
                ->when($status === 'active', fn (Builder $query) => $query->active())
                ->when($status === 'inactive', fn (Builder $query) => $query->inactive())
                ->whereIn('status', [Incident::STATUS_PENDING, Incident::STATUS_UNDER_REVIEW])
                ->doesntHave('activeReview')
                ->latest()
                ->limit(5)
                ->get(),
            'status' => $status,
        ]);
    }

    public function driver(Request $request): View
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        $driver = $user->driverProfile()->with('score')->first();

        if (! $driver instanceof Driver) {
            return view('dashboard.driver', [
                'driver' => null,
                'latestIncident' => null,
                'notificationItems' => [
                    [
                        'title' => 'Profile setup',
                        'copy' => 'No driver profile is linked to your account yet.',
                        'status' => 'pending',
                    ],
                ],
                'recentIncidents' => collect(),
                'scoreTrendPoints' => [],
                'metrics' => [
                    [
                        'label' => 'My active incidents',
                        'value' => 0,
                    ],
                    [
                        'label' => 'My resolved incidents',
                        'value' => 0,
                    ],
                    [
                        'label' => 'My pending incidents',
                        'value' => 0,
                    ],
                    [
                        'label' => 'My current safety score',
                        'value' => 'N/A',
                    ],
                ],
            ]);
        }

        $score = $this->driverScoreService->ensureDefaultScore($driver);
        $pendingIncidents = Incident::query()
            ->where('driver_id', $driver->id)
            ->active()
            ->where('status', Incident::STATUS_PENDING)
            ->count();
        $latestIncident = Incident::query()
            ->where('driver_id', $driver->id)
            ->active()
            ->latest()
            ->first();

        return view('dashboard.driver', [
            'driver' => $driver,
            'latestIncident' => $latestIncident,
            'notificationItems' => [
                [
                    'title' => 'Safety score',
                    'copy' => $score->score < 50
                        ? 'Your safety score is in the high-risk band after final reviews.'
                        : 'Your safety score is visible to you and staff reviewers.',
                    'status' => $score->score < 50 ? 'warning' : 'active',
                ],
                [
                    'title' => 'Pending reports',
                    'copy' => $pendingIncidents > 0
                        ? "{$pendingIncidents} of your active reports are still pending."
                        : 'You have no pending active reports.',
                    'status' => $pendingIncidents > 0 ? 'pending' : 'completed',
                ],
                [
                    'title' => 'Latest incident',
                    'copy' => $latestIncident instanceof Incident
                        ? "Latest status: {$latestIncident->status}."
                        : 'No active incident has been submitted yet.',
                    'status' => $latestIncident instanceof Incident ? $latestIncident->status : 'completed',
                ],
            ],
            'recentIncidents' => Incident::query()
                ->where('driver_id', $driver->id)
                ->active()
                ->with('vehicle')
                ->latest()
                ->limit(3)
                ->get(),
            'scoreTrendPoints' => $this->driverScoreService->scoreTrendForDriver($driver),
            'metrics' => [
                [
                    'label' => 'My active incidents',
                    'value' => Incident::query()->where('driver_id', $driver->id)->active()->count(),
                ],
                [
                    'label' => 'My resolved incidents',
                    'value' => Incident::query()
                        ->where('driver_id', $driver->id)
                        ->active()
                        ->where('status', Incident::STATUS_RESOLVED)
                        ->count(),
                ],
                [
                    'label' => 'My pending incidents',
                    'value' => $pendingIncidents,
                ],
                [
                    'label' => 'My current safety score',
                    'value' => $score->score,
                ],
            ],
        ]);
    }

    private function incidentStatusFilter(Request $request): string
    {
        $status = $request->string('status', 'active')->toString();

        return in_array($status, ['active', 'inactive', 'all'], true) ? $status : 'active';
    }

    /**
     * @return list<array{label: string, value: int}>
     */
    private function incidentTrendPoints(int $days = 14): array
    {
        $start = now()->startOfDay()->subDays($days - 1);
        $end = now()->endOfDay();
        $counts = Incident::query()
            ->active()
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('DATE(created_at) as chart_date, COUNT(*) as aggregate')
            ->groupByRaw('DATE(created_at)')
            ->pluck('aggregate', 'chart_date');

        return collect(range(0, $days - 1))
            ->map(function (int $offset) use ($counts, $start): array {
                $date = $start->copy()->addDays($offset);
                $key = $date->toDateString();

                return [
                    'label' => $date->format('M j'),
                    'value' => (int) ($counts[$key] ?? 0),
                ];
            })
            ->all();
    }
}
