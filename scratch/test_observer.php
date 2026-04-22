<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Voucher;
use App\Models\Liquidation;
use App\Models\JournalEntry;

// Find a petty_cash voucher that has a PENDING liquidation (the disbursement-created one)
$voucher = Voucher::where('type', 'petty_cash')
    ->where('status', 'paid')
    ->whereHas('liquidation', fn($q) => $q->where('status', 'pending'))
    ->with('liquidation')
    ->first();

if (! $voucher) {
    echo "No petty_cash voucher with a pending liquidation found.\n";
    echo "Tip: Disburse a PCV first, which will auto-create a pending liquidation.\n";
    echo "Then create a JE and link it to that PCV — the observer will settle it.\n";
    exit;
}

echo "=== FOUND TARGET VOUCHER ===\n";
echo "Voucher: {$voucher->voucher_number} | Amount: AED {$voucher->amount}\n";
echo "Liquidation ID: {$voucher->liquidation->id} | Status: {$voucher->liquidation->status}\n";
echo "Amount Spent: AED {$voucher->liquidation->amount_spent}\n";
echo "Voucher liquidation_status: {$voucher->liquidation_status}\n\n";

// Simulate: create a JE linked to this PCV
echo "=== SIMULATING JE CREATION + LINK ===\n";

// Use DB transaction so we can roll back if needed
\Illuminate\Support\Facades\DB::beginTransaction();

try {
    $je = JournalEntry::create([
        'date'          => now()->toDateString(),
        'reference'     => 'TEST-AUTO-LIQUIDATE',
        'currency'      => 'AED',
        'total_debit'   => $voucher->amount * 0.8,  // simulate spending 80% of disbursement
        'total_credit'  => $voucher->amount * 0.8,
        'voucher_id'    => $voucher->id,
    ]);

    echo "JE Created: {$je->entry_no} | Debit: AED {$je->total_debit}\n";

    // Refresh to get the observer's result
    $voucher->load('liquidation');
    $voucher->refresh();

    echo "\n=== RESULT AFTER OBSERVER ===\n";
    $liq = $voucher->liquidation;
    echo "Liquidation Status: {$liq->status}\n";
    echo "Amount Spent: AED {$liq->amount_spent}\n";
    echo "Amount Returned: AED {$liq->amount_returned}\n";
    echo "Amount Short: AED {$liq->amount_short}\n";
    echo "Remarks: {$liq->remarks}\n";
    echo "Voucher liquidation_status: {$voucher->liquidation_status}\n\n";

    $ok = $liq->status === 'complete';
    echo $ok ? "✅ PASS: Liquidation auto-settled!\n" : "❌ FAIL: Liquidation was NOT settled.\n";

    \Illuminate\Support\Facades\DB::rollBack();
    echo "\n(Test rolled back — no real data was changed)\n";

} catch (\Throwable $e) {
    \Illuminate\Support\Facades\DB::rollBack();
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
