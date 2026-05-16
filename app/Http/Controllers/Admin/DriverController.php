<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CompleteDriverProfileRequest;
use App\Http\Requests\Admin\StoreDriverRequest;
use App\Http\Requests\Admin\UpdateDriverRequest;
use App\Models\Driver;
use App\Models\User;
use App\Services\DeactivationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DriverController extends Controller
{
    public function __construct(private DeactivationService $deactivationService) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Driver::class);

        $status = $this->statusFilter($request);

        $drivers = User::role('driver')
            ->with(['driverProfile.currentAssignments.vehicle'])
            ->when($status === Driver::STATUS_ACTIVE, function ($query): void {
                $query->where(function ($query): void {
                    $query->where(function ($query): void {
                        $query
                            ->where('is_active', true)
                            ->where('status', User::STATUS_ACTIVE)
                            ->whereDoesntHave('driverProfile');
                    })
                        ->orWhereHas('driverProfile', fn ($query) => $query
                            ->where('is_active', true)
                            ->where('status', Driver::STATUS_ACTIVE));
                });
            })
            ->when($status === 'inactive', function ($query): void {
                $query->where(function ($query): void {
                    $query->where(function ($query): void {
                        $query
                            ->where(function ($query): void {
                                $query
                                    ->where('is_active', false)
                                    ->orWhere('status', '!=', User::STATUS_ACTIVE);
                            })
                            ->whereDoesntHave('driverProfile');
                    })
                        ->orWhereHas('driverProfile', fn ($query) => $query
                            ->where(function ($query): void {
                                $query
                                    ->where('is_active', false)
                                    ->orWhere('status', '!=', Driver::STATUS_ACTIVE);
                            }));
                });
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.drivers.index', [
            'drivers' => $drivers,
            'status' => $status,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Driver::class);

        return view('admin.drivers.create', [
            'driver' => new Driver(['status' => Driver::STATUS_ACTIVE]),
            'statuses' => Driver::STATUSES,
            'user' => new User(['status' => User::STATUS_ACTIVE]),
        ]);
    }

    public function store(StoreDriverRequest $request): RedirectResponse
    {
        $driver = DB::transaction(function () use ($request): Driver {
            $actor = $request->user();
            $status = $request->validated('status');
            $userStatus = $status === Driver::STATUS_ACTIVE
                ? User::STATUS_ACTIVE
                : User::STATUS_INACTIVE;
            $user = User::query()->create([
                ...$request->safe()->only(['name', 'email', 'password']),
                'status' => $userStatus,
                ...$this->deactivationService->attributesForStatus(
                    $userStatus,
                    User::STATUS_ACTIVE,
                    $actor instanceof User ? $actor : null,
                ),
            ]);
            $user->assignRole('driver');

            return Driver::query()->create([
                ...$request->safe()->only(['license_number', 'phone', 'status']),
                ...$this->deactivationService->attributesForStatus(
                    $status,
                    Driver::STATUS_ACTIVE,
                    $actor instanceof User ? $actor : null,
                ),
                'user_id' => $user->id,
            ]);
        });

        return redirect()->route('admin.drivers.show', $driver)->with('status', 'Driver created.');
    }

    public function show(Driver $driver): View
    {
        Gate::authorize('view', $driver);

        $driver->load(['user', 'currentAssignment.vehicle', 'assignments.vehicle']);

        return view('admin.drivers.show', [
            'driver' => $driver,
        ]);
    }

    public function edit(Driver $driver): View
    {
        Gate::authorize('update', $driver);

        $driver->load('user');

        return view('admin.drivers.edit', [
            'driver' => $driver,
            'statuses' => Driver::STATUSES,
            'userStatuses' => User::STATUSES,
        ]);
    }

    public function update(UpdateDriverRequest $request, Driver $driver): RedirectResponse
    {
        if (
            $request->validated('status') !== Driver::STATUS_ACTIVE
            || $request->validated('user_status') !== User::STATUS_ACTIVE
        ) {
            if ($driver->currentAssignment()->exists()) {
                return back()->withErrors(['driver' => 'Unassign this driver before deactivating the profile.']);
            }

            if ($driver->hasUnresolvedActiveIncidents()) {
                return back()->withErrors(['driver' => "Resolve this driver's active incidents before deactivating the profile."]);
            }
        }

        DB::transaction(function () use ($driver, $request): void {
            $user = $driver->user;

            abort_unless($user instanceof User, 404);

            $userData = [
                'name' => $request->validated('name'),
                'email' => $request->validated('email'),
                'status' => $request->validated('user_status'),
                ...$this->deactivationService->attributesForStatus(
                    $request->validated('user_status'),
                    User::STATUS_ACTIVE,
                    $request->user() instanceof User ? $request->user() : null,
                ),
            ];

            if ($request->filled('password')) {
                $userData['password'] = $request->validated('password');
            }

            $user->update($userData);
            $user->syncRoles(['driver']);
            $driver->update([
                ...$request->safe()->only(['license_number', 'phone', 'status']),
                ...$this->deactivationService->attributesForStatus(
                    $request->validated('status'),
                    Driver::STATUS_ACTIVE,
                    $request->user() instanceof User ? $request->user() : null,
                ),
            ]);
        });

        return redirect()->route('admin.drivers.show', $driver)->with('status', 'Driver updated.');
    }

    public function createForUser(User $user): View|RedirectResponse
    {
        Gate::authorize('create', Driver::class);

        abort_unless($user->hasRole('driver'), 404);

        if ($user->driverProfile) {
            return redirect()->route('admin.drivers.edit', $user->driverProfile);
        }

        return view('admin.drivers.complete', [
            'driver' => new Driver(['status' => Driver::STATUS_ACTIVE]),
            'statuses' => Driver::STATUSES,
            'user' => $user,
        ]);
    }

    public function storeForUser(CompleteDriverProfileRequest $request, User $user): RedirectResponse
    {
        abort_unless($user->hasRole('driver'), 404);

        if ($user->driverProfile) {
            return redirect()->route('admin.drivers.edit', $user->driverProfile);
        }

        $driver = DB::transaction(function () use ($request, $user): Driver {
            $actor = $request->user();
            $status = $request->validated('status');

            if ($status !== Driver::STATUS_ACTIVE) {
                $user->update([
                    'status' => User::STATUS_INACTIVE,
                    ...$this->deactivationService->attributesForStatus(
                        User::STATUS_INACTIVE,
                        User::STATUS_ACTIVE,
                        $actor instanceof User ? $actor : null,
                    ),
                ]);
            }

            return Driver::query()->create([
                ...$request->safe()->only(['license_number', 'phone', 'status']),
                ...$this->deactivationService->attributesForStatus(
                    $status,
                    Driver::STATUS_ACTIVE,
                    $actor instanceof User ? $actor : null,
                ),
                'user_id' => $user->id,
            ]);
        });

        return redirect()->route('admin.drivers.show', $driver)->with('status', 'Driver profile completed.');
    }

    public function deactivate(Request $request, Driver $driver): RedirectResponse
    {
        Gate::authorize('deactivate', $driver);

        $actor = $request->user();

        abort_unless($actor instanceof User, 403);

        if ($driver->currentAssignment()->exists()) {
            return back()->withErrors(['driver' => 'Unassign this driver before deactivating the profile.']);
        }

        if ($driver->hasUnresolvedActiveIncidents()) {
            return back()->withErrors(['driver' => "Resolve this driver's active incidents before deactivating the profile."]);
        }

        $this->deactivationService->deactivateDriver($driver, $actor);

        return back()->with('status', 'Driver deactivated.');
    }

    public function reactivate(Driver $driver): RedirectResponse
    {
        Gate::authorize('reactivate', $driver);

        $this->deactivationService->reactivateDriver($driver);

        return back()->with('status', 'Driver reactivated.');
    }

    private function statusFilter(Request $request): string
    {
        $status = $request->string('status', Driver::STATUS_ACTIVE)->toString();

        return in_array($status, [Driver::STATUS_ACTIVE, 'inactive', 'all'], true) ? $status : Driver::STATUS_ACTIVE;
    }
}
