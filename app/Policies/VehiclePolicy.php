<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vehicle;
use App\Services\PermissionCatalog;

class VehiclePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionCatalog::VIEW_VEHICLES);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Vehicle $vehicle): bool
    {
        return $user->can(PermissionCatalog::VIEW_VEHICLES);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can(PermissionCatalog::MANAGE_VEHICLES);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Vehicle $vehicle): bool
    {
        return $user->can(PermissionCatalog::MANAGE_VEHICLES);
    }

    public function deactivate(User $user, Vehicle $vehicle): bool
    {
        return $user->can(PermissionCatalog::MANAGE_VEHICLES)
            && $user->can(PermissionCatalog::MANAGE_DEACTIVATIONS);
    }

    public function reactivate(User $user, Vehicle $vehicle): bool
    {
        return $user->can(PermissionCatalog::MANAGE_VEHICLES)
            && $user->can(PermissionCatalog::MANAGE_DEACTIVATIONS);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Vehicle $vehicle): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Vehicle $vehicle): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Vehicle $vehicle): bool
    {
        return false;
    }
}
