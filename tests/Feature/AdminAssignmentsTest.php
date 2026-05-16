<?php

use App\Models\Driver;
use App\Models\DriverVehicle;
use App\Models\Vehicle;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    seedRolesAndPermissions();
});

test('admin can assign and unassign vehicles', function () {
    $admin = createUserWithRole('admin');
    $driverUser = createUserWithRole('driver');
    $driver = Driver::factory()->create(['user_id' => $driverUser->id]);
    $vehicle = Vehicle::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.assignments.store'), [
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
        ])
        ->assertRedirect(route('admin.assignments.index'));

    $assignment = DriverVehicle::current()->where('vehicle_id', $vehicle->id)->firstOrFail();

    expect($assignment->driver_id)->toBe($driver->id);

    $this->actingAs($admin)
        ->patch(route('admin.assignments.unassign', $assignment))
        ->assertRedirect();

    expect($assignment->fresh()->unassigned_at)->not->toBeNull();
});

test('assigning an already assigned vehicle preserves history', function () {
    $admin = createUserWithRole('admin');
    $firstUser = createUserWithRole('driver');
    $secondUser = createUserWithRole('driver');
    $firstDriver = Driver::factory()->create(['user_id' => $firstUser->id]);
    $secondDriver = Driver::factory()->create(['user_id' => $secondUser->id]);
    $vehicle = Vehicle::factory()->create();
    $previousAssignment = DriverVehicle::factory()->create(['driver_id' => $firstDriver->id, 'vehicle_id' => $vehicle->id]);

    $this->actingAs($admin)
        ->post(route('admin.assignments.store'), [
            'driver_id' => $secondDriver->id,
            'vehicle_id' => $vehicle->id,
        ])
        ->assertRedirect(route('admin.assignments.index'));

    expect($previousAssignment->fresh()->unassigned_at)->not->toBeNull()
        ->and(DriverVehicle::current()->where('vehicle_id', $vehicle->id)->firstOrFail()->driver_id)->toBe($secondDriver->id);
});

test('inactive drivers and vehicles cannot be assigned', function () {
    $admin = createUserWithRole('admin');
    $driverUser = createUserWithRole('driver');
    $driver = Driver::factory()->inactive()->create(['user_id' => $driverUser->id]);
    $vehicle = Vehicle::factory()->retired()->create();

    $this->actingAs($admin)
        ->post(route('admin.assignments.store'), [
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
        ])
        ->assertSessionHasErrors('driver_id');
});
