<?php

namespace App\Observers;

use App\Models\Voucher;

class VoucherObserver
{
    /**
     * Handle the Voucher "creating" event.
     */
    public function creating(Voucher $voucher): void
    {
        $prefix = $voucher->type === 'petty_cash' ? 'PCV-' : 'PAY-';
        $prefix .= date('Y') . '-';
        
        $lock = \Illuminate\Support\Facades\Cache::lock('voucher_number_generation', 5);
        
        try {
            $lock->block(5, function () use ($voucher, $prefix) {
                $latest = Voucher::where('voucher_number', 'like', $prefix . '%')
                    ->orderBy('id', 'desc')
                    ->first();
                    
                if ($latest) {
                    $number = intval(substr($latest->voucher_number, -4)) + 1;
                } else {
                    $number = 1;
                }
                
                $voucher->voucher_number = $prefix . str_pad($number, 4, '0', STR_PAD_LEFT);
            });
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException $e) {
            // Never silently emit a broken/non-sequential voucher number.
            // rand() is not unique and ERR numbers corrupt future sequential lookups.
            // Bubble up so the user sees a proper error and no record is saved.
            throw new \RuntimeException(
                'Could not generate a voucher number due to high system load. Please try again in a moment.'
            );
        }
    }

    /**
     * Handle the Voucher "created" event.
     */
    public function created(Voucher $voucher): void
    {
        //
    }

    /**
     * Handle the Voucher "updated" event.
     */
    public function updated(Voucher $voucher): void
    {
        // If a petty cash voucher was just marked as paid, recalculate the float balance.
        if ($voucher->type === 'petty_cash' && $voucher->wasChanged('status') && $voucher->status === \App\Enums\VoucherStatus::Paid) {

            // Wrap both aggregate queries in a transaction with a raw lock so no concurrent
            // "mark as paid" can slip in between the two SELECTs and produce a wrong balance.
            $currentBalance = \Illuminate\Support\Facades\DB::transaction(function () {
                // Lock the paid petty-cash vouchers for the duration of this read.
                \App\Models\Voucher::where('type', 'petty_cash')
                    ->where('status', \App\Enums\VoucherStatus::Paid->value)
                    ->lockForUpdate()
                    ->get(['id']); // minimal select — we only need the lock

                $totalReplenishing = \App\Models\FloatReplenishment::sum('amount');
                $totalSpent = \App\Models\Voucher::where('type', 'petty_cash')
                    ->where('status', \App\Enums\VoucherStatus::Paid->value)
                    ->sum('amount');

                return (float) $totalReplenishing - (float) $totalSpent;
            });

            // Threshold Check (AED 2000)
            if ($currentBalance < 2000) {
                $managers = \App\Models\User::permission('voucher.manage_float')->get();
                foreach ($managers as $manager) {
                    $manager->notify(new \App\Notifications\LowBalanceNotification((float)$currentBalance));
                }
            }
        }
    }

    /**
     * Handle the Voucher "deleted" event.
     */
    public function deleted(Voucher $voucher): void
    {
        //
    }

    /**
     * Handle the Voucher "restored" event.
     */
    public function restored(Voucher $voucher): void
    {
        //
    }

    /**
     * Handle the Voucher "force deleted" event.
     */
    public function forceDeleted(Voucher $voucher): void
    {
        //
    }
}
