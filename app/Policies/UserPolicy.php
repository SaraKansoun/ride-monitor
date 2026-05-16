<?php

namespace App\Policies;

use App\Models\User;
use App\Services\PermissionCatalog;

class UserPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionCatalog::VIEW_USERS);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        return $user->can(PermissionCatalog::VIEW_USERS);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can(PermissionCatalog::MANAGE_USERS);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model): bool
    {
        return $user->can(PermissionCatalog::MANAGE_USERS);
    }

    public function deactivate(User $user, User $model): bool
    {
        return $user->can(PermissionCatalog::MANAGE_USERS)
            && $user->can(PermissionCatalog::MANAGE_DEACTIVATIONS);
    }

    public function reactivate(User $user, User $model): bool
    {
        return $user->can(PermissionCatalog::MANAGE_USERS)
            && $user->can(PermissionCatalog::MANAGE_DEACTIVATIONS);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $model): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, User $model): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, User $model): bool
    {
        return false;
    }
}
