<?php

namespace App\Policies;

use App\Models\TransactionItem;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TransactionItemPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, TransactionItem $transactionItem): bool
    {
        return $user->isHeadOffice() || $user->branch_id === $transactionItem->transaction->branch_id;
    }

    public function create(User $user): bool
    {
        // Created via Transaction
        return false; 
    }

    public function update(User $user, TransactionItem $transactionItem): bool
    {
         // Updates handled via Transaction
         return false;
    }

    public function delete(User $user, TransactionItem $transactionItem): bool
    {
         // Deletes handled via Transaction
         return false;
    }

    public function restore(User $user, TransactionItem $transactionItem): bool
    {
        return false;
    }

    public function forceDelete(User $user, TransactionItem $transactionItem): bool
    {
        return false;
    }
}
