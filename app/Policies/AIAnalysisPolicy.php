<?php

namespace App\Policies;

use App\Models\AIAnalysis;
use App\Models\Driver;
use App\Models\Incident;
use App\Models\User;
use App\Services\PermissionCatalog;

class AIAnalysisPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionCatalog::VIEW_AI_ANALYSES);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, AIAnalysis $aIAnalysis): bool
    {
        if ($user->can(PermissionCatalog::VIEW_AI_ANALYSES)) {
            return true;
        }

        $driver = $user->driverProfile;
        $incident = $aIAnalysis->incident;

        return $user->can(PermissionCatalog::VIEW_OWN_INCIDENTS)
            && $driver instanceof Driver
            && $incident instanceof Incident
            && $incident->driver_id === $driver->id
            && $aIAnalysis->isActive();
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
    public function update(User $user, AIAnalysis $aIAnalysis): bool
    {
        return false;
    }

    public function deactivate(User $user, AIAnalysis $aIAnalysis): bool
    {
        return $user->can(PermissionCatalog::VIEW_AI_ANALYSES)
            && $user->can(PermissionCatalog::MANAGE_DEACTIVATIONS);
    }

    public function reactivate(User $user, AIAnalysis $aIAnalysis): bool
    {
        return $user->can(PermissionCatalog::VIEW_AI_ANALYSES)
            && $user->can(PermissionCatalog::MANAGE_DEACTIVATIONS);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, AIAnalysis $aIAnalysis): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, AIAnalysis $aIAnalysis): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, AIAnalysis $aIAnalysis): bool
    {
        return false;
    }
}
