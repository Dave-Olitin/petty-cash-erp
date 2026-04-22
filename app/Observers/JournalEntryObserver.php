<?php

namespace App\Observers;

use App\Models\JournalEntry;
use App\Models\Liquidation;

class JournalEntryObserver
{
    /**
     * Fires after the JE is created or updated.
     * If a voucher_id is set, auto-create/update a Liquidation.
     */
    public function saved(JournalEntry $journalEntry): void
    {
        if (! $journalEntry->voucher_id) {
            return;
        }

        $voucher = $journalEntry->voucher()->with(['liquidation', 'denominations'])->first();

        if (! $voucher) {
            return;
        }

        // Only auto-liquidate petty cash vouchers — payment/receipt vouchers don't need this
        if ($voucher->type !== 'petty_cash') {
            return;
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($journalEntry, $voucher) {
            $original  = (float) $voucher->amount;
            $denom     = $voucher->denominations()->first();
            $deduction = $denom ? (float) $denom->prior_deduction : 0;
            $netTarget = max(0, $original - $deduction);

            $spent     = (float) $journalEntry->total_debit;
            $returned  = max(0, $netTarget - $spent);
            $short     = max(0, $spent - $netTarget);

            // Determine settlement status
            $status = 'complete';

            $existingLiquidation = $voucher->liquidation;

            // Guard: only skip if the liquidation is already COMPLETE and was set manually
            // (i.e., it has no auto-liquidated tag — an accountant already settled it by hand)
            if (
                $existingLiquidation
                && $existingLiquidation->status === 'complete'
                && ! str_contains($existingLiquidation->remarks ?? '', '[auto-liquidated]')
            ) {
                return;
            }

            $remarkTag  = '[auto-liquidated] JE: ' . $journalEntry->entry_no;
            $remarkNote = $short > 0
                ? " | Short by AED " . number_format($short, 2)
                : ($returned > 0 ? " | AED " . number_format($returned, 2) . " returned." : " | Fully settled (Net: " . number_format($netTarget, 2) . ").");

            $payload = [
                'voucher_id'      => $voucher->id,
                'liquidated_by'   => $journalEntry->created_by ?? auth()->id() ?? 1,
                'amount_spent'    => $spent,
                'amount_returned' => $returned,
                'amount_short'    => $short,
                'prior_deduction' => $deduction,
                'status'          => $status,
                'remarks'         => $remarkTag . $remarkNote,
                'liquidated_at'   => $journalEntry->date ?? now(),
            ];

            if ($existingLiquidation) {
                $existingLiquidation->update($payload);
            } else {
                Liquidation::create($payload);
            }

            // Sync the voucher's liquidation_status field
            $voucher->update(['liquidation_status' => 'liquidated']);
        });
    }

    /**
     * If the voucher_id is cleared off a JE, revert the voucher's liquidation status.
     */
    public function updated(JournalEntry $journalEntry): void
    {
        // If voucher_id was just removed
        if ($journalEntry->wasChanged('voucher_id') && ! $journalEntry->voucher_id) {
            $oldVoucherId = $journalEntry->getOriginal('voucher_id');
            if (! $oldVoucherId) {
                return;
            }

            $oldVoucher = \App\Models\Voucher::with('liquidation')->find($oldVoucherId);
            if (! $oldVoucher) {
                return;
            }

            // Only revert if the liquidation was auto-created by us
            $liq = $oldVoucher->liquidation;
            if ($liq && str_contains($liq->remarks ?? '', '[auto-liquidated]')) {
                $liq->delete();
                $oldVoucher->update(['liquidation_status' => 'pending']);
            }
        }
    }
}
