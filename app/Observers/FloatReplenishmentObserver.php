<?php

namespace App\Observers;

use App\Models\FloatReplenishment;

class FloatReplenishmentObserver
{
    /**
     * When a Float Replenishment is created, automatically create a
     * corresponding Denomination record so it appears in the
     * "Recent Cash Breakdown Logs" widget.
     */
    public function created(FloatReplenishment $replenishment): void
    {
        // Only create if no denomination already exists (avoids double-entry)
        if (! $replenishment->denominations) {
            $replenishment->denominations()->create([
                'total_amount'       => $replenishment->amount,
                'change_given'       => 0,
                'is_change_received' => true,
                'remarks'            => $replenishment->remarks ?? 'Float Replenishment',
                'bill_1000' => 0, 'bill_500' => 0, 'bill_200' => 0, 'bill_100' => 0,
                'bill_50'   => 0, 'bill_20'  => 0, 'bill_10'  => 0, 'bill_5'   => 0,
                'coin_1'    => 0, 'coin_0_50' => 0, 'coin_0_25' => 0,
            ]);
        }
    }
}
