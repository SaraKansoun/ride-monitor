<?php

namespace App\Policies;

use App\Models\Driver;
use App\Models\Incident;
use App\Models\User;
use App\Services\PermissionCatalog;

class IncidentPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionCatalog::VIEW_INCIDENTS)
            || $user->can(PermissionCatalog::VIEW_OWN_INCIDENTS);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Incident $incident): bool
    {
        if ($user->can(PermissionCatalog::VIEW_INCIDENTS)) {
            return true;
        }

        $driver = $user->driverProfile;

        return $user->can(PermissionCatalog::VIEW_OWN_INCIDENTS)
            && $driver instanceof Driver
            && $incident->driver_id === $driver->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        $driver = $user->driverProfile;

        return $user->can(PermissionCatalog::CREATE_INCIDENTS)
            && $driver instanceof Driver
            && $driver->isActive();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Incident $incident): bool
    {
        return $user->can(PermissionCatalog::VIEW_INCIDENTS)
            && $incident->isActive()
            && in_array($incident->status, [Incident::STATUS_PENDING, Incident::STATUS_UNDER_REVIEW], true);
    }

    /**
     * Determine whether the user can deactivate the model.
     */
    public function deactivate(User $user, Incident $incident): bool
    {
        return $user->can(PermissionCatalog::VIEW_INCIDENTS)
            && $user->can(PermissionCatalog::MANAGE_DEACTIVATIONS);
    }

    /**
     * Determine whether the user can reactivate the model.
     */
    public function reactivate(User $user, Incident $incident): bool
    {
        return $user->can(PermissionCatalog::VIEW_INCIDENTS)
            && $user->can(PermissionCatalog::MANAGE_DEACTIVATIONS);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Incident $incident): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Incident $incident): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Incident $incident): bool
    {
        return false;
    }
}
