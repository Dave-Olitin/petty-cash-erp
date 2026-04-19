<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Repair Lines first
        $lines = \App\Models\PurchaseEntryLine::all();
        foreach ($lines as $line) {
            if ((float)$line->total == 0 && ((float)$line->debit > 0 || (float)$line->credit > 0)) {
                $line->total = max((float)$line->debit, (float)$line->credit);
                $line->amount = $line->total;
            }
            // Ensure amount matches total/max
            $line->amount = max((float)$line->total, (float)$line->debit, (float)$line->credit);
            $line->save(); 
        }

        // 2. Repair Parent entries
        $entries = \App\Models\PurchaseEntry::all();
        foreach ($entries as $entry) {
            $lineData = $entry->lines()->get();
            $grandTotal = $lineData->sum(fn($l) => max((float)$l->total, (float)$l->debit, (float)$l->credit));
            $vatTotal = $lineData->sum(fn($l) => (float)$l->tax_amount);
            
            // The saving hook on PurchaseEntry will automatically calculate balance_due
            $entry->grand_total = $grandTotal;
            $entry->total_vat = $vatTotal;
            $entry->total_amount = $grandTotal - $vatTotal;
            
            if ($grandTotal > 0) {
                $paid = (float) $entry->amount_paid;
                if ($paid >= $grandTotal) {
                    $entry->payment_status = \App\Models\PurchaseEntry::STATUS_PAID;
                } elseif ($paid > 0) {
                    $entry->payment_status = \App\Models\PurchaseEntry::STATUS_PARTIAL;
                } else {
                    $entry->payment_status = \App\Models\PurchaseEntry::STATUS_UNPAID;
                }
            }
            
            $entry->save(); 
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Data recalculation is irreversible.
    }
};
