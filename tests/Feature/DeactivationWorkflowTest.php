<?php

use App\Models\AIAnalysis;
use App\Models\Driver;
use App\Models\DriverScore;
use App\Models\DriverVehicle;
use App\Models\Incident;
use App\Models\IncidentMedia;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    seedRolesAndPermissions();
});

test('user can be deactivated and reactivated by admin', function () {
    $admin = createUserWithRole('admin');
    $user = createUserWithRole('monitor');

    $this->actingAs($admin)
        ->patch(route('admin.users.deactivate', $user))
        ->assertRedirect();

    expect($user->fresh()->isActive())->toBeFalse();

    $this->actingAs($admin)
        ->patch(route('admin.users.reactivate', $user))
        ->assertRedirect();

    expect($user->fresh()->isActive())->toBeTrue();
});

test('inactive user cannot log in or continue using protected routes', function () {
    $user = createUserWithRole('driver', [
        'status' => User::STATUS_INACTIVE,
        'is_active' => false,
        'deactivated_at' => now(),
    ]);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $user->update([
        'status' => User::STATUS_ACTIVE,
        'is_active' => true,
        'deactivated_at' => null,
    ]);

    $this->actingAs($user);

    $user->update([
        'status' => User::STATUS_INACTIVE,
        'is_active' => false,
        'deactivated_at' => now(),
    ]);

    $this->get(route('dashboard'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('admin cannot deactivate self', function () {
    $admin = createUserWithRole('admin');

    $this->actingAs($admin)
        ->patch(route('admin.users.deactivate', $admin))
        ->assertSessionHasErrors('user');

    expect($admin->fresh()->isActive())->toBeTrue();
});

test('driver user with unresolved active incidents cannot be deactivated', function () {
    $admin = createUserWithRole('admin');
    $driverUser = createUserWithRole('driver');
    $driver = Driver::factory()->for($driverUser, 'user')->create();

    Incident::factory()->for($driver)->create([
        'reported_by' => $driverUser->id,
        'status' => Incident::STATUS_PENDING,
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.users.deactivate', $driverUser))
        ->assertSessionHasErrors('user');

    expect($driverUser->fresh()->isActive())->toBeTrue()
        ->and($driver->fresh()->isActive())->toBeTrue();
});

test('driver profile with unresolved active incidents cannot be deactivated', function () {
    $admin = createUserWithRole('admin');
    $driverUser = createUserWithRole('driver');
    $driver = Driver::factory()->for($driverUser, 'user')->create();

    Incident::factory()->for($driver)->create([
        'reported_by' => $driverUser->id,
        'status' => Incident::STATUS_UNDER_REVIEW,
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.drivers.deactivate', $driver))
        ->assertSessionHasErrors('driver');

    expect($driver->fresh()->isActive())->toBeTrue();
});

test('driver deactivation cascades to related user and reactivation restores both', function () {
    $admin = createUserWithRole('admin');
    $driverUser = createUserWithRole('driver');
    $driver = Driver::factory()->for($driverUser, 'user')->create();

    $this->actingAs($admin)
        ->patch(route('admin.drivers.deactivate', $driver))
        ->assertRedirect();

    expect($driver->fresh()->isActive())->toBeFalse()
        ->and($driverUser->fresh()->isActive())->toBeFalse();

    $this->actingAs($admin)
        ->patch(route('admin.drivers.reactivate', $driver))
        ->assertRedirect();

    expect($driver->fresh()->isActive())->toBeTrue()
        ->and($driverUser->fresh()->isActive())->toBeTrue();
});

test('vehicle with current assignment to active driver cannot be deactivated', function () {
    $admin = createUserWithRole('admin');
    $driverUser = createUserWithRole('driver');
    $driver = Driver::factory()->for($driverUser, 'user')->create();
    $vehicle = Vehicle::factory()->create();

    DriverVehicle::factory()->for($driver)->for($vehicle)->create();

    $this->actingAs($admin)
        ->patch(route('admin.vehicles.deactivate', $vehicle))
        ->assertSessionHasErrors('vehicle');

    expect($vehicle->fresh()->isActive())->toBeTrue();
});

test('monitor and driver cannot manage users drivers or vehicles', function () {
    $monitor = createUserWithRole('monitor');
    $driver = createUserWithRole('driver');

    $this->actingAs($monitor)
        ->get(route('admin.users.index'))
        ->assertForbidden();

    $this->actingAs($monitor)
        ->get(route('admin.drivers.index'))
        ->assertSuccessful()
        ->assertDontSee('Create driver');

    $this->actingAs($monitor)
        ->get(route('admin.vehicles.create'))
        ->assertForbidden();

    $this->actingAs($driver)
        ->get(route('admin.drivers.index'))
        ->assertForbidden();
});

test('incident media can be deactivated and inactive media cannot be viewed', function () {
    $monitor = createUserWithRole('monitor');
    $driverUser = createUserWithRole('driver');
    $driver = Driver::factory()->for($driverUser, 'user')->create();
    $incident = Incident::factory()->for($driver)->create([
        'reported_by' => $driverUser->id,
    ]);
    $media = IncidentMedia::factory()->for($incident)->create([
        'original_name' => 'camera-evidence.jpg',
        'uploaded_by' => $driverUser->id,
    ]);

    $this->actingAs($monitor)
        ->patch(route('incident-media.deactivate', $media))
        ->assertRedirect();

    expect($media->fresh()->isActive())->toBeFalse();

    $this->actingAs($monitor)
        ->get(route('incidents.show', $incident))
        ->assertDontSee('camera-evidence.jpg');

    $this->actingAs($monitor)
        ->get(route('incident-media.show', $media))
        ->assertNotFound();

    $this->actingAs($monitor)
        ->patch(route('incident-media.reactivate', $media))
        ->assertRedirect();

    expect($media->fresh()->isActive())->toBeTrue();
});

test('ai analyses can be deactivated and reactivated without fake actions', function () {
    $monitor = createUserWithRole('monitor');
    $analysis = AIAnalysis::factory()->completed()->create();

    $this->actingAs($monitor)
        ->patch(route('ai-analyses.deactivate', $analysis))
        ->assertRedirect();

    expect($analysis->fresh()->isActive())->toBeFalse()
        ->and($analysis->fresh()->status)->toBe(AIAnalysis::STATUS_INACTIVE);

    $this->actingAs($monitor)
        ->get(route('ai-analyses.index'))
        ->assertDontSee($analysis->summary);

    $this->actingAs($monitor)
        ->patch(route('ai-analyses.reactivate', $analysis))
        ->assertRedirect();

    expect($analysis->fresh()->isActive())->toBeTrue()
        ->and($analysis->fresh()->status)->toBe(AIAnalysis::STATUS_COMPLETED)
        ->and(Route::has('ai-analyses.generate-fake'))->toBeFalse();
});

test('driver scores can be deactivated and reactivated and inactive scores are hidden by default', function () {
    $monitor = createUserWithRole('monitor');
    $driverUser = createUserWithRole('driver');
    $driver = Driver::factory()->for($driverUser, 'user')->create();
    $score = $driver->score ?? DriverScore::factory()->for($driver)->create();

    $this->actingAs($monitor)
        ->patch(route('driver-scores.deactivate', $score))
        ->assertRedirect();

    expect($score->fresh()->isActive())->toBeFalse();

    $this->actingAs($monitor)
        ->get(route('safety-scores.index'))
        ->assertDontSee($driverUser->name);

    $this->actingAs($monitor)
        ->patch(route('driver-scores.reactivate', $score))
        ->assertRedirect();

    expect($score->fresh()->isActive())->toBeTrue();
});

test('deactivation controls use confirmation attributes and hard delete routes are absent', function () {
    $admin = createUserWithRole('admin');
    $user = createUserWithRole('monitor');

    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertSee('data-confirm="Deactivate this user?', false)
        ->assertDontSee('Delete');

    expect(Route::has('admin.users.destroy'))->toBeFalse()
        ->and(Route::has('admin.drivers.destroy'))->toBeFalse()
        ->and(Route::has('admin.vehicles.destroy'))->toBeFalse()
        ->and(Route::has('ai-analyses.destroy'))->toBeFalse()
        ->and($user->exists)->toBeTrue();
});
