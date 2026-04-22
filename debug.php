<?php
require 'vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$repls = App\Models\FloatReplenishment::all()->map(function($r) {
    return "ID: {$r->id} | amt: {$r->amount} | date: {$r->date} | crt: {$r->created_at}";
})->toArray();

$vouchers = App\Models\Voucher::where('status', 'paid')->get()->map(function($v) {
    return "ID: {$v->id} | amt: {$v->amount} | type: {$v->type} | upd: {$v->updated_at}";
})->toArray();

file_put_contents('debug_clean.txt', implode("\n", $repls) . "\n\n" . implode("\n", $vouchers));
