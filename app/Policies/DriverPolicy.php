<?php

namespace App\Policies;

use App\Models\Driver;
use App\Models\User;
use App\Services\PermissionCatalog;

class DriverPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionCatalog::VIEW_DRIVERS);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Driver $driver): bool
    {
        return $user->can(PermissionCatalog::VIEW_DRIVERS);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can(PermissionCatalog::MANAGE_DRIVERS);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Driver $driver): bool
    {
        return $user->can(PermissionCatalog::MANAGE_DRIVERS);
    }

    public function deactivate(User $user, Driver $driver): bool
    {
        return $user->can(PermissionCatalog::MANAGE_DRIVERS)
            && $user->can(PermissionCatalog::MANAGE_DEACTIVATIONS);
    }

    public function reactivate(User $user, Driver $driver): bool
    {
        return $user->can(PermissionCatalog::MANAGE_DRIVERS)
            && $user->can(PermissionCatalog::MANAGE_DEACTIVATIONS);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Driver $driver): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Driver $driver): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Driver $driver): bool
    {
        return false;
    }
}
