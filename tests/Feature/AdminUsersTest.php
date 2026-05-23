<?php

use App\Models\User;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    seedRolesAndPermissions();
});

test('admin can create and edit users', function () {
    $admin = createUserWithRole('admin');

    $this->actingAs($admin)
        ->post(route('admin.users.store'), [
            'name' => 'Fleet Monitor',
            'email' => 'fleet.monitor@example.com',
            'password' => 'password',
            'role' => 'monitor',
            'status' => User::STATUS_ACTIVE,
        ])
        ->assertRedirect();

    $user = User::where('email', 'fleet.monitor@example.com')->firstOrFail();

    expect($user->hasRole('monitor'))->toBeTrue()
        ->and($user->status)->toBe(User::STATUS_ACTIVE);

    $this->actingAs($admin)
        ->patch(route('admin.users.update', $user), [
            'name' => 'Safety Monitor',
            'email' => 'safety.monitor@example.com',
            'password' => '',
            'role' => 'admin',
            'status' => User::STATUS_ACTIVE,
        ])
        ->assertRedirect(route('admin.users.show', $user));

    $user->refresh();

    expect($user->name)->toBe('Safety Monitor')
        ->and($user->email)->toBe('safety.monitor@example.com')
        ->and($user->hasRole('admin'))->toBeTrue();
});

test('admin creates driver accounts through users module before completing profiles', function () {
    $admin = createUserWithRole('admin');

    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertSuccessful()
        ->assertSeeText('Create user');

    $this->actingAs($admin)
        ->post(route('admin.users.store'), [
            'name' => 'Driver From Users',
            'email' => 'driver.from.users@example.com',
            'password' => 'password',
            'role' => 'driver',
            'status' => User::STATUS_ACTIVE,
        ])
        ->assertRedirect();

    $driverUser = User::where('email', 'driver.from.users@example.com')->firstOrFail();

    $this->actingAs($admin)
        ->get(route('admin.drivers.index'))
        ->assertSuccessful()
        ->assertSeeText('Driver From Users')
        ->assertSeeText('Missing profile')
        ->assertSeeText('Complete profile');

    expect($driverUser->hasRole('driver'))->toBeTrue();
});

test('admin can deactivate and reactivate users', function () {
    $admin = createUserWithRole('admin');
    $monitor = createUserWithRole('monitor');

    $this->actingAs($admin)
        ->patch(route('admin.users.deactivate', $monitor))
        ->assertRedirect();

    expect($monitor->fresh()->status)->toBe(User::STATUS_INACTIVE);

    $this->actingAs($admin)
        ->patch(route('admin.users.reactivate', $monitor))
        ->assertRedirect();

    expect($monitor->fresh()->status)->toBe(User::STATUS_ACTIVE);
});

test('users table removes deactivation shortcut but keeps reactivation action', function () {
    $admin = createUserWithRole('admin');
    $activeUser = createUserWithRole('monitor', ['email' => 'active.user@example.com']);
    $inactiveUser = createUserWithRole('monitor', [
        'email' => 'inactive.user@example.com',
        'status' => User::STATUS_INACTIVE,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.users.index', ['status' => 'all']))
        ->assertSuccessful()
        ->assertSeeText($activeUser->email)
        ->assertSeeText($inactiveUser->email)
        ->assertDontSee(route('admin.users.deactivate', $activeUser), false)
        ->assertDontSeeText('Deactivate')
        ->assertSeeText('Reactivate');
});

test('admin can deactivate another admin through user edit status workflow', function () {
    $admin = createUserWithRole('admin');
    $otherAdmin = createUserWithRole('admin', [
        'email' => 'other.admin@example.com',
        'name' => 'Other Admin',
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.users.update', $otherAdmin), [
            'name' => 'Other Admin',
            'email' => 'other.admin@example.com',
            'password' => '',
            'role' => 'admin',
            'status' => User::STATUS_INACTIVE,
        ])
        ->assertRedirect(route('admin.users.show', $otherAdmin));

    $otherAdmin->refresh();

    expect($otherAdmin->status)->toBe(User::STATUS_INACTIVE)
        ->and($otherAdmin->isActive())->toBeFalse();
});

test('deactivated users cannot log in', function () {
    createUserWithRole('driver', [
        'email' => 'inactive.driver@example.com',
        'password' => 'password',
        'status' => User::STATUS_INACTIVE,
    ]);

    $this->post(route('login.store'), [
        'email' => 'inactive.driver@example.com',
        'password' => 'password',
    ])->assertInvalid(['email']);

    $this->assertGuest();
});

test('admin cannot deactivate self', function () {
    $admin = createUserWithRole('admin');

    $this->actingAs($admin)
        ->from(route('admin.users.index'))
        ->patch(route('admin.users.deactivate', $admin))
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHasErrors('user');

    expect($admin->fresh()->status)->toBe(User::STATUS_ACTIVE);
});
