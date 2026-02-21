<?php

namespace App\Observers;

use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransactionObserver
{
    /**
     * Handle the Transaction "created" event.
     * Uses a DB transaction with lock to prevent race conditions on concurrent balance updates.
     * Also performs a server-side balance double-check (TOCTOU guard).
     */
    public function created(Transaction $transaction): void
    {
        if (!$transaction->branch_id) {
            return;
        }

        DB::transaction(function () use ($transaction) {
            $branch = \App\Models\Branch::lockForUpdate()->find($transaction->branch_id);

            if (!$branch) {
                return;
            }

            // TOCTOU guard: Re-check balance AFTER acquiring the lock.
            // The form-level validation happens seconds before this write,
            // so another concurrent request could have already debited the balance.
            if ($transaction->type === 'EXPENSE' && $transaction->amount > $branch->current_balance) {
                // Rollback by soft-deleting this transaction immediately
                $transaction->deleteQuietly();

                throw ValidationException::withMessages([
                    'amount' => "Insufficient funds — the balance was updated by another request. The branch only has AED {$branch->current_balance}.",
                ]);
            }

            if ($transaction->type === 'EXPENSE') {
                $branch->decrement('current_balance', $transaction->amount);
            } else {
                $branch->increment('current_balance', $transaction->amount);
            }
        });
    }

    /**
     * Handle the Transaction "updated" event.
     * Handles all status transitions and amount/type changes.
     */
    public function updated(Transaction $transaction): void
    {
        if (!$transaction->branch_id) {
            return;
        }

        $oldStatus = $transaction->getOriginal('status');
        $newStatus = $transaction->status;
        $oldAmount = $transaction->getOriginal('amount');
        $oldType   = $transaction->getOriginal('type');

        DB::transaction(function () use ($transaction, $oldStatus, $newStatus, $oldAmount, $oldType) {
            $branch = \App\Models\Branch::lockForUpdate()->find($transaction->branch_id);

            if (!$branch) {
                return;
            }

            // Case A: Transaction was JUST Rejected — reverse the balance impact
            if ($oldStatus !== 'rejected' && $newStatus === 'rejected') {
                if ($oldType === 'EXPENSE') {
                    $branch->increment('current_balance', $oldAmount);
                } else {
                    $branch->decrement('current_balance', $oldAmount);
                }
                return;
            }

            // Case B: Transaction was UN-Rejected (e.g. re-approved from rejected state)
            if ($oldStatus === 'rejected' && $newStatus !== 'rejected') {
                if ($transaction->type === 'EXPENSE') {
                    $branch->decrement('current_balance', $transaction->amount);
                } else {
                    $branch->increment('current_balance', $transaction->amount);
                }
                return;
            }

            // Case C: Standard Edit (Amount/Type change) — skip if currently rejected
            if ($newStatus === 'rejected') {
                return;
            }

            // Revert old amount, then apply new amount
            if ($oldType === 'EXPENSE') {
                $branch->increment('current_balance', $oldAmount);
            } else {
                $branch->decrement('current_balance', $oldAmount);
            }

            if ($transaction->type === 'EXPENSE') {
                $branch->decrement('current_balance', $transaction->amount);
            } else {
                $branch->increment('current_balance', $transaction->amount);
            }
        });
    }

    /**
     * Handle the Transaction "deleted" event (Void / Soft Delete).
     * IMPORTANT: If the transaction was already 'rejected', the balance was
     * already reversed when the rejection happened. Reversing again would
     * cause a double-refund bug, so we skip it.
     */
    public function deleted(Transaction $transaction): void
    {
        if (!$transaction->branch_id) {
            return;
        }

        if ($transaction->status === 'rejected') {
            return; // Balance was already reversed at rejection time
        }

        DB::transaction(function () use ($transaction) {
            $branch = \App\Models\Branch::lockForUpdate()->find($transaction->branch_id);

            if (!$branch) {
                return;
            }

            if ($transaction->type === 'EXPENSE') {
                $branch->increment('current_balance', $transaction->amount);
            } else {
                $branch->decrement('current_balance', $transaction->amount);
            }
        });
    }

    /**
     * Handle the Transaction "restored" event (Un-void).
     * Re-applies the transaction effect as if it were just created.
     */
    public function restored(Transaction $transaction): void
    {
        $this->created($transaction);
    }
}