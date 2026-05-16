<?php

use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    seedRolesAndPermissions();
});

test('login page does not render the sidebar', function () {
    $this->get(route('login'))
        ->assertSuccessful()
        ->assertSee('Sign in')
        ->assertDontSee('Dashboard')
        ->assertDontSee('Assignments');
});

test('admin can access all admin modules', function () {
    $admin = createUserWithRole('admin');

    foreach ([
        route('admin.users.index'),
        route('admin.drivers.index'),
        route('admin.vehicles.index'),
        route('admin.assignments.index'),
    ] as $url) {
        $this->actingAs($admin)->get($url)->assertSuccessful();
    }
});

test('non admin users are forbidden from admin modules', function (string $role) {
    $user = createUserWithRole($role);

    $this->actingAs($user)
        ->get(route('admin.users.index'))
        ->assertForbidden();
})->with(['monitor', 'driver']);
