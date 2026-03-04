<?php

namespace App\Observers;

use App\Enums\VoucherStatus;
use App\Models\Voucher;

class VoucherObserver
{
    /**
     * Handle the Voucher "creating" event.
     */
    public function creating(Voucher $voucher): void
    {
        // Use template prefix if set, otherwise fall back to type-based prefix
        if ($voucher->voucher_template_id) {
            $template = \App\Models\VoucherTemplate::find($voucher->voucher_template_id);
            $prefix = $template ? $template->prefix . '-' : ($voucher->type === 'petty_cash' ? 'PCV-' : 'PAY-');
        } else {
            $prefix = $voucher->type === 'petty_cash' ? 'PCV-' : 'PAY-';
        }

        $lock = \Illuminate\Support\Facades\Cache::lock('voucher_number_generation', 5);

        try {
            $lock->block(5, function () use ($voucher, $prefix) {
                $latest = Voucher::withTrashed()
                    ->where('voucher_number', 'like', $prefix . '%')
                    ->orderBy('id', 'desc')
                    ->first();

                if ($latest) {
                    $number = intval(substr($latest->voucher_number, strlen($prefix))) + 1;
                } else {
                    $number = 1;
                }

                $voucher->voucher_number = $prefix . str_pad($number, 4, '0', STR_PAD_LEFT);
            });
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException $e) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'voucher_template_id' => 'Could not generate a voucher number due to high system load. Please try again in a moment.'
            ]);
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
        // If a petty cash voucher was just marked as paid, check the head-office float balance.
        if ($voucher->type === 'petty_cash' && $voucher->wasChanged('status') && $voucher->status === VoucherStatus::Paid->value) {

            // Calculate balance directly without a nested transaction in the observer event cycle
            // which can cause issues or deadlock if the caller is already in a transaction.
            $totalReplenishing = \App\Models\FloatReplenishment::sum('amount');
            $totalSpent = \App\Models\Voucher::where('type', 'petty_cash')
                ->where('status', VoucherStatus::Paid->value)
                ->sum('amount');

            $currentBalance = (float) $totalReplenishing - (float) $totalSpent;

            // Threshold Check (AED 2000)
            if ($currentBalance < 2000) {
                // Dispatch notifications (this already behaves asynchronously if notifications are queued)
                $managers = \App\Models\User::permission('voucher.manage_float')->get();
                foreach ($managers as $manager) {
                    $manager->notify(new \App\Notifications\LowBalanceNotification($currentBalance));
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
