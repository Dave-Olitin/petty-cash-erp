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
        // 1. Repair Lines first using DB facade to avoid Eloquent strictness
        $lines = \Illuminate\Support\Facades\DB::table('purchase_entry_lines')->get();
        
        foreach ($lines as $line) {
            $total = (float) ($line->total ?? 0);
            $debit = (float) ($line->debit ?? 0);
            $credit = (float) ($line->credit ?? 0);
            
            if ($total == 0 && ($debit > 0 || $credit > 0)) {
                $total = max($debit, $credit);
            }
            
            $amount = max($total, $debit, $credit);
            
            \Illuminate\Support\Facades\DB::table('purchase_entry_lines')
                ->where('id', $line->id)
                ->update([
                    'total' => $total,
                    'amount' => $amount,
                ]);
        }

        // 2. Repair Parent entries
        $entries = \Illuminate\Support\Facades\DB::table('purchase_entries')->get();
        foreach ($entries as $entry) {
            $lineData = \Illuminate\Support\Facades\DB::table('purchase_entry_lines')
                ->where('purchase_entry_id', $entry->id)
                ->get();
                
            $grandTotal = $lineData->sum(fn($l) => max((float)($l->total ?? 0), (float)($l->debit ?? 0), (float)($l->credit ?? 0)));
            $vatTotal = $lineData->sum(fn($l) => (float)($l->tax_amount ?? 0));
            
            $totalAmount = $grandTotal - $vatTotal;
            $amountPaid = (float) ($entry->amount_paid ?? 0);
            $balanceDue = max(0, $grandTotal - $amountPaid);
            
            $paymentStatus = 'unpaid';
            if ($grandTotal > 0) {
                if ($amountPaid >= $grandTotal) {
                    $paymentStatus = 'paid';
                } elseif ($amountPaid > 0) {
                    $paymentStatus = 'partial';
                }
            }
            
            \Illuminate\Support\Facades\DB::table('purchase_entries')
                ->where('id', $entry->id)
                ->update([
                    'grand_total' => $grandTotal,
                    'total_vat' => $vatTotal,
                    'total_amount' => $totalAmount,
                    'balance_due' => $balanceDue,
                    'payment_status' => $paymentStatus,
                ]);
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
