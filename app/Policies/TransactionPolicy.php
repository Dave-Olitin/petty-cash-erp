<?php

namespace App\Policies;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TransactionPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Transaction $transaction): bool
    {
        // HQ can view all. Branch users can only view their own branch's transactions.
        return $user->isHeadOffice() || $user->branch_id === $transaction->branch_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // HQ can always create
        if ($user->isHeadOffice()) {
            return true;
        }

        // Branch user can only create if their branch is active
        return $user->branch && $user->branch->is_active;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Transaction $transaction): bool
    {
        // Prevent editing if it's already approved or rejected, UNLESS it's HQ functionality (maybe they need to correct something?)
        // Let's govern strictly: Approved/Rejected transactions are locked for everyone essentially, unless we add specific "correction" logic.
        // For now: HQ can edit. Branch can edit ONLY if pending.
        
        if ($user->isHeadOffice()) {
            return true;
        }

        // Branch users can ONLY update if it's still pending AND belongs to their branch
        return $user->branch_id === $transaction->branch_id && $transaction->status === 'pending';
    }

    /**
     * Determine whether the user can delete the model.
     * In our context, "Delete" is "Void" (Soft Delete).
     */
    public function delete(User $user, Transaction $transaction): bool
    {
        // HQ can void anything.
        if ($user->isHeadOffice()) {
            return true;
        }

        // Branch users can ONLY void if it's still pending AND belongs to their branch
        return $user->branch_id === $transaction->branch_id && $transaction->status === 'pending';
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Transaction $transaction): bool
    {
        // Only HQ can restore a voided transaction.
        return $user->isHeadOffice();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Transaction $transaction): bool
    {
        // Simply disallow force delete for audit purposes.
        return false;
    }
}
