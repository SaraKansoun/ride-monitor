<?php

use App\Models\Driver;
use App\Models\Incident;
use App\Models\IncidentReview;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\DriverScoreService;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    seedRolesAndPermissions();
});

test('dashboard route renders role-specific content for each role', function () {
    $admin = createUserWithRole('admin');
    $monitor = createUserWithRole('monitor');
    $driver = createUserWithRole('driver');

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertSeeText('Admin workspace')
        ->assertSeeText('Total active users');

    $this->actingAs($monitor)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertSeeText('Monitor workspace')
        ->assertSeeText('Incidents under review');

    $this->actingAs($driver)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertSeeText('Driver workspace')
        ->assertSeeText('My active incidents');
});

test('admin sees admin dashboard counts excluding inactive records by default', function () {
    $admin = createUserWithRole('admin');
    User::factory()->create();
    User::factory()->inactive()->create();
    $driverUser = createUserWithRole('driver');
    $driver = Driver::factory()->create([
        'user_id' => $driverUser->id,
        'status' => Driver::STATUS_ACTIVE,
    ]);
    Vehicle::factory()->create(['status' => Vehicle::STATUS_ACTIVE]);
    Vehicle::factory()->retired()->create();
    Incident::factory()->create([
        'driver_id' => $driver->id,
        'reported_by' => $driverUser->id,
        'status' => Incident::STATUS_PENDING,
    ]);
    Incident::factory()->create([
        'driver_id' => $driver->id,
        'reported_by' => $driverUser->id,
        'status' => Incident::STATUS_RESOLVED,
    ]);
    Incident::factory()->inactive()->create([
        'driver_id' => $driver->id,
        'reported_by' => $driverUser->id,
    ]);

    $this->actingAs($admin)
        ->get(route('dashboard.admin'))
        ->assertSuccessful()
        ->assertSeeText('Fleet health')
        ->assertSeeText('Latest active incidents')
        ->assertSeeText('Quick actions')
        ->assertSeeText('Manage users')
        ->assertSeeText('Manage drivers')
        ->assertSeeText('Assign vehicles')
        ->assertSeeText('Taxi fleet command center')
        ->assertSeeText('Incidents Over Time')
        ->assertSeeText(now()->format('M j'))
        ->assertSeeTextInOrder(['Total active users', '3'])
        ->assertDontSeeText('Total inactive users')
        ->assertSeeTextInOrder(['Total active drivers', '1'])
        ->assertSeeTextInOrder(['Total active vehicles', '1'])
        ->assertSeeTextInOrder(['Total active incidents', '2'])
        ->assertSeeTextInOrder(['Pending incidents', '1'])
        ->assertSeeTextInOrder(['Resolved incidents', '1']);
});

test('monitor sees incident cards recent incidents and risky active drivers', function () {
    $monitor = createUserWithRole('monitor');
    $riskyDriver = createDashboardDriverProfile('Risky Active Driver');
    $stableDriver = createDashboardDriverProfile('Stable Active Driver');
    $inactiveDriver = createDashboardDriverProfile('Inactive Risk Driver', Driver::STATUS_INACTIVE);
    $riskyDriver->score()->firstOrFail()->update(['score' => 41, 'total_incidents' => 3, 'unsafe_events' => 2]);
    $stableDriver->score()->firstOrFail()->update(['score' => 88, 'total_incidents' => 1, 'unsafe_events' => 0]);
    $inactiveDriver->score()->firstOrFail()->update(['score' => 5, 'total_incidents' => 5, 'unsafe_events' => 3]);

    Incident::factory()->create([
        'driver_id' => $riskyDriver->id,
        'reported_by' => $monitor->id,
        'description' => 'Active pending dashboard incident',
        'status' => Incident::STATUS_PENDING,
    ]);
    Incident::factory()->create([
        'driver_id' => $stableDriver->id,
        'reported_by' => $monitor->id,
        'description' => 'Active under review dashboard incident',
        'status' => Incident::STATUS_UNDER_REVIEW,
    ]);
    Incident::factory()->create([
        'driver_id' => $stableDriver->id,
        'reported_by' => $monitor->id,
        'description' => 'Active resolved dashboard incident',
        'status' => Incident::STATUS_RESOLVED,
    ]);
    Incident::factory()->inactive()->create([
        'driver_id' => $riskyDriver->id,
        'reported_by' => $monitor->id,
        'description' => 'Inactive monitor dashboard incident',
    ]);

    $this->actingAs($monitor)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertSeeText('Monitor workspace')
        ->assertSeeText('Review workload')
        ->assertSeeText('Pending reviews queue')
        ->assertSeeText('Lowest score watchlist')
        ->assertSeeText('Reviews stay monitor-led')
        ->assertSeeText('Incidents Over Time')
        ->assertSeeText(now()->format('M j'))
        ->assertSeeTextInOrder(['Pending incidents', '1'])
        ->assertSeeTextInOrder(['Incidents under review', '1'])
        ->assertSeeTextInOrder(['Resolved incidents', '1'])
        ->assertSeeText('Active pending dashboard incident')
        ->assertSeeText('Active under review dashboard incident')
        ->assertSeeText('Active resolved dashboard incident')
        ->assertDontSeeText('Inactive monitor dashboard incident')
        ->assertSeeText('Risky Active Driver')
        ->assertSeeText('Stable Active Driver')
        ->assertDontSeeText('Inactive Risk Driver');
});

test('monitor recent incident filter supports active inactive and all', function () {
    $monitor = createUserWithRole('monitor');
    $driver = createDashboardDriverProfile('Filter Driver');

    Incident::factory()->create([
        'driver_id' => $driver->id,
        'reported_by' => $monitor->id,
        'description' => 'Filter active dashboard incident',
    ]);
    Incident::factory()->inactive()->create([
        'driver_id' => $driver->id,
        'reported_by' => $monitor->id,
        'description' => 'Filter inactive dashboard incident',
    ]);

    $this->actingAs($monitor)
        ->get(route('dashboard.monitor'))
        ->assertSuccessful()
        ->assertSeeText('Filter active dashboard incident')
        ->assertDontSeeText('Filter inactive dashboard incident');

    $this->actingAs($monitor)
        ->get(route('dashboard.monitor', ['status' => 'inactive']))
        ->assertSuccessful()
        ->assertSeeText('Filter inactive dashboard incident')
        ->assertDontSeeText('Filter active dashboard incident');

    $this->actingAs($monitor)
        ->get(route('dashboard.monitor', ['status' => 'all']))
        ->assertSuccessful()
        ->assertSeeText('Filter active dashboard incident')
        ->assertSeeText('Filter inactive dashboard incident');
});

test('monitor dashboard hides empty optional panels', function () {
    $monitor = createUserWithRole('monitor');

    $this->actingAs($monitor)
        ->get(route('dashboard.monitor'))
        ->assertSuccessful()
        ->assertSeeText('Monitor workspace')
        ->assertSeeText('Reviews stay monitor-led')
        ->assertSeeText('Pending incidents')
        ->assertDontSeeText('Incidents Over Time')
        ->assertDontSeeText('Pending reviews queue')
        ->assertDontSeeText('Lowest score watchlist')
        ->assertDontSeeText('Recent incidents')
        ->assertDontSeeText('Risky active drivers by lowest score')
        ->assertDontSeeText('No pending reviews.')
        ->assertDontSeeText('No active scores yet.')
        ->assertDontSeeText('No incidents found for this filter.')
        ->assertDontSeeText('No active driver scores are available yet.');
});

test('driver sees only own dashboard data and latest active incident status', function () {
    $driverUser = createUserWithRole('driver', ['name' => 'Driver Dashboard Owner']);
    $driver = Driver::factory()->create(['user_id' => $driverUser->id]);
    $otherDriver = createDashboardDriverProfile('Other Dashboard Driver');
    $driver->score()->firstOrFail()->update(['score' => 87]);
    $resolvedTrendIncident = Incident::factory()->create([
        'driver_id' => $driver->id,
        'reported_by' => $driverUser->id,
        'description' => 'Resolved trend crash incident',
        'status' => Incident::STATUS_RESOLVED,
        'type' => Incident::TYPE_CRASH,
        'created_at' => now()->subDays(6),
        'updated_at' => now()->subDays(6),
    ]);
    IncidentReview::factory()->create([
        'fault_decision' => IncidentReview::FAULT_DRIVER,
        'incident_id' => $resolvedTrendIncident->id,
        'reviewed_at' => now()->subDays(5),
    ]);
    $unsafeTrendIncident = Incident::factory()->create([
        'driver_id' => $driver->id,
        'reported_by' => $driverUser->id,
        'description' => 'Resolved trend unsafe incident',
        'status' => Incident::STATUS_RESOLVED,
        'type' => Incident::TYPE_UNSAFE_DRIVING,
        'created_at' => now()->subDays(5),
        'updated_at' => now()->subDays(5),
    ]);
    IncidentReview::factory()->create([
        'fault_decision' => IncidentReview::FAULT_UNCLEAR,
        'incident_id' => $unsafeTrendIncident->id,
        'reviewed_at' => now()->subDays(4),
    ]);
    $inactiveReviewIncident = Incident::factory()->create([
        'driver_id' => $driver->id,
        'reported_by' => $driverUser->id,
        'description' => 'Inactive review trend incident',
        'status' => Incident::STATUS_RESOLVED,
        'type' => Incident::TYPE_COMPLAINT,
    ]);
    IncidentReview::factory()->inactive()->create([
        'incident_id' => $inactiveReviewIncident->id,
        'reviewed_at' => now()->subDays(3),
    ]);
    $inactiveIncident = Incident::factory()->inactive()->create([
        'driver_id' => $driver->id,
        'reported_by' => $driverUser->id,
        'description' => 'Inactive incident trend review',
        'type' => Incident::TYPE_CRASH,
    ]);
    IncidentReview::factory()->create([
        'fault_decision' => IncidentReview::FAULT_DRIVER,
        'incident_id' => $inactiveIncident->id,
        'reviewed_at' => now()->subDays(2),
    ]);
    app(DriverScoreService::class)->recalculateForDriver($driver);

    Incident::factory()->create([
        'driver_id' => $driver->id,
        'reported_by' => $driverUser->id,
        'description' => 'Older active pending own incident',
        'status' => Incident::STATUS_PENDING,
        'created_at' => now()->subDays(3),
        'updated_at' => now()->subDays(3),
    ]);
    Incident::factory()->create([
        'driver_id' => $driver->id,
        'reported_by' => $driverUser->id,
        'description' => 'Latest active resolved own incident',
        'status' => Incident::STATUS_RESOLVED,
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ]);
    Incident::factory()->inactive()->create([
        'driver_id' => $driver->id,
        'reported_by' => $driverUser->id,
        'description' => 'Newest inactive own incident',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    Incident::factory()->create([
        'driver_id' => $otherDriver->id,
        'reported_by' => $otherDriver->user_id,
        'description' => 'Other driver dashboard incident',
    ]);

    $this->actingAs($driverUser)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertSeeText('Driver workspace')
        ->assertSeeText('Focused driver overview')
        ->assertSeeText('Safety score guide')
        ->assertSeeText('Driver Safety Score Trend')
        ->assertSeeText('Start')
        ->assertSeeText(now()->subDays(5)->format('M j'))
        ->assertSeeText(now()->subDays(4)->format('M j'))
        ->assertDontSeeText(now()->subDays(3)->format('M j').': 65')
        ->assertSeeText('My latest incidents')
        ->assertSeeText('Quick actions')
        ->assertSeeTextInOrder(['My active incidents', '5'])
        ->assertSeeTextInOrder(['My resolved incidents', '4'])
        ->assertSeeTextInOrder(['My pending incidents', '1'])
        ->assertSeeTextInOrder(['My current safety score', '70'])
        ->assertSeeText('Latest active resolved own incident')
        ->assertDontSeeText('Newest inactive own incident')
        ->assertDontSeeText('Other driver dashboard incident');
});

test('driver dashboard hides empty optional incident panels', function () {
    $driverUser = createUserWithRole('driver', ['name' => 'Driver Without Incidents']);
    Driver::factory()->create(['user_id' => $driverUser->id]);

    $this->actingAs($driverUser)
        ->get(route('dashboard.driver'))
        ->assertSuccessful()
        ->assertSeeText('Driver workspace')
        ->assertSeeText('Focused driver overview')
        ->assertSeeText('My active incidents')
        ->assertSeeText('Quick actions')
        ->assertDontSeeText('My latest incidents')
        ->assertDontSeeText('Latest incident status')
        ->assertDontSeeText('Latest incident')
        ->assertDontSeeText('No active incidents yet.');
});

test('driver dashboard handles users without a driver profile', function () {
    $driverUser = createUserWithRole('driver');

    $this->actingAs($driverUser)
        ->get(route('dashboard.driver'))
        ->assertSuccessful()
        ->assertSeeText('Driver workspace')
        ->assertSeeText('No driver profile is linked to your account yet.')
        ->assertSeeTextInOrder(['My active incidents', '0'])
        ->assertSeeTextInOrder(['My current safety score', 'N/A']);
});

function createDashboardDriverProfile(string $name, string $status = Driver::STATUS_ACTIVE): Driver
{
    $user = createUserWithRole('driver', ['name' => $name]);

    return Driver::factory()->create([
        'user_id' => $user->id,
        'status' => $status,
    ]);
}
