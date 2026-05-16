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
