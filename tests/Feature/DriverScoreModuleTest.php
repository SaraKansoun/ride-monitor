<?php

use App\Models\Driver;
use App\Models\DriverScore;
use App\Models\Incident;
use App\Models\IncidentReview;
use App\Services\DriverScoreService;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    seedRolesAndPermissions();
});

test('score is created when a driver is created', function () {
    $driver = Driver::factory()->create();

    $score = DriverScore::query()->where('driver_id', $driver->id)->first();

    expect($score)->toBeInstanceOf(DriverScore::class)
        ->and($score?->score)->toBe(DriverScore::DEFAULT_SCORE)
        ->and($score?->total_incidents)->toBe(0)
        ->and($score?->unsafe_events)->toBe(0)
        ->and($score?->is_active)->toBeTrue()
        ->and($score?->last_updated_at)->not->toBeNull();
});

test('missing score is safely initialized when recalculation runs', function () {
    $driver = Driver::factory()->create();
    $driver->score()->delete();

    $score = app(DriverScoreService::class)->recalculateForDriver($driver);

    expect($score->score)->toBe(DriverScore::DEFAULT_SCORE)
        ->and($score->total_incidents)->toBe(0)
        ->and($score->unsafe_events)->toBe(0)
        ->and($score->is_active)->toBeTrue();
});

test('score updates after a monitor submits a resolved review', function () {
    $monitor = createUserWithRole('monitor');
    $driverUser = createUserWithRole('driver');
    $driver = Driver::factory()->create(['user_id' => $driverUser->id]);
    $incident = Incident::factory()->create([
        'driver_id' => $driver->id,
        'reported_by' => $driverUser->id,
        'type' => Incident::TYPE_CRASH,
        'status' => Incident::STATUS_PENDING,
    ]);

    $this->actingAs($monitor)
        ->post(route('incidents.reviews.store', $incident), [
            'fault_decision' => IncidentReview::FAULT_DRIVER,
            'notes' => 'Driver was found at fault after human review.',
        ])
        ->assertRedirect(route('incidents.show', $incident));

    $score = $driver->score()->firstOrFail();

    expect($incident->fresh()->status)->toBe(Incident::STATUS_RESOLVED)
        ->and($score->score)->toBe(80)
        ->and($score->total_incidents)->toBe(1)
        ->and($score->unsafe_events)->toBe(0);
});

test('score recalculates when a review is deactivated and reactivated', function () {
    $monitor = createUserWithRole('monitor');
    $driverUser = createUserWithRole('driver');
    $driver = Driver::factory()->create(['user_id' => $driverUser->id]);
    $incident = Incident::factory()->create([
        'driver_id' => $driver->id,
        'reported_by' => $driverUser->id,
        'type' => Incident::TYPE_UNSAFE_DRIVING,
        'status' => Incident::STATUS_PENDING,
    ]);

    $this->actingAs($monitor)
        ->post(route('incidents.reviews.store', $incident), [
            'fault_decision' => IncidentReview::FAULT_DRIVER,
            'notes' => 'Unsafe driving confirmed by the monitor.',
        ])
        ->assertRedirect(route('incidents.show', $incident));

    $review = IncidentReview::query()->where('incident_id', $incident->id)->firstOrFail();

    expect($driver->score()->firstOrFail()->score)->toBe(90)
        ->and($driver->score()->firstOrFail()->total_incidents)->toBe(1)
        ->and($driver->score()->firstOrFail()->unsafe_events)->toBe(1);

    $this->actingAs($monitor)
        ->patch(route('incident-reviews.deactivate', $review))
        ->assertRedirect();

    expect($driver->score()->firstOrFail()->score)->toBe(DriverScore::DEFAULT_SCORE)
        ->and($driver->score()->firstOrFail()->total_incidents)->toBe(0)
        ->and($driver->score()->firstOrFail()->unsafe_events)->toBe(0);

    $this->actingAs($monitor)
        ->patch(route('incident-reviews.reactivate', $review))
        ->assertRedirect();

    expect($driver->score()->firstOrFail()->score)->toBe(90)
        ->and($driver->score()->firstOrFail()->total_incidents)->toBe(1)
        ->and($driver->score()->firstOrFail()->unsafe_events)->toBe(1);
});

test('score never drops below zero or above one hundred', function () {
    $monitor = createUserWithRole('monitor');
    $driver = Driver::factory()->create();

    app(DriverScoreService::class)->recalculateForDriver($driver);

    expect($driver->score()->firstOrFail()->score)->toBe(DriverScore::MAX_SCORE);

    foreach (range(1, 6) as $number) {
        $incident = Incident::factory()->create([
            'driver_id' => $driver->id,
            'reported_by' => $monitor->id,
            'type' => Incident::TYPE_CRASH,
            'status' => Incident::STATUS_RESOLVED,
            'description' => "Crash review {$number}",
        ]);

        IncidentReview::factory()->create([
            'incident_id' => $incident->id,
            'reviewed_by' => $monitor->id,
            'fault_decision' => IncidentReview::FAULT_DRIVER,
            'is_active' => true,
        ]);
    }

    $score = app(DriverScoreService::class)->recalculateForDriver($driver);

    expect($score->score)->toBe(DriverScore::MIN_SCORE)
        ->and($score->total_incidents)->toBe(6);
});

test('admin and monitor can view safety score list', function () {
    $admin = createUserWithRole('admin');
    $monitor = createUserWithRole('monitor');
    $driverUser = createUserWithRole('driver', ['name' => 'Visible Score Driver']);
    Driver::factory()->create(['user_id' => $driverUser->id]);

    $this->actingAs($admin)
        ->get(route('safety-scores.index'))
        ->assertSuccessful()
        ->assertSeeText('Safety Scores')
        ->assertSeeText('Visible Score Driver');

    $this->actingAs($monitor)
        ->get(route('safety-scores.index'))
        ->assertSuccessful()
        ->assertSeeText('Visible Score Driver');
});

test('driver can view only their own performance page', function () {
    $driverUser = createUserWithRole('driver', ['name' => 'Primary Driver']);
    $otherDriverUser = createUserWithRole('driver', ['name' => 'Other Driver']);
    Driver::factory()->create(['user_id' => $driverUser->id]);
    Driver::factory()->create(['user_id' => $otherDriverUser->id]);

    $this->actingAs($driverUser)
        ->get(route('driver-performance.show'))
        ->assertSuccessful()
        ->assertSeeText('Primary Driver')
        ->assertDontSeeText('Other Driver')
        ->assertSeeText('Current score');

    $this->actingAs($driverUser)
        ->get(route('safety-scores.index'))
        ->assertForbidden();
});

test('inactive drivers are hidden from default active score list', function () {
    $admin = createUserWithRole('admin');
    $activeUser = createUserWithRole('driver', ['name' => 'Active Score Driver']);
    $inactiveUser = createUserWithRole('driver', ['name' => 'Inactive Score Driver']);
    Driver::factory()->create([
        'user_id' => $activeUser->id,
        'status' => Driver::STATUS_ACTIVE,
    ]);
    Driver::factory()->create([
        'user_id' => $inactiveUser->id,
        'status' => Driver::STATUS_INACTIVE,
    ]);

    $this->actingAs($admin)
        ->get(route('safety-scores.index'))
        ->assertSuccessful()
        ->assertSeeText('Active Score Driver')
        ->assertDontSeeText('Inactive Score Driver');

    $this->actingAs($admin)
        ->get(route('safety-scores.index', ['status' => 'inactive']))
        ->assertSuccessful()
        ->assertSeeText('Inactive Score Driver')
        ->assertDontSeeText('Active Score Driver');
});
