<?php

namespace App\Policies;

use App\Models\Driver;
use App\Models\DriverScore;
use App\Models\User;
use App\Services\PermissionCatalog;

class DriverScorePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionCatalog::VIEW_SAFETY_SCORES);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, DriverScore $driverScore): bool
    {
        if ($user->can(PermissionCatalog::VIEW_SAFETY_SCORES)) {
            return true;
        }

        $driver = $user->driverProfile;

        return $user->can(PermissionCatalog::VIEW_OWN_SAFETY_SCORE)
            && $driver instanceof Driver
            && $driverScore->driver_id === $driver->id
            && $driverScore->isActive();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, DriverScore $driverScore): bool
    {
        return false;
    }

    public function deactivate(User $user, DriverScore $driverScore): bool
    {
        return $user->can(PermissionCatalog::VIEW_SAFETY_SCORES)
            && $user->can(PermissionCatalog::MANAGE_DEACTIVATIONS);
    }

    public function reactivate(User $user, DriverScore $driverScore): bool
    {
        return $user->can(PermissionCatalog::VIEW_SAFETY_SCORES)
            && $user->can(PermissionCatalog::MANAGE_DEACTIVATIONS);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, DriverScore $driverScore): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, DriverScore $driverScore): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, DriverScore $driverScore): bool
    {
        return false;
    }
}
