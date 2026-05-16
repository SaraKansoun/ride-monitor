<?php

use App\Models\Driver;
use App\Models\DriverVehicle;
use App\Models\Vehicle;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Role::findOrCreate('driver');
});

test('user has one driver profile and driver belongs to user', function () {
    $user = createUserWithRole('driver');
    $driver = Driver::factory()->create(['user_id' => $user->id]);

    expect($user->driverProfile->is($driver))->toBeTrue()
        ->and($driver->user->is($user))->toBeTrue();
});

test('vehicle assignment relationships resolve driver and vehicle', function () {
    $user = createUserWithRole('driver');
    $driver = Driver::factory()->create(['user_id' => $user->id]);
    $vehicle = Vehicle::factory()->create();
    $assignment = DriverVehicle::factory()->create(['driver_id' => $driver->id, 'vehicle_id' => $vehicle->id]);

    expect($assignment->driver->is($driver))->toBeTrue()
        ->and($assignment->vehicle->is($vehicle))->toBeTrue();
});

test('current assignment scope returns only active assignments', function () {
    $active = DriverVehicle::factory()->create();
    DriverVehicle::factory()->ended()->create();

    expect(DriverVehicle::current()->pluck('id')->all())->toBe([$active->id]);
});
