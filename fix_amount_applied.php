<?php

/**
 * One-time data fix: Re-distributes amount_applied across all paid vouchers
 * that have linked purchase entries where the pivot value is wrong (0 or stale).
 *
 * Run via: php fix_amount_applied.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Voucher;
use App\Services\VoucherApprovalService;

$service = app(VoucherApprovalService::class);

// Find all paid vouchers that have at least one linked PE with amount_applied = 0
// (or all paid vouchers with linked PEs — redistribution is idempotent).
$vouchers = Voucher::where('status', 'paid')
    ->whereHas('purchaseEntries')
    ->orderBy('date', 'asc')
    ->orderBy('id', 'asc')
    ->get();

if ($vouchers->isEmpty()) {
    echo "No paid vouchers with linked purchase entries found.\n";
    exit(0);
}

echo "Found {$vouchers->count()} paid voucher(s) to fix...\n";
echo str_repeat('─', 70) . "\n\n";

$fixed = 0;
$errors = 0;

foreach ($vouchers as $voucher) {
    try {
        // Capture BEFORE state
        $before = $voucher->purchaseEntries()->withPivot('amount_applied')->get()
            ->mapWithKeys(fn($pe) => [$pe->id => (float) $pe->pivot->amount_applied]);

        // Run redistribution
        $service->redistributeLinkedEntries($voucher);

        // Capture AFTER state
        $entries = $voucher->purchaseEntries()->withPivot('amount_applied')->get();

        echo "✅ {$voucher->voucher_number} — AED " . number_format($voucher->amount, 2) . "\n";
        foreach ($entries as $pe) {
            $was = $before[$pe->id] ?? 0;
            $now = (float) $pe->pivot->amount_applied;
            $changed = abs($was - $now) > 0.01;
            $marker  = $changed ? ' ← FIXED' : '';
            echo "   → {$pe->entry_no}: AED " . number_format($now, 2) . " applied"
                . ($changed ? " (was AED " . number_format($was, 2) . ")" : '')
                . "$marker\n";
        }
        echo "\n";
        $fixed++;
    } catch (\Throwable $e) {
        echo "❌ {$voucher->voucher_number}: FAILED — {$e->getMessage()}\n\n";
        $errors++;
    }
}

echo str_repeat('─', 70) . "\n";
echo "Done. Fixed: {$fixed} voucher(s). Errors: {$errors}.\n";
