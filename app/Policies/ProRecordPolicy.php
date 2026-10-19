<?php

namespace App\Policies;

use App\Models\ProRecord;
use App\Models\User;

class ProRecordPolicy
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
        return $user->can('pro_record.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ProRecord $proRecord): bool
    {
        return $user->can('pro_record.view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('pro_record.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ProRecord $proRecord): bool
    {
        return $user->can('pro_record.edit');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ProRecord $proRecord): bool
    {
        return $user->can('pro_record.delete');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ProRecord $proRecord): bool
    {
        return $user->can('pro_record.delete');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ProRecord $proRecord): bool
    {
        return $user->hasAnyRole(['Admin', 'Super Admin']);
    }
}
