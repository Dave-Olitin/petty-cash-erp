<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\PurchaseEntry;
use App\Models\PurchaseEntryLine;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Repair lines: sync total/amount with debit/credit for non-legacy lines
        $lines = PurchaseEntryLine::all();
        foreach ($lines as $line) {
            $debit = (float)$line->debit;
            $credit = (float)$line->credit;
            $total = (float)$line->total;
            
            $expectedTotal = $total;
            if ($debit > 0) {
                $expectedTotal = $debit;
            } elseif ($credit > 0) {
                $expectedTotal = $credit;
            }
            
            if (round($total - $expectedTotal, 2) != 0 || round((float)$line->amount - $expectedTotal, 2) != 0) {
                $line->total = $expectedTotal;
                $line->amount = $expectedTotal;
                $line->save(); // Triggers saved hook which updates parent
            }
        }

        // 2. Repair Purchase Entries: recalculate grand_total, total_amount, balance_due, and payment_status
        $entries = PurchaseEntry::with('lines')->get();
        foreach ($entries as $entry) {
            $lineData = $entry->lines;
            
            // Recalculate based on corrected lines
            $newGrandTotal = $lineData->sum(fn($l) => max((float)$l->total, (float)$l->debit, (float)$l->credit));
            $newVatTotal = $lineData->sum(fn($l) => (float)$l->tax_amount);
            $newTotalAmount = $newGrandTotal - $newVatTotal;
            
            $totalDebit = $lineData->sum(fn($l) => (float)$l->debit);
            $totalCredit = $lineData->sum(fn($l) => (float)$l->credit);
            
            $originalAmountPaid = (float)$entry->amount_paid;
            
            $newAmountPaid = $originalAmountPaid;
            if ($entry->payment_status === 'paid') {
                $newAmountPaid = $newGrandTotal;
                $newBalanceDue = 0.0;
            } else {
                $newBalanceDue = max(0.0, $newGrandTotal - $newAmountPaid);
                if ($newAmountPaid >= $newGrandTotal && $newGrandTotal > 0) {
                    $entry->payment_status = 'paid';
                    $newBalanceDue = 0.0;
                } elseif ($newAmountPaid > 0) {
                    $entry->payment_status = 'partial';
                } else {
                    $entry->payment_status = 'unpaid';
                }
            }
            
            $entry->update([
                'grand_total' => $newGrandTotal,
                'total_vat' => $newVatTotal,
                'total_amount' => $newTotalAmount,
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'amount_paid' => $newAmountPaid,
                'balance_due' => $newBalanceDue,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Data repair is not reversible.
    }
};
