<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\PermissionCatalog;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        collect(PermissionCatalog::permissions())->each(
            fn (string $permission) => Permission::findOrCreate($permission)
        );

        collect(PermissionCatalog::rolePermissions())->each(
            fn (array $permissions, string $role) => Role::findOrCreate($role)->syncPermissions($permissions)
        );

        $users = [
            ['name' => 'Admin User', 'email' => 'admin@example.com', 'role' => 'admin'],
            ['name' => 'Monitor User', 'email' => 'monitor@example.com', 'role' => 'monitor'],
            ['name' => 'Driver User', 'email' => 'driver@example.com', 'role' => 'driver'],
        ];

        foreach ($users as $userData) {
            $user = User::query()->updateOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'password' => 'password',
                    'status' => User::STATUS_ACTIVE,
                ]
            );

            $user->syncRoles($userData['role']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
