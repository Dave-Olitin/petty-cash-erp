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
        // loadMissing prevents an N+1 when policies are evaluated for a table of items.
        $transactionItem->loadMissing('transaction');
        return $user->isHeadOffice() || $user->branch_id === $transactionItem->transaction->branch_id;
    }

    public function create(User $user): bool
    {
        return $user->isHeadOffice() || $user->branch_id !== null;
    }

    public function update(User $user, TransactionItem $transactionItem): bool
    {
        if ($user->isHeadOffice()) {
            return true;
        }
        $transactionItem->loadMissing('transaction');
        return $user->branch_id === $transactionItem->transaction->branch_id && $transactionItem->transaction->status === 'pending';
    }

    public function delete(User $user, TransactionItem $transactionItem): bool
    {
        if ($user->isHeadOffice()) {
            return true;
        }
        $transactionItem->loadMissing('transaction');
        return $user->branch_id === $transactionItem->transaction->branch_id && $transactionItem->transaction->status === 'pending';
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
