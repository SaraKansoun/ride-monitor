<?php

use App\Models\Driver;
use App\Models\DriverVehicle;
use App\Models\Incident;
use App\Models\Vehicle;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    seedRolesAndPermissions();
});

test('driver can create incident with media', function () {
    Storage::fake('public');

    $driverUser = createUserWithRole('driver');
    $driver = Driver::factory()->create(['user_id' => $driverUser->id]);
    $vehicle = Vehicle::factory()->create(['plate_number' => 'INC-100']);

    DriverVehicle::factory()->create([
        'driver_id' => $driver->id,
        'vehicle_id' => $vehicle->id,
    ]);

    $this->actingAs($driverUser)
        ->post(route('incidents.store'), [
            'type' => Incident::TYPE_CRASH,
            'description' => 'Hard braking near the depot entrance.',
            'vehicle_id' => $vehicle->id,
            'media' => [
                UploadedFile::fake()->create('dashcam.mp4', 1024, 'video/mp4'),
            ],
        ])
        ->assertRedirect();

    $incident = Incident::query()
        ->where('description', 'Hard braking near the depot entrance.')
        ->with('media')
        ->firstOrFail();

    expect($incident->driver_id)->toBe($driver->id)
        ->and($incident->vehicle_id)->toBe($vehicle->id)
        ->and($incident->reported_by)->toBe($driverUser->id)
        ->and($incident->status)->toBe(Incident::STATUS_PENDING)
        ->and($incident->is_active)->toBeTrue()
        ->and($incident->media)->toHaveCount(1);

    Storage::disk('public')->assertExists($incident->media->first()->file_path);
});

test('driver can only view own incidents', function () {
    $driverUser = createUserWithRole('driver');
    $otherDriverUser = createUserWithRole('driver');
    $driver = Driver::factory()->create(['user_id' => $driverUser->id]);
    $otherDriver = Driver::factory()->create(['user_id' => $otherDriverUser->id]);

    $ownIncident = Incident::factory()->create([
        'driver_id' => $driver->id,
        'reported_by' => $driverUser->id,
        'description' => 'Own driver incident.',
    ]);
    $otherIncident = Incident::factory()->create([
        'driver_id' => $otherDriver->id,
        'reported_by' => $otherDriverUser->id,
        'description' => 'Other driver incident.',
    ]);

    $this->actingAs($driverUser)
        ->get(route('incidents.index'))
        ->assertSuccessful()
        ->assertSeeText($ownIncident->description)
        ->assertDontSeeText($otherIncident->description);

    $this->actingAs($driverUser)
        ->get(route('incidents.show', $otherIncident))
        ->assertForbidden();
});

test('monitor and admin can view incidents', function () {
    $admin = createUserWithRole('admin');
    $monitor = createUserWithRole('monitor');
    $driverUser = createUserWithRole('driver');
    $driver = Driver::factory()->create(['user_id' => $driverUser->id]);
    $incident = Incident::factory()->create([
        'driver_id' => $driver->id,
        'reported_by' => $driverUser->id,
        'description' => 'Fleet-wide review incident.',
    ]);

    $this->actingAs($admin)
        ->get(route('incidents.index'))
        ->assertSuccessful()
        ->assertSeeText('Fleet-wide review incident.');

    $this->actingAs($monitor)
        ->get(route('incidents.show', $incident))
        ->assertSuccessful()
        ->assertSeeText('Fleet-wide review incident.');
});

test('incident can be deactivated instead of deleted', function () {
    $monitor = createUserWithRole('monitor');
    $driverUser = createUserWithRole('driver');
    $driver = Driver::factory()->create(['user_id' => $driverUser->id]);
    $incident = Incident::factory()->create([
        'driver_id' => $driver->id,
        'reported_by' => $driverUser->id,
        'description' => 'Deactivation test incident.',
    ]);

    $this->actingAs($monitor)
        ->from(route('incidents.show', $incident))
        ->patch(route('incidents.deactivate', $incident))
        ->assertRedirect(route('incidents.show', $incident));

    $incident->refresh();

    $this->assertModelExists($incident);

    expect($incident->is_active)->toBeFalse()
        ->and($incident->status)->toBe(Incident::STATUS_INACTIVE)
        ->and($incident->deactivated_by)->toBe($monitor->id)
        ->and($incident->deactivated_at)->not->toBeNull();

    $this->actingAs($monitor)
        ->get(route('incidents.index'))
        ->assertSuccessful()
        ->assertDontSeeText('Deactivation test incident.');

    $this->actingAs($monitor)
        ->get(route('incidents.index', ['status' => 'inactive']))
        ->assertSuccessful()
        ->assertSeeText('Deactivation test incident.');
});
