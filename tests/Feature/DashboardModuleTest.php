<?php

use App\Models\Driver;
use App\Models\Incident;
use App\Models\User;
use App\Models\Vehicle;
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
        ->assertSeeTextInOrder(['Total active users', '3'])
        ->assertSeeTextInOrder(['Total inactive users', '1'])
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

test('driver sees only own dashboard data and latest active incident status', function () {
    $driverUser = createUserWithRole('driver', ['name' => 'Driver Dashboard Owner']);
    $driver = Driver::factory()->create(['user_id' => $driverUser->id]);
    $otherDriver = createDashboardDriverProfile('Other Dashboard Driver');
    $driver->score()->firstOrFail()->update(['score' => 87]);

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
        ->assertSeeTextInOrder(['My active incidents', '2'])
        ->assertSeeTextInOrder(['My resolved incidents', '1'])
        ->assertSeeTextInOrder(['My pending incidents', '1'])
        ->assertSeeTextInOrder(['My current safety score', '87'])
        ->assertSeeText('Latest active resolved own incident')
        ->assertDontSeeText('Newest inactive own incident')
        ->assertDontSeeText('Other driver dashboard incident');
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
