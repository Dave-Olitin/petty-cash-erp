<?php

namespace App\Policies;

use App\Models\ProCategory;
use App\Models\User;

class ProCategoryPolicy
{
    /**
     * Pre-authorization check: Admin and Super Admin roles bypass all authorization checks.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasAnyRole(['Admin', 'Super Admin'])) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('PRO Officer')
            || $user->can('pro_category.view')
            || $user->can('pro_category.manage');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ProCategory $proCategory): bool
    {
        return $user->hasRole('PRO Officer')
            || $user->can('pro_category.view')
            || $user->can('pro_category.manage');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('PRO Officer')
            || $user->can('pro_category.manage');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ProCategory $proCategory): bool
    {
        return $user->hasRole('PRO Officer')
            || $user->can('pro_category.manage');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ProCategory $proCategory): bool
    {
        return $user->hasRole('PRO Officer')
            || $user->can('pro_category.manage');
    }
}
