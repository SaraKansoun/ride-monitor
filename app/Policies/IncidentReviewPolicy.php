<?php

namespace App\Policies;

use App\Models\Driver;
use App\Models\Incident;
use App\Models\IncidentReview;
use App\Models\User;
use App\Services\PermissionCatalog;

class IncidentReviewPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionCatalog::REVIEW_INCIDENTS);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, IncidentReview $incidentReview): bool
    {
        if ($user->can(PermissionCatalog::REVIEW_INCIDENTS)) {
            return true;
        }

        $driver = $user->driverProfile;
        $incident = $incidentReview->incident;

        return $user->can(PermissionCatalog::VIEW_OWN_INCIDENTS)
            && $driver instanceof Driver
            && $incident instanceof Incident
            && $incident->driver_id === $driver->id
            && $incidentReview->isActive();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can(PermissionCatalog::REVIEW_INCIDENTS);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, IncidentReview $incidentReview): bool
    {
        return false;
    }

    /**
     * Determine whether the user can deactivate the model.
     */
    public function deactivate(User $user, IncidentReview $incidentReview): bool
    {
        return $user->can(PermissionCatalog::REVIEW_INCIDENTS)
            && $user->can(PermissionCatalog::MANAGE_DEACTIVATIONS);
    }

    /**
     * Determine whether the user can reactivate the model.
     */
    public function reactivate(User $user, IncidentReview $incidentReview): bool
    {
        return $user->can(PermissionCatalog::REVIEW_INCIDENTS)
            && $user->can(PermissionCatalog::MANAGE_DEACTIVATIONS);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, IncidentReview $incidentReview): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, IncidentReview $incidentReview): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, IncidentReview $incidentReview): bool
    {
        return false;
    }
}
