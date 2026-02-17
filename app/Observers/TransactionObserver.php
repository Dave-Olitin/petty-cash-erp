<?php

namespace App\Observers;

use App\Models\Transaction;
use Filament\Notifications\Notification;

class TransactionObserver
{
    /**
     * Handle the Transaction "created" event.
     */
    public function created(Transaction $transaction): void
    {
        $branch = $transaction->branch;

        if (!$branch) {
            return;
        }

        if ($transaction->type === 'EXPENSE') {
            // Money leaving the wallet
            $branch->decrement('current_balance', $transaction->amount);
        } else {
            // Money entering the wallet (Replenishment)
            $branch->increment('current_balance', $transaction->amount);
        }
    }

    /**
     * Handle the Transaction "deleted" event.
     * (We reverse the logic here to fix mistakes)
     */
    /**
     * Handle the Transaction "updated" event.
     */
    public function updated(Transaction $transaction): void
    {
        $branch = $transaction->branch;

        if (!$branch) {
            return;
        }

        // Logic:
        // 1. If status changed TO 'rejected' -> Reverse the transaction (Refund money).
        // 2. If status changed FROM 'rejected' -> Apply the transaction.
        // 3. If standard update (amount/type changed) AND status is NOT 'rejected' -> Adjust difference.

        $oldStatus = $transaction->getOriginal('status');
        $newStatus = $transaction->status;
        $oldAmount = $transaction->getOriginal('amount');
        $oldType = $transaction->getOriginal('type');

        // Case A: Transaction was JUST Rejected
        if ($oldStatus !== 'rejected' && $newStatus === 'rejected') {
            // Reverse the OLD amount benefit/cost
            if ($oldType === 'EXPENSE') {
                $branch->increment('current_balance', $oldAmount);
            } else {
                $branch->decrement('current_balance', $oldAmount);
            }
            return; // Done
        }

        // Case B: Transaction was JUST Un-Rejected (e.g. Approved again)
        if ($oldStatus === 'rejected' && $newStatus !== 'rejected') {
            // Apply the NEW amount benefit/cost
            if ($transaction->type === 'EXPENSE') {
                $branch->decrement('current_balance', $transaction->amount);
            } else {
                $branch->increment('current_balance', $transaction->amount);
            }
            return; // Done
        }

        // Case C: Standard Edit (Amount/Type change) - BUT IGNORE if currently rejected
        if ($newStatus === 'rejected') {
            return; // Do nothing if we are editing a rejected record (it shouldn't affect balance)
        }

        // 1. Revert Old Amount
        if ($oldType === 'EXPENSE') {
            $branch->increment('current_balance', $oldAmount);
        } else {
            $branch->decrement('current_balance', $oldAmount);
        }

        // 2. Apply New Amount
        if ($transaction->type === 'EXPENSE') {
            $branch->decrement('current_balance', $transaction->amount);
        } else {
            $branch->increment('current_balance', $transaction->amount);
        }
    }

    /**
     * Handle the Transaction "deleted" event.
     */
    /**
     * Handle the Transaction "restored" event.
     */
    public function restored(Transaction $transaction): void
    {
        // If a transaction is restored (un-voided), we need to re-apply its effect.
        // This is essentially the same as "created" logic.
        $this->created($transaction);
    }
    
    /**
     * Handle the Transaction "deleted" event.
     */
    public function deleted(Transaction $transaction): void
    {
        $branch = $transaction->branch;

        if (!$branch) {
            return;
        }

        if ($transaction->type === 'EXPENSE') {
            // We deleted an expense, so put the money back
            $branch->increment('current_balance', $transaction->amount);
        } else {
            // We deleted a replenishment, so remove the money
            $branch->decrement('current_balance', $transaction->amount);
        }
    }
}