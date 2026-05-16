<?php

use App\Models\Driver;
use App\Models\DriverVehicle;
use App\Models\Vehicle;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    seedRolesAndPermissions();
});

test('admin can create and edit vehicles', function () {
    $admin = createUserWithRole('admin');

    $this->actingAs($admin)
        ->post(route('admin.vehicles.store'), [
            'plate_number' => 'ABC-123',
            'model' => 'Toyota Prius',
            'year' => 2022,
            'status' => Vehicle::STATUS_ACTIVE,
        ])
        ->assertRedirect();

    $vehicle = Vehicle::where('plate_number', 'ABC-123')->firstOrFail();

    $this->actingAs($admin)
        ->patch(route('admin.vehicles.update', $vehicle), [
            'plate_number' => 'XYZ-987',
            'model' => 'Ford Transit',
            'year' => 2023,
            'status' => Vehicle::STATUS_MAINTENANCE,
        ])
        ->assertRedirect(route('admin.vehicles.show', $vehicle));

    $vehicle->refresh();

    expect($vehicle->plate_number)->toBe('XYZ-987')
        ->and($vehicle->status)->toBe(Vehicle::STATUS_MAINTENANCE);
});

test('admin can deactivate and reactivate vehicles', function () {
    $admin = createUserWithRole('admin');
    $vehicle = Vehicle::factory()->create();

    $this->actingAs($admin)
        ->patch(route('admin.vehicles.deactivate', $vehicle))
        ->assertRedirect();

    expect($vehicle->fresh()->status)->toBe(Vehicle::STATUS_RETIRED);

    $this->actingAs($admin)
        ->patch(route('admin.vehicles.reactivate', $vehicle))
        ->assertRedirect();

    expect($vehicle->fresh()->status)->toBe(Vehicle::STATUS_ACTIVE);
});

test('vehicle with active assignment cannot be deactivated', function () {
    $admin = createUserWithRole('admin');
    $driverUser = createUserWithRole('driver');
    $driver = Driver::factory()->create(['user_id' => $driverUser->id]);
    $vehicle = Vehicle::factory()->create();
    DriverVehicle::factory()->create(['driver_id' => $driver->id, 'vehicle_id' => $vehicle->id]);

    $this->actingAs($admin)
        ->from(route('admin.vehicles.show', $vehicle))
        ->patch(route('admin.vehicles.deactivate', $vehicle))
        ->assertRedirect(route('admin.vehicles.show', $vehicle))
        ->assertSessionHasErrors('vehicle');

    expect($vehicle->fresh()->status)->toBe(Vehicle::STATUS_ACTIVE);
});
