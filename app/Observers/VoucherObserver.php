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
        
        $latest = Voucher::where('voucher_number', 'like', $prefix . '%')
            ->orderBy('id', 'desc')
            ->first();
            
        if ($latest) {
            $number = intval(substr($latest->voucher_number, -4)) + 1;
        } else {
            $number = 1;
        }
        
        $voucher->voucher_number = $prefix . str_pad($number, 4, '0', STR_PAD_LEFT);
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
        // If a petty cash voucher was just marked as paid, deduct and check the overall hover float balance
        if ($voucher->type === 'petty_cash' && $voucher->wasChanged('status') && $voucher->status === 'paid') {
            
            // Calculate current Head Office Float
            $totalReplenishing = \App\Models\FloatReplenishment::sum('amount');
            $totalSpent = \App\Models\Voucher::where('type', 'petty_cash')
                ->where('status', 'paid')
                ->sum('amount');

            $currentBalance = $totalReplenishing - $totalSpent;

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
