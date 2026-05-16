<?php

use App\Models\Driver;
use App\Models\DriverVehicle;
use App\Models\Vehicle;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    seedRolesAndPermissions();
});

test('driver role users appear as missing profiles', function () {
    $admin = createUserWithRole('admin');
    $driverUser = createUserWithRole('driver', ['name' => 'Missing Driver']);

    $this->actingAs($admin)
        ->get(route('admin.drivers.index'))
        ->assertSuccessful()
        ->assertSee($driverUser->email)
        ->assertSee('Missing profile');
});

test('drivers index summarizes multiple current vehicle assignments', function () {
    $admin = createUserWithRole('admin');
    $driverUser = createUserWithRole('driver');
    $driver = Driver::factory()->create(['user_id' => $driverUser->id]);
    $plateNumbers = collect(['123814', '123978', '326713', '441002', '552003', '663004']);

    $plateNumbers->each(function (string $plateNumber, int $index) use ($driver): void {
        $vehicle = Vehicle::factory()->create(['plate_number' => $plateNumber]);

        DriverVehicle::factory()->create([
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'assigned_at' => now()->subMinutes($index),
        ]);
    });

    $historicalVehicle = Vehicle::factory()->create(['plate_number' => 'ENDED-01']);
    DriverVehicle::factory()->create([
        'driver_id' => $driver->id,
        'vehicle_id' => $historicalVehicle->id,
        'assigned_at' => now()->subDay(),
        'unassigned_at' => now()->subHour(),
    ]);

    $response = $this->actingAs($admin)->get(route('admin.drivers.index'));

    $response
        ->assertSuccessful()
        ->assertSeeTextInOrder(['123814', '123978', '+4'])
        ->assertSee('vehicle-summary-popover', false)
        ->assertDontSeeText('ENDED-01');

    $plateNumbers->each(fn (string $plateNumber) => $response->assertSeeText($plateNumber));
});

test('drivers without current vehicle assignments show unassigned', function () {
    $admin = createUserWithRole('admin');
    $driverUser = createUserWithRole('driver');
    Driver::factory()->create(['user_id' => $driverUser->id]);

    $this->actingAs($admin)
        ->get(route('admin.drivers.index'))
        ->assertSuccessful()
        ->assertSeeText('Unassigned');
});

test('admin can create a driver directly', function () {
    $admin = createUserWithRole('admin');

    $this->actingAs($admin)
        ->post(route('admin.drivers.store'), [
            'name' => 'Driver One',
            'email' => 'driver.one@example.com',
            'password' => 'password',
            'license_number' => 'DRV-10001',
            'phone' => '555-0101',
            'status' => Driver::STATUS_ACTIVE,
        ])
        ->assertRedirect();

    $driver = Driver::where('license_number', 'DRV-10001')->with('user')->firstOrFail();

    expect($driver->user->email)->toBe('driver.one@example.com')
        ->and($driver->user->hasRole('driver'))->toBeTrue();
});

test('admin can deactivate and reactivate drivers', function () {
    $admin = createUserWithRole('admin');
    $driverUser = createUserWithRole('driver');
    $driver = Driver::factory()->create(['user_id' => $driverUser->id]);

    $this->actingAs($admin)
        ->patch(route('admin.drivers.deactivate', $driver))
        ->assertRedirect();

    expect($driver->fresh()->status)->toBe(Driver::STATUS_INACTIVE);

    $this->actingAs($admin)
        ->patch(route('admin.drivers.reactivate', $driver))
        ->assertRedirect();

    expect($driver->fresh()->status)->toBe(Driver::STATUS_ACTIVE);
});

test('driver with active assignment cannot be deactivated', function () {
    $admin = createUserWithRole('admin');
    $driverUser = createUserWithRole('driver');
    $driver = Driver::factory()->create(['user_id' => $driverUser->id]);
    $vehicle = Vehicle::factory()->create();
    DriverVehicle::factory()->create(['driver_id' => $driver->id, 'vehicle_id' => $vehicle->id]);

    $this->actingAs($admin)
        ->from(route('admin.drivers.show', $driver))
        ->patch(route('admin.drivers.deactivate', $driver))
        ->assertRedirect(route('admin.drivers.show', $driver))
        ->assertSessionHasErrors('driver');

    expect($driver->fresh()->status)->toBe(Driver::STATUS_ACTIVE);
});
