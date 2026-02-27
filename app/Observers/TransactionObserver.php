<?php

namespace App\Observers;

use App\Models\Transaction;
use App\Enums\TransactionStatus;
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

        // getRawOriginal() bypasses the enum cast and always returns the plain DB string.
        // getOriginal() can return an enum object when strict mode is on, causing (string) cast errors.
        $oldStatus = TransactionStatus::tryFrom((string) $transaction->getRawOriginal('status'));
        $newStatus = $transaction->status; // Already cast to TransactionStatus enum via model cast
        $oldAmount = $transaction->getOriginal('amount');
        $oldType   = $transaction->getOriginal('type');

        DB::transaction(function () use ($transaction, $oldStatus, $newStatus, $oldAmount, $oldType) {
            $branch = \App\Models\Branch::lockForUpdate()->find($transaction->branch_id);

            if (!$branch) {
                return;
            }

            // Case A: Transaction was JUST Rejected — reverse the balance impact
            if ($oldStatus !== TransactionStatus::Rejected && $newStatus === TransactionStatus::Rejected) {
                if ($oldType === 'EXPENSE') {
                    $branch->increment('current_balance', $oldAmount);
                } else {
                    $branch->decrement('current_balance', $oldAmount);
                }
                return;
            }

            // Case B: Transaction was UN-Rejected (e.g. re-approved from rejected state)
            if ($oldStatus === TransactionStatus::Rejected && $newStatus !== TransactionStatus::Rejected) {
                if ($transaction->type === 'EXPENSE') {
                    $branch->decrement('current_balance', $transaction->amount);
                } else {
                    $branch->increment('current_balance', $transaction->amount);
                }
                return;
            }

            // Case C: Standard Edit (Amount/Type change) — skip if currently rejected
            if ($newStatus === TransactionStatus::Rejected) {
                return;
            }

            // Idempotency check: Only update floats if amount or type actually changed
            if ($transaction->isDirty(['amount', 'type'])) {
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

        // The model cast means $transaction->status is already a TransactionStatus enum here.
        if ($transaction->status === TransactionStatus::Rejected) {
            return; // Balance was already reversed at rejection time — don't double-restore
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