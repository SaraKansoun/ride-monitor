<?php

use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    seedRolesAndPermissions();
});

test('each role can access its own protected area', function (string $role, string $routeName, string $expectedText) {
    $user = createUserWithRole($role);

    $this->actingAs($user)
        ->get(route($routeName))
        ->assertSuccessful()
        ->assertSee($expectedText);
})->with([
    'admin' => ['admin', 'dashboard.admin', 'Admin workspace'],
    'monitor' => ['monitor', 'dashboard.monitor', 'Monitor workspace'],
    'driver' => ['driver', 'dashboard.driver', 'Driver workspace'],
]);

test('each role is forbidden from another protected area', function (string $role, string $routeName) {
    $user = createUserWithRole($role);

    $this->actingAs($user)
        ->get(route($routeName))
        ->assertForbidden();
})->with([
    'admin cannot access monitor' => ['admin', 'dashboard.monitor'],
    'admin cannot access driver' => ['admin', 'dashboard.driver'],
    'monitor cannot access admin' => ['monitor', 'dashboard.admin'],
    'monitor cannot access driver' => ['monitor', 'dashboard.driver'],
    'driver cannot access admin' => ['driver', 'dashboard.admin'],
    'driver cannot access monitor' => ['driver', 'dashboard.monitor'],
]);
