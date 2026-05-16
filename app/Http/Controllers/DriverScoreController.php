<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use App\Models\DriverScore;
use App\Models\User;
use App\Services\DeactivationService;
use App\Services\DriverScoreService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DriverScoreController extends Controller
{
    public function __construct(
        private DeactivationService $deactivationService,
        private DriverScoreService $driverScoreService
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', DriverScore::class);

        $status = $this->statusFilter($request);
        $drivers = Driver::query()
            ->with(['score', 'user'])
            ->when($status === 'active', function ($query): void {
                $query
                    ->active()
                    ->where(function ($query): void {
                        $query
                            ->whereDoesntHave('score')
                            ->orWhereHas('score', fn ($query) => $query->where('is_active', true));
                    });
            })
            ->when($status === 'inactive', function ($query): void {
                $query->where(function ($query): void {
                    $query
                        ->inactive()
                        ->orWhereHas('score', fn ($query) => $query->where('is_active', false));
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $drivers->getCollection()->each(function (Driver $driver): void {
            $driver->setRelation('score', $this->driverScoreService->ensureDefaultScore($driver));
        });

        return view('safety-scores.index', [
            'drivers' => $drivers,
            'status' => $status,
        ]);
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

        $driver = $user->driverProfile()->with('user')->first();

        abort_unless($driver instanceof Driver, 404);

        $score = $this->driverScoreService->ensureDefaultScore($driver);

        Gate::authorize('view', $score);

        return view('driver-performance.show', [
            'driver' => $driver,
            'score' => $score,
        ]);
    }

    private function statusFilter(Request $request): string
    {
        $status = $request->string('status', 'active')->toString();

        return in_array($status, ['active', 'inactive', 'all'], true) ? $status : 'active';
    }
}
