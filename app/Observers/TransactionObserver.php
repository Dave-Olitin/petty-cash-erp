<?php

namespace App\Observers;

use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use App\Models\User;
use App\Notifications\TransactionStatusNotification;

class TransactionObserver
{
    /**
     * Helper to calculate the financial impact of a transaction on the branch balance.
     */
    private function calculateBalanceImpact(string $type, ?string $status, float $amount): float
    {
        $status = $status ?? 'pending';
        
        if ($status === 'rejected') {
            return 0.0;
        }

        if ($type === 'EXPENSE') {
            // Expenses deduct from balance as long as they are pending or approved.
            // This reserves the funds immediately.
            return -$amount;
        }

        if ($type === 'REPLENISHMENT') {
            // As requested, replenishments add to balance immediately, even if pending
            return $amount;
        }

        return 0.0;
    }

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

            $impact = $this->calculateBalanceImpact($transaction->type, $transaction->status, $transaction->amount);

            // TOCTOU guard: Re-check balance AFTER acquiring the lock.
            if ($impact < 0 && !$branch->allow_overdraft && abs($impact) > $branch->current_balance) {
                $transaction->deleteQuietly();

                throw ValidationException::withMessages([
                    'amount' => "Insufficient funds — the balance was updated by another request. The branch only has AED {$branch->current_balance}.",
                ]);
            }

            if ($impact != 0) {
                $branch->increment('current_balance', $impact);
            }
        });

        // Notifications
        try {
            if ($transaction->status === 'pending') {
                $headOfficeUsers = User::whereNull('branch_id')->get();
                Notification::send($headOfficeUsers, new TransactionStatusNotification($transaction, 'created'));
            } elseif ($transaction->type === 'REPLENISHMENT' && $transaction->status === 'approved') {
                $branchUsers = User::where('branch_id', $transaction->branch_id)->get();
                if ($branchUsers->isNotEmpty()) {
                    Notification::send($branchUsers, new TransactionStatusNotification($transaction, 'created'));
                }
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Transaction created notification failed: ' . $e->getMessage());
        }
    }

    public function updated(Transaction $transaction): void
    {
        if (!$transaction->branch_id) {
            return;
        }

        if (!$transaction->isDirty('amount', 'type', 'status')) {
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

            $oldImpact = $this->calculateBalanceImpact($oldType, $oldStatus, $oldAmount);
            $newImpact = $this->calculateBalanceImpact($transaction->type, $transaction->status, $transaction->amount);

            $impactDifference = $newImpact - $oldImpact;

            if ($impactDifference == 0) {
                return;
            }

            $availableBalance = $branch->current_balance - $oldImpact;

            if ($newImpact < 0 && !$branch->allow_overdraft && abs($newImpact) > $availableBalance) {
                throw ValidationException::withMessages([
                    'amount' => "Insufficient funds — the balance was updated by another request. The branch only has an available balance of AED {$availableBalance} for this transaction.",
                ]);
            }

            $branch->increment('current_balance', $impactDifference);
        });

        try {
            if ($oldStatus === 'pending' && $newStatus === 'approved') {
                $transaction->user->notify(new TransactionStatusNotification($transaction, 'approved'));
                
                if ($transaction->type === 'REPLENISHMENT') {
                    $branchUsers = User::where('branch_id', $transaction->branch_id)
                        ->where('id', '!=', $transaction->user_id)
                        ->get();
                    if ($branchUsers->isNotEmpty()) {
                        Notification::send($branchUsers, new TransactionStatusNotification($transaction, 'approved'));
                    }
                }
            } elseif ($oldStatus === 'pending' && $newStatus === 'rejected') {
                $transaction->user->notify(new TransactionStatusNotification($transaction, 'rejected'));
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Transaction status notification failed: ' . $e->getMessage());
        }
    }

    public function deleted(Transaction $transaction): void
    {
        if (!$transaction->branch_id) {
            return;
        }

        DB::transaction(function () use ($transaction) {
            $branch = \App\Models\Branch::lockForUpdate()->find($transaction->branch_id);

            if (!$branch) {
                return;
            }

            $impact = $this->calculateBalanceImpact($transaction->type, $transaction->status, $transaction->amount);

            if ($impact != 0) {
                $branch->decrement('current_balance', $impact);
            }
        });
    }

    public function restored(Transaction $transaction): void
    {
        $this->created($transaction);
    }
}