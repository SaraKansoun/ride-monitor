<?php

namespace App\Policies;

use App\Models\Driver;
use App\Models\Incident;
use App\Models\IncidentMedia;
use App\Models\User;
use App\Services\PermissionCatalog;

class IncidentMediaPolicy
{
    public function view(User $user, IncidentMedia $incidentMedia): bool
    {
        if (! $incidentMedia->isActive()) {
            return false;
        }

        $driver = $user->driverProfile;
        $incident = $incidentMedia->incident;

        if (! $incident instanceof Incident) {
            return false;
        }

        if ($user->can(PermissionCatalog::VIEW_INCIDENTS)) {
            return true;
        }

        return $user->can(PermissionCatalog::VIEW_OWN_INCIDENTS)
            && $driver instanceof Driver
            && $incident->driver_id === $driver->id;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, IncidentMedia $incidentMedia): bool
    {
        return false;
    }

    public function deactivate(User $user, IncidentMedia $incidentMedia): bool
    {
        return $user->can(PermissionCatalog::VIEW_INCIDENTS)
            && $user->can(PermissionCatalog::MANAGE_DEACTIVATIONS);
    }

    public function reactivate(User $user, IncidentMedia $incidentMedia): bool
    {
        return $user->can(PermissionCatalog::VIEW_INCIDENTS)
            && $user->can(PermissionCatalog::MANAGE_DEACTIVATIONS);
    }

    public function delete(User $user, IncidentMedia $incidentMedia): bool
    {
        return false;
    }

    public function restore(User $user, IncidentMedia $incidentMedia): bool
    {
        return false;
    }

    public function forceDelete(User $user, IncidentMedia $incidentMedia): bool
    {
        return false;
    }
}
