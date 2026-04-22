<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Liquidation;
use App\Models\Voucher;

// Remove the test-created auto-liquidation for Voucher 9 (payment type, should not have been touched)
$liq = Liquidation::find(17);
if ($liq && str_contains($liq->remarks ?? '', '[auto-liquidated]')) {
    $voucherId = $liq->voucher_id;
    $liq->forceDelete();
    echo "Deleted auto-liquidation id=17 for voucher_id={$voucherId}\n";

    // Restore the voucher's liquidation_status back to not_required
    Voucher::where('id', $voucherId)->update(['liquidation_status' => 'not_required']);
    echo "Restored Voucher {$voucherId} liquidation_status to not_required\n";
} else {
    echo "Nothing to clean up.\n";
}
