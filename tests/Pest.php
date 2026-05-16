<?php

use App\Models\User;
use App\Services\PermissionCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

function createUserWithRole(string $role, array $attributes = []): User
{
    seedRolesAndPermissions();

    $user = User::factory()->create($attributes);
    $user->assignRole($role);

    return $user;
}

function seedRolesAndPermissions(): void
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    collect(PermissionCatalog::permissions())->each(
        fn (string $permission) => Permission::findOrCreate($permission)
    );

    collect(PermissionCatalog::rolePermissions())->each(
        fn (array $permissions, string $role) => Role::findOrCreate($role)->syncPermissions($permissions)
    );

    app(PermissionRegistrar::class)->forgetCachedPermissions();
}

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', fn () => $this->toBe(1));

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/
