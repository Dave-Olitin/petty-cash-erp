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
        if ($voucher->type === 'petty_cash') {
            $prefix = 'PCV NO: ' . date('y') . '-';
        } elseif ($voucher->type === 'receipt') {
            $prefix = 'RV NO: ';
        } else {
            if ($voucher->voucher_template_id) {
                $template = \App\Models\VoucherTemplate::find($voucher->voucher_template_id);
                $prefix = $template ? $template->prefix . '-' : 'PV NO: ';
            } else {
                $prefix = 'PV NO: ';
            }
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
                    if ($voucher->type === 'petty_cash' && str_starts_with($prefix, 'PCV NO: ')) {
                        $number = 4001; // Starts at PCV NO: 26-04001
                    } elseif ($voucher->type === 'receipt' && str_starts_with($prefix, 'RV NO: ')) {
                        $number = 776;  // Starts at RV NO: 0776
                    } elseif (str_contains($prefix, 'ETC-')) {
                        $number = 1246; // Starts at PV NO: ETC-1246
                    } elseif (str_contains($prefix, 'SB-')) {
                        $number = 216;  // Starts at PV NO: SB-0216
                    } elseif (str_contains($prefix, 'TG-')) {
                        $number = 564;  // Starts at PV NO: TG-0564
                    } elseif (str_contains($prefix, 'IC-')) {
                        $number = 376;  // Starts at PV NO: IC-0376
                    } else {
                        $number = 1;
                    }
                }

                if ($voucher->type === 'petty_cash' && str_starts_with($prefix, 'PCV NO: ')) {
                    $padLength = 5;
                } else {
                    $padLength = 4;
                }
                
                $voucher->voucher_number = $prefix . str_pad($number, $padLength, '0', STR_PAD_LEFT);
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
        if ($voucher->wasChanged('status') && $voucher->status === VoucherStatus::Paid->value) {

            // ── Petty Cash: low balance check + liquidation trigger ────────────
            if ($voucher->type === 'petty_cash') {
                $totalReplenishing = \App\Models\FloatReplenishment::sum('amount');
                $totalSpent = \App\Models\Voucher::where('type', 'petty_cash')
                    ->where('status', VoucherStatus::Paid->value)
                    ->sum('amount');

                $currentBalance = (float) $totalReplenishing - (float) $totalSpent;

                if ($currentBalance < 2000) {
                    try {
                        $managers = \App\Models\User::permission('voucher.manage_float')->get();
                        foreach ($managers as $manager) {
                            $manager->notify(new \App\Notifications\LowBalanceNotification($currentBalance));
                        }
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::error('Low Balance Notification Failed: ' . $e->getMessage());
                    }
                }

                // ── Auto-trigger liquidation ───────────────────────────────────
                $threshold = (float) config('liquidation.minimum_amount', 0);
                if ((float) $voucher->amount >= $threshold) {
                    $deadlineDays = config('liquidation.deadline_days');
                    $dueDate = $deadlineDays ? now()->addDays((int) $deadlineDays)->toDateString() : null;

                    $voucher->updateQuietly(['liquidation_status' => 'pending']);

                    \App\Models\Liquidation::create([
                        'voucher_id'      => $voucher->id,
                        'liquidated_by'   => $voucher->user_id, // pre-assigns to voucher creator; custodian will update
                        'amount_spent'    => 0,
                        'amount_returned' => 0,
                        'amount_short'    => (float) $voucher->amount,
                        'status'          => 'pending',
                        'due_date'        => $dueDate,
                    ]);
                }
            }

            // Bust the float widget cache on every paid status change
            \Illuminate\Support\Facades\Cache::forget('head_office_float_widget_stats');
        }

        // ── Mark overdue liquidations ──────────────────────────────────────────
        // This is a passive check: if the liquidation exists and is past due,
        // sync the voucher's liquidation_status to 'overdue'.
        if ($voucher->type === 'petty_cash' && $voucher->liquidation_status === 'pending') {
            $liq = $voucher->liquidation;
            if ($liq && $liq->due_date && \Carbon\Carbon::parse($liq->due_date)->isPast() && $liq->status === 'pending') {
                $voucher->updateQuietly(['liquidation_status' => 'overdue']);
                $liq->updateQuietly(['status' => 'pending']); // keep as pending, just the voucher reflects overdue
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
