<?php

use App\Models\AIAnalysis;
use App\Models\Driver;
use App\Models\DriverVehicle;
use App\Models\Incident;
use App\Models\IncidentMedia;
use App\Models\IncidentReview;
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

test('incident index keeps long descriptions compact with icon-only row actions', function () {
    $monitor = createUserWithRole('monitor');
    $driverUser = createUserWithRole('driver');
    $driver = Driver::factory()->create(['user_id' => $driverUser->id]);

    Incident::factory()->create([
        'driver_id' => $driver->id,
        'reported_by' => $driverUser->id,
        'description' => 'This incident description is intentionally long for compact table rendering.',
    ]);

    $this->actingAs($monitor)
        ->get(route('incidents.index'))
        ->assertSuccessful()
        ->assertSeeText('This incident description is intentionally...')
        ->assertSee('title="This incident description is intentionally long for compact table rendering."', false)
        ->assertSee('aria-label="View incident"', false)
        ->assertSee('aria-label="Deactivate incident"', false)
        ->assertDontSeeText('Deactivate');
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

test('admin and monitor can edit active unresolved incident descriptions', function () {
    $admin = createUserWithRole('admin');
    $monitor = createUserWithRole('monitor');
    $driverUser = createUserWithRole('driver');
    $driver = Driver::factory()->create(['user_id' => $driverUser->id]);
    $pendingIncident = Incident::factory()->create([
        'driver_id' => $driver->id,
        'reported_by' => $driverUser->id,
        'description' => 'Original pending description.',
        'status' => Incident::STATUS_PENDING,
    ]);
    $underReviewIncident = Incident::factory()->create([
        'driver_id' => $driver->id,
        'reported_by' => $driverUser->id,
        'description' => 'Original under review description.',
        'status' => Incident::STATUS_UNDER_REVIEW,
    ]);

    $this->actingAs($admin)
        ->get(route('incidents.show', $pendingIncident))
        ->assertSuccessful()
        ->assertSeeText('Edit description');

    $this->actingAs($monitor)
        ->get(route('incidents.show', $underReviewIncident))
        ->assertSuccessful()
        ->assertSeeText('Edit description');

    $this->actingAs($monitor)
        ->patch(route('incidents.update', $underReviewIncident), [
            'description' => 'Updated monitor clarification.',
        ])
        ->assertRedirect(route('incidents.show', $underReviewIncident));

    expect($underReviewIncident->fresh()->description)->toBe('Updated monitor clarification.');
});

test('incident description update preserves incident workflow and related records', function () {
    $monitor = createUserWithRole('monitor');
    $driverUser = createUserWithRole('driver');
    $driver = Driver::factory()->create(['user_id' => $driverUser->id]);
    $vehicle = Vehicle::factory()->create();
    $incident = Incident::factory()->create([
        'driver_id' => $driver->id,
        'vehicle_id' => $vehicle->id,
        'reported_by' => $driverUser->id,
        'type' => Incident::TYPE_CRASH,
        'severity' => Incident::SEVERITY_HIGH,
        'status' => Incident::STATUS_UNDER_REVIEW,
        'description' => 'Original preserved incident description.',
    ]);
    $analysis = AIAnalysis::factory()->completed()->create(['incident_id' => $incident->id]);
    $media = IncidentMedia::factory()->create(['incident_id' => $incident->id]);
    $review = IncidentReview::factory()->inactive()->create(['incident_id' => $incident->id]);

    $this->actingAs($monitor)
        ->patch(route('incidents.update', $incident), [
            'description' => 'Only the description should change.',
        ])
        ->assertRedirect(route('incidents.show', $incident));

    $incident->refresh();

    expect($incident->description)->toBe('Only the description should change.')
        ->and($incident->status)->toBe(Incident::STATUS_UNDER_REVIEW)
        ->and($incident->type)->toBe(Incident::TYPE_CRASH)
        ->and($incident->severity)->toBe(Incident::SEVERITY_HIGH)
        ->and($incident->vehicle_id)->toBe($vehicle->id)
        ->and($incident->is_active)->toBeTrue()
        ->and($incident->aiAnalyses()->whereKey($analysis)->exists())->toBeTrue()
        ->and($incident->media()->whereKey($media)->exists())->toBeTrue()
        ->and($incident->reviews()->whereKey($review)->exists())->toBeTrue();
});

test('driver cannot edit incident descriptions', function () {
    $driverUser = createUserWithRole('driver');
    $driver = Driver::factory()->create(['user_id' => $driverUser->id]);
    $incident = Incident::factory()->create([
        'driver_id' => $driver->id,
        'reported_by' => $driverUser->id,
        'description' => 'Driver owned description.',
    ]);

    $this->actingAs($driverUser)
        ->get(route('incidents.show', $incident))
        ->assertSuccessful()
        ->assertDontSeeText('Edit description');

    $this->actingAs($driverUser)
        ->get(route('incidents.edit', $incident))
        ->assertForbidden();

    $this->actingAs($driverUser)
        ->patch(route('incidents.update', $incident), [
            'description' => 'Driver attempted edit.',
        ])
        ->assertForbidden();

    expect($incident->fresh()->description)->toBe('Driver owned description.');
});

test('resolved and inactive incidents cannot have descriptions edited', function () {
    $monitor = createUserWithRole('monitor');
    $driverUser = createUserWithRole('driver');
    $driver = Driver::factory()->create(['user_id' => $driverUser->id]);
    $resolvedIncident = Incident::factory()->create([
        'driver_id' => $driver->id,
        'reported_by' => $driverUser->id,
        'description' => 'Resolved description.',
        'status' => Incident::STATUS_RESOLVED,
    ]);
    $inactiveIncident = Incident::factory()->inactive()->create([
        'driver_id' => $driver->id,
        'reported_by' => $driverUser->id,
        'description' => 'Inactive description.',
    ]);

    $this->actingAs($monitor)
        ->get(route('incidents.show', $resolvedIncident))
        ->assertSuccessful()
        ->assertDontSeeText('Edit description');

    $this->actingAs($monitor)
        ->get(route('incidents.edit', $resolvedIncident))
        ->assertForbidden();

    $this->actingAs($monitor)
        ->patch(route('incidents.update', $resolvedIncident), [
            'description' => 'Resolved attempted edit.',
        ])
        ->assertForbidden();

    $this->actingAs($monitor)
        ->get(route('incidents.edit', $inactiveIncident))
        ->assertForbidden();

    $this->actingAs($monitor)
        ->patch(route('incidents.update', $inactiveIncident), [
            'description' => 'Inactive attempted edit.',
        ])
        ->assertForbidden();

    expect($resolvedIncident->fresh()->description)->toBe('Resolved description.')
        ->and($inactiveIncident->fresh()->description)->toBe('Inactive description.');
});
