<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreVehicleRequest;
use App\Http\Requests\Admin\UpdateVehicleRequest;
use App\Models\Driver;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\DeactivationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class VehicleController extends Controller
{
    public function __construct(private DeactivationService $deactivationService) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Vehicle::class);

        $status = $this->statusFilter($request);

        $vehicles = Vehicle::query()
            ->with(['currentAssignment.driver.user'])
            ->when($status === Vehicle::STATUS_ACTIVE, fn ($query) => $query->active())
            ->when($status === 'inactive', fn ($query) => $query->inactive())
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.vehicles.index', [
            'status' => $status,
            'vehicles' => $vehicles,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Vehicle::class);

        return view('admin.vehicles.create', [
            'statuses' => Vehicle::STATUSES,
            'vehicle' => new Vehicle(['status' => Vehicle::STATUS_ACTIVE]),
        ]);
    }

    public function store(StoreVehicleRequest $request): RedirectResponse
    {
        $actor = $request->user();
        $status = $request->validated('status');
        $vehicle = Vehicle::query()->create([
            ...$request->validated(),
            ...$this->deactivationService->attributesForStatus(
                $status,
                Vehicle::STATUS_ACTIVE,
                $actor instanceof User ? $actor : null,
            ),
        ]);

        return redirect()->route('admin.vehicles.show', $vehicle)->with('status', 'Vehicle created.');
    }

    public function show(Vehicle $vehicle): View
    {
        Gate::authorize('view', $vehicle);

        $vehicle->load(['currentAssignment.driver.user', 'assignments.driver.user']);

        return view('admin.vehicles.show', [
            'vehicle' => $vehicle,
        ]);
    }

    public function edit(Vehicle $vehicle): View
    {
        Gate::authorize('update', $vehicle);

        return view('admin.vehicles.edit', [
            'statuses' => Vehicle::STATUSES,
            'vehicle' => $vehicle,
        ]);
    }

    public function update(UpdateVehicleRequest $request, Vehicle $vehicle): RedirectResponse
    {
        $actor = $request->user();
        $status = $request->validated('status');

        if (
            $status !== Vehicle::STATUS_ACTIVE
            && $vehicle->currentAssignment()
                ->whereHas('driver', fn ($query) => $query
                    ->where('is_active', true)
                    ->where('status', Driver::STATUS_ACTIVE))
                ->exists()
        ) {
            return back()->withErrors(['vehicle' => 'Unassign this vehicle before deactivating it.']);
        }

        $vehicle->update([
            ...$request->validated(),
            ...$this->deactivationService->attributesForStatus(
                $status,
                Vehicle::STATUS_ACTIVE,
                $actor instanceof User ? $actor : null,
            ),
        ]);

        return redirect()->route('admin.vehicles.show', $vehicle)->with('status', 'Vehicle updated.');
    }

    public function deactivate(Request $request, Vehicle $vehicle): RedirectResponse
    {
        Gate::authorize('deactivate', $vehicle);

        $actor = $request->user();

        abort_unless($actor instanceof User, 403);

        if ($vehicle->currentAssignment()
            ->whereHas('driver', fn ($query) => $query
                ->where('is_active', true)
                ->where('status', Driver::STATUS_ACTIVE))
            ->exists()) {
            return back()->withErrors(['vehicle' => 'Unassign this vehicle before deactivating it.']);
        }

        $this->deactivationService->deactivateVehicle($vehicle, $actor);

        return back()->with('status', 'Vehicle deactivated.');
    }

    public function reactivate(Vehicle $vehicle): RedirectResponse
    {
        Gate::authorize('reactivate', $vehicle);

        $this->deactivationService->reactivateVehicle($vehicle);

        return back()->with('status', 'Vehicle reactivated.');
    }

    private function statusFilter(Request $request): string
    {
        $status = $request->string('status', Vehicle::STATUS_ACTIVE)->toString();

        return in_array($status, [Vehicle::STATUS_ACTIVE, 'inactive', 'all'], true) ? $status : Vehicle::STATUS_ACTIVE;
    }
}
