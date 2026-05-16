<?php

namespace App\Http\Controllers;

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
        return view('dashboard.admin', [
            'metrics' => [
                [
                    'label' => 'Total active users',
                    'value' => User::query()->active()->count(),
                ],
                [
                    'label' => 'Total inactive users',
                    'value' => User::query()->inactive()->count(),
                ],
                [
                    'label' => 'Total active drivers',
                    'value' => Driver::query()->active()->count(),
                ],
                [
                    'label' => 'Total active vehicles',
                    'value' => Vehicle::query()->active()->count(),
                ],
                [
                    'label' => 'Total active incidents',
                    'value' => Incident::query()->active()->count(),
                ],
                [
                    'label' => 'Pending incidents',
                    'value' => Incident::query()->active()->where('status', Incident::STATUS_PENDING)->count(),
                ],
                [
                    'label' => 'Resolved incidents',
                    'value' => Incident::query()->active()->where('status', Incident::STATUS_RESOLVED)->count(),
                ],
            ],
        ]);
    }

    public function monitor(Request $request): View
    {
        $status = $this->incidentStatusFilter($request);
        $driverTable = (new Driver)->getTable();
        $scoreTable = (new DriverScore)->getTable();

        return view('dashboard.monitor', [
            'metrics' => [
                [
                    'label' => 'Pending incidents',
                    'value' => Incident::query()->active()->where('status', Incident::STATUS_PENDING)->count(),
                ],
                [
                    'label' => 'Incidents under review',
                    'value' => Incident::query()->active()->where('status', Incident::STATUS_UNDER_REVIEW)->count(),
                ],
                [
                    'label' => 'Resolved incidents',
                    'value' => Incident::query()->active()->where('status', Incident::STATUS_RESOLVED)->count(),
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

        return view('dashboard.driver', [
            'driver' => $driver,
            'latestIncident' => Incident::query()
                ->where('driver_id', $driver->id)
                ->active()
                ->latest()
                ->first(),
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
                    'value' => Incident::query()
                        ->where('driver_id', $driver->id)
                        ->active()
                        ->where('status', Incident::STATUS_PENDING)
                        ->count(),
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
}
