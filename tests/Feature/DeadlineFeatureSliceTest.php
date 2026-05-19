<?php

use App\Models\AIAnalysis;
use App\Models\Driver;
use App\Models\Incident;
use App\Models\Vehicle;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    seedRolesAndPermissions();
});

test('admin index pages support search and extra filters', function (): void {
    $admin = createUserWithRole('admin');
    $matchingUser = createUserWithRole('monitor', ['name' => 'Searchable Monitor', 'email' => 'searchable-monitor@example.com']);
    $otherUser = createUserWithRole('monitor', ['name' => 'Hidden Monitor', 'email' => 'hidden-monitor@example.com']);

    $matchingDriverUser = createUserWithRole('driver', ['name' => 'Searchable Driver']);
    $matchingDriver = Driver::factory()->create([
        'license_number' => 'SEARCH-LICENSE',
        'user_id' => $matchingDriverUser->id,
    ]);
    $otherDriverUser = createUserWithRole('driver', ['name' => 'Hidden Driver']);
    Driver::factory()->create([
        'license_number' => 'HIDDEN-LICENSE',
        'user_id' => $otherDriverUser->id,
    ]);

    Vehicle::factory()->create(['plate_number' => 'SEARCH-PLATE', 'model' => 'Presentation Taxi']);
    Vehicle::factory()->create(['plate_number' => 'HIDDEN-PLATE', 'model' => 'Hidden Taxi']);
    $matchingDriver->score()->firstOrFail()->update(['score' => 42]);

    $this->actingAs($admin)
        ->get(route('admin.users.index', ['q' => 'Searchable Monitor', 'role' => 'monitor']))
        ->assertSuccessful()
        ->assertSeeText($matchingUser->name)
        ->assertDontSeeText($otherUser->name);

    $this->actingAs($admin)
        ->get(route('admin.drivers.index', ['q' => 'SEARCH-LICENSE', 'profile' => 'complete']))
        ->assertSuccessful()
        ->assertSeeText('Searchable Driver')
        ->assertDontSeeText('Hidden Driver');

    $this->actingAs($admin)
        ->get(route('admin.vehicles.index', ['q' => 'SEARCH-PLATE', 'assignment' => 'unassigned']))
        ->assertSuccessful()
        ->assertSeeText('SEARCH-PLATE')
        ->assertDontSeeText('HIDDEN-PLATE');

    $this->actingAs($admin)
        ->get(route('safety-scores.index', ['q' => 'Searchable Driver', 'score_band' => 'risk']))
        ->assertSuccessful()
        ->assertSeeText('Searchable Driver')
        ->assertDontSeeText('Hidden Driver');
});

test('table filters auto apply and preserve reset and export links', function (): void {
    $admin = createUserWithRole('admin');
    $driverUser = createUserWithRole('driver', ['name' => 'Auto Filter Driver']);
    $driver = Driver::factory()->create([
        'license_number' => 'AUTO-FILTER-LICENSE',
        'user_id' => $driverUser->id,
    ]);

    Vehicle::factory()->create(['plate_number' => 'AUTO-FILTER-PLATE']);
    Incident::factory()->create([
        'description' => 'AUTO_FILTER_INCIDENT',
        'driver_id' => $driver->id,
        'reported_by' => $driverUser->id,
    ]);

    $pages = [
        route('admin.users.index', ['q' => 'AUTO']) => route('admin.users.index'),
        route('admin.drivers.index', ['q' => 'AUTO']) => route('admin.drivers.index'),
        route('admin.vehicles.index', ['q' => 'AUTO']) => route('admin.vehicles.index'),
        route('incidents.index', ['q' => 'AUTO']) => route('incidents.index'),
        route('safety-scores.index', ['q' => 'AUTO']) => route('safety-scores.index'),
    ];

    foreach ($pages as $filteredUrl => $resetUrl) {
        $this->actingAs($admin)
            ->get($filteredUrl)
            ->assertSuccessful()
            ->assertSee('data-auto-filter', false)
            ->assertSee($resetUrl, false)
            ->assertSeeText('Reset')
            ->assertDontSeeText('Apply filters');
    }

    $this->actingAs($admin)
        ->get(route('admin.drivers.index', ['q' => 'AUTO']))
        ->assertSee(route('admin.drivers.export', ['q' => 'AUTO']), false);

    $this->actingAs($admin)
        ->get(route('admin.vehicles.index', ['q' => 'AUTO']))
        ->assertSee(route('admin.vehicles.export', ['q' => 'AUTO']), false);

    $this->actingAs($admin)
        ->get(route('incidents.index', ['q' => 'AUTO']))
        ->assertSee(route('incidents.export', ['q' => 'AUTO']), false);

    $this->actingAs($admin)
        ->get(route('safety-scores.index', ['q' => 'AUTO']))
        ->assertSee(route('safety-scores.export', ['q' => 'AUTO']), false);
});

test('incidents receive default severity and staff can filter by severity', function (): void {
    $admin = createUserWithRole('admin');
    $driverUser = createUserWithRole('driver');
    $driver = Driver::factory()->create(['user_id' => $driverUser->id]);
    $crash = Incident::factory()->create([
        'description' => 'Searchable crash incident',
        'driver_id' => $driver->id,
        'reported_by' => $driverUser->id,
        'type' => Incident::TYPE_CRASH,
    ]);
    $complaint = Incident::factory()->create([
        'description' => 'Hidden complaint incident',
        'driver_id' => $driver->id,
        'reported_by' => $driverUser->id,
        'type' => Incident::TYPE_COMPLAINT,
    ]);

    expect($crash->refresh()->severity)->toBe(Incident::SEVERITY_HIGH);
    expect($complaint->refresh()->severity)->toBe(Incident::SEVERITY_LOW);

    $this->actingAs($admin)
        ->get(route('incidents.index', ['severity' => Incident::SEVERITY_HIGH]))
        ->assertSuccessful()
        ->assertSeeText('Searchable crash incident')
        ->assertSeeText('High')
        ->assertDontSeeText('Hidden complaint incident');
});

test('csv exports preserve filters and deny unauthorized staff exports', function (): void {
    $admin = createUserWithRole('admin');
    $driverUser = createUserWithRole('driver');
    $driver = Driver::factory()->create(['user_id' => $driverUser->id]);
    Incident::factory()->create([
        'description' => 'Export matching incident',
        'driver_id' => $driver->id,
        'reported_by' => $driverUser->id,
    ]);
    Incident::factory()->create([
        'description' => 'Export hidden incident',
        'driver_id' => $driver->id,
        'reported_by' => $driverUser->id,
    ]);

    $response = $this->actingAs($admin)
        ->get(route('incidents.export', ['q' => 'matching']));

    $response
        ->assertSuccessful()
        ->assertDownload('incidents.csv');

    $csv = $response->streamedContent();

    expect($csv)
        ->toContain('Export matching incident')
        ->not->toContain('Export hidden incident');

    $this->actingAs($driverUser)
        ->get(route('admin.drivers.export'))
        ->assertForbidden();
});

test('dashboard notification panels render derived operational alerts', function (): void {
    $admin = createUserWithRole('admin');
    $monitor = createUserWithRole('monitor');
    $driverUser = createUserWithRole('driver');
    $driver = Driver::factory()->create(['user_id' => $driverUser->id]);
    $driver->score()->firstOrFail()->update(['score' => 35]);
    $incident = Incident::factory()->create([
        'description' => 'Dashboard alert incident',
        'driver_id' => $driver->id,
        'reported_by' => $driverUser->id,
        'status' => Incident::STATUS_PENDING,
    ]);
    AIAnalysis::factory()->create([
        'incident_id' => $incident->id,
        'status' => AIAnalysis::STATUS_FAILED,
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->get(route('dashboard.admin'))
        ->assertSuccessful()
        ->assertSeeText('Operational signals')
        ->assertSeeText('Low score drivers')
        ->assertSeeText('AI analysis queue');

    $this->actingAs($monitor)
        ->get(route('dashboard.monitor'))
        ->assertSuccessful()
        ->assertSeeText('Monitor attention panel')
        ->assertSeeText('Pending reviews')
        ->assertSeeText('AI follow-up');

    $this->actingAs($driverUser)
        ->get(route('dashboard.driver'))
        ->assertSuccessful()
        ->assertSeeText('Driver attention panel')
        ->assertSeeText('Safety score')
        ->assertSeeText('Pending reports');
});
