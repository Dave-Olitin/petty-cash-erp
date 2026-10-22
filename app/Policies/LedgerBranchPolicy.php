<?php

namespace App\Policies;

use App\Models\LedgerBranch;
use App\Models\User;

class LedgerBranchPolicy
{
    /**
     * Pre-authorization check: Admin and Super Admin roles bypass all authorization checks.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($ability === 'delete') {
            return false;
        }

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
            || $user->can('ledger_branch.view')
            || $user->can('ledger_branch.manage');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, LedgerBranch $ledgerBranch): bool
    {
        return $user->hasRole('PRO Officer')
            || $user->can('ledger_branch.view')
            || $user->can('ledger_branch.manage');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('PRO Officer')
            || $user->can('ledger_branch.manage');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, LedgerBranch $ledgerBranch): bool
    {
        return $user->hasRole('PRO Officer')
            || $user->can('ledger_branch.manage');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, LedgerBranch $ledgerBranch): bool
    {
        return false;
    }
}
