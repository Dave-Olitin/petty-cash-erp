<?php

namespace App\Services;

use App\Models\JournalEntry;
use Illuminate\Support\Facades\DB;

class JournalEntryService
{
    /**
     * Distribute the Journal Entry's total debit amount across linked Purchase Entries.
     * This acts just like Voucher approval payment distribution.
     * 
     * @param JournalEntry $journalEntry
     */
    public function distributePayments(JournalEntry $journalEntry): void
    {
        DB::transaction(function () use ($journalEntry) {
            $linkedEntries = $journalEntry->purchaseEntries()->orderBy('date')->orderBy('id')->get();

            if ($linkedEntries->isEmpty()) {
                return;
            }

            // We use total_debit to represent the amount being distributed.
            // If the JV is paying a supplier, the supplier AP account is debited.
            $remaining = (float) $journalEntry->total_debit;

            foreach ($linkedEntries as $pe) {
                $billTotal    = (float) $pe->grand_total;
                $alreadyPaid  = (float) $pe->amount_paid;
                
                // When editing, we need to subtract the amount THIS specific journal entry previously applied
                // from the amount_paid, so we can accurately calculate how much is still owed by the bill.
                $previouslyApplied = (float) $pe->pivot->amount_applied;
                $actualAlreadyPaid = max(0, $alreadyPaid - $previouslyApplied);
                
                $stillOwed    = max(0, $billTotal - $actualAlreadyPaid);

                if ($remaining <= 0) {
                    $applied = 0;
                } else {
                    $applied = min($remaining, $stillOwed);
                }
                
                // Save the exact amount we applied to the pivot table
                $journalEntry->purchaseEntries()->updateExistingPivot($pe->id, [
                    'amount_applied' => $applied
                ]);

                $remaining -= $applied;
            }
            
            // Now safely recalculate all linked entries from the pivot single source of truth
            $linkedEntries->each(fn ($pe) => $pe->recalculatePayments());
        });
    }
}
