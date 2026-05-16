<?php

use App\Models\User;
use App\Services\PermissionCatalog;
use Database\Seeders\DatabaseSeeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    seedRolesAndPermissions();
});

test('role names are configured as expected', function () {
    expect(Role::query()->pluck('name')->sort()->values()->all())
        ->toBe(['admin', 'driver', 'monitor']);
});

test('factory users can be assigned expected roles', function (string $role) {
    $user = createUserWithRole($role);

    expect($user->fresh()->hasRole($role))->toBeTrue();
})->with(['admin', 'monitor', 'driver']);

test('roles receive expected permissions', function () {
    expect(Role::findByName('admin')->permissions)->toHaveCount(count(PermissionCatalog::permissions()))
        ->and(Role::findByName('monitor')->hasPermissionTo(PermissionCatalog::REVIEW_INCIDENTS))->toBeTrue()
        ->and(Role::findByName('monitor')->hasPermissionTo(PermissionCatalog::MANAGE_DEACTIVATIONS))->toBeTrue()
        ->and(Role::findByName('driver')->hasPermissionTo(PermissionCatalog::VIEW_OWN_INCIDENTS))->toBeTrue()
        ->and(Role::findByName('driver')->hasPermissionTo(PermissionCatalog::VIEW_INCIDENTS))->toBeFalse();
});

test('database seeder creates test users for each role', function () {
    $this->seed(DatabaseSeeder::class);

    expect(User::where('email', 'admin@example.com')->first()?->hasRole('admin'))->toBeTrue()
        ->and(User::where('email', 'monitor@example.com')->first()?->hasRole('monitor'))->toBeTrue()
        ->and(User::where('email', 'driver@example.com')->first()?->hasRole('driver'))->toBeTrue();
});
