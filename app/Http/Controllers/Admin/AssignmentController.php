<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAssignmentRequest;
use App\Models\Driver;
use App\Models\DriverVehicle;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\PermissionCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AssignmentController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeAssignmentManagement($request);

        return view('admin.assignments.index', [
            'assignments' => DriverVehicle::query()
                ->current()
                ->with(['driver.user', 'vehicle'])
                ->latest('assigned_at')
                ->get(),
            'history' => DriverVehicle::query()
                ->whereNotNull('unassigned_at')
                ->with(['driver.user', 'vehicle'])
                ->latest('assigned_at')
                ->limit(25)
                ->get(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorizeAssignmentManagement($request);

        return view('admin.assignments.create', [
            'drivers' => Driver::query()
                ->active()
                ->whereHas('user', fn ($query) => $query
                    ->where('is_active', true)
                    ->where('status', User::STATUS_ACTIVE))
                ->with('user')
                ->orderBy('license_number')
                ->get(),
            'vehicles' => Vehicle::query()
                ->active()
                ->with('currentAssignment.driver.user')
                ->orderBy('plate_number')
                ->get(),
        ]);
    }

    public function store(StoreAssignmentRequest $request): RedirectResponse
    {
        $driver = Driver::query()->with('user')->findOrFail($request->validated('driver_id'));
        $vehicle = Vehicle::query()->findOrFail($request->validated('vehicle_id'));
        $user = $driver->user;

        if (! $user instanceof User || ! $driver->isActive() || ! $user->isActive()) {
            return back()->withErrors(['driver_id' => 'Only active drivers with active user accounts can be assigned.'])->withInput();
        }

        if (! $vehicle->isActive()) {
            return back()->withErrors(['vehicle_id' => 'Only active vehicles can be assigned.'])->withInput();
        }

        DB::transaction(function () use ($driver, $vehicle): void {
            DriverVehicle::query()
                ->current()
                ->where('vehicle_id', $vehicle->id)
                ->update(['unassigned_at' => now()]);

            DriverVehicle::query()->create([
                'driver_id' => $driver->id,
                'vehicle_id' => $vehicle->id,
                'assigned_at' => now(),
            ]);
        });

        return redirect()->route('admin.assignments.index')->with('status', 'Vehicle assigned.');
    }

    public function unassign(Request $request, DriverVehicle $assignment): RedirectResponse
    {
        $this->authorizeAssignmentManagement($request);

        if ($assignment->unassigned_at === null) {
            $assignment->update(['unassigned_at' => now()]);
        }

        return back()->with('status', 'Vehicle unassigned.');
    }

    private function authorizeAssignmentManagement(Request $request): void
    {
        $user = $request->user();

        abort_unless(
            $user instanceof User
            && $user->can(PermissionCatalog::MANAGE_DRIVERS)
            && $user->can(PermissionCatalog::MANAGE_VEHICLES),
            403
        );
    }
}
