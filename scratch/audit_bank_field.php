<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Voucher;
use App\Models\AccountCode;

// Show all account codes starting with 1 (bank accounts)
$accountCodes = AccountCode::where('is_active', true)
    ->where('code', 'like', '1%')
    ->get(['code', 'name'])
    ->toArray();

echo "=== AVAILABLE BANK ACCOUNT CODES ===\n";
foreach ($accountCodes as $ac) {
    echo "  {$ac['code']} => {$ac['name']}\n";
}

echo "\n=== FREE TEXT NAMES IN PAID VOUCHERS (need fixing) ===\n";

$paid = Voucher::whereIn('type', ['payment', 'bank_encashment'])
    ->where('status', 'paid')
    ->get(['id', 'voucher_number', 'bank', 'multiple_payments', 'amount']);

$validCodes = AccountCode::pluck('code')->toArray();

$freeTextGroups = [];
foreach ($paid as $v) {
    $payments = $v->multiple_payments ?? [['bank' => $v->bank, 'amount' => $v->amount]];
    foreach ($payments as $p) {
        $bk = trim($p['bank'] ?? '');
        if (empty($bk) || in_array($bk, $validCodes)) continue;
        if (!isset($freeTextGroups[$bk])) {
            $freeTextGroups[$bk] = ['count' => 0, 'ids' => []];
        }
        $freeTextGroups[$bk]['count']++;
        $freeTextGroups[$bk]['ids'][] = $v->id;
    }
}

arsort($freeTextGroups);
foreach ($freeTextGroups as $name => $info) {
    echo "  '{$name}' => {$info['count']} vouchers (IDs: " . implode(', ', array_slice($info['ids'], 0, 5)) . (count($info['ids']) > 5 ? '...' : '') . ")\n";
}
