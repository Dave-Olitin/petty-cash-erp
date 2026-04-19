<?php

use App\Models\PurchaseEntry;
use App\Models\PurchaseEntryLine;

echo "--- DEEP DATA REPAIR STARTED ---\n";

// 1. Repair Lines first
$lines = PurchaseEntryLine::all();
foreach ($lines as $line) {
    if ((float)$line->total == 0 && ((float)$line->debit > 0 || (float)$line->credit > 0)) {
        $line->total = max((float)$line->debit, (float)$line->credit);
        $line->amount = $line->total;
    }
    // Also ensures amount matches total/max
    $line->amount = max((float)$line->total, (float)$line->debit, (float)$line->credit);
    $line->save(); // Triggers saved hook which updates parent partially
}

// 2. Repair Parent entries
$entries = PurchaseEntry::all();
foreach ($entries as $entry) {
    echo "Processing $entry->entry_no... ";
    
    // Explicitly calculate line totals for safety
    $lineData = $entry->lines()->get();
    $grandTotal = $lineData->sum(fn($l) => max((float)$l->total, (float)$l->debit, (float)$l->credit));
    $vatTotal = $lineData->sum(fn($l) => (float)$l->tax_amount);
    
    // the saving hook I just added will handle balance_due automatically
    $entry->grand_total = $grandTotal;
    $entry->total_vat = $vatTotal;
    $entry->total_amount = $grandTotal - $vatTotal;
    
    // Touch status
    if ($grandTotal > 0) {
        $paid = (float) $entry->amount_paid;
        if ($paid >= $grandTotal) {
            $entry->payment_status = PurchaseEntry::STATUS_PAID;
        } elseif ($paid > 0) {
            $entry->payment_status = PurchaseEntry::STATUS_PARTIAL;
        } else {
            $entry->payment_status = PurchaseEntry::STATUS_UNPAID;
        }
    }
    
    $entry->save(); // Triggers the balance_due sync hook
    echo "Done (GT: $grandTotal, Bal: $entry->balance_due)\n";
}

echo "--- DEEP DATA REPAIR COMPLETE ---\n";
