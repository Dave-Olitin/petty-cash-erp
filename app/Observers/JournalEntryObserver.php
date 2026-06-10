<?php

namespace App\Observers;

use App\Models\JournalEntry;
use App\Models\Liquidation;

class JournalEntryObserver
{
    /**
     * Fires after the JE is created or updated, AND after Filament has synced relationships.
     */
    public function processAutoLiquidation(JournalEntry $journalEntry): void
    {
        $vouchers = $journalEntry->vouchers()->with(['liquidation', 'denominations'])->get();

        // If there are exactly 1 voucher, we can auto-liquidate
        // If there are >1 vouchers, we skip auto-liquidation as requested by user ("yes manually")
        if ($vouchers->count() !== 1) {
            return;
        }

        $voucher = $vouchers->first();

        // Only auto-liquidate petty cash vouchers
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
            if ($short > 0) {
                $status = 'short';
            } elseif ($returned > 0) {
                $status = 'excess';
            } else {
                $status = 'complete';
            }

            $existingLiquidation = $voucher->liquidation;

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
                $liquidationRecord = $existingLiquidation;
            } else {
                $liquidationRecord = Liquidation::create($payload);
            }

            app(\App\Services\LiquidationService::class)->handleAutoGeneration($liquidationRecord, true);

            if ($voucher->status === 'paid') {
                $voucher->update(['liquidation_status' => 'liquidated']);
            }
        });
    }

    /**
     * Fires after a new Journal Entry is created.
     */
    public function created(JournalEntry $journalEntry): void
    {
        $accountants = \App\Models\User::role('Accountant')->get();

        if ($accountants->count() > 0) {
            $creator = $journalEntry->created_by ? \App\Models\User::find($journalEntry->created_by) : auth()->user();
            $creatorName = $creator ? $creator->name : 'System';

            \Filament\Notifications\Notification::make()
                ->title('New Journal Entry Created')
                ->body("{$journalEntry->entry_no} has been created by {$creatorName}.")
                ->info()
                ->sendToDatabase($accountants);
        }
    }
}
