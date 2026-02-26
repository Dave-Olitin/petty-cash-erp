<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$v = \App\Models\Voucher::create([
    'user_id' => 1,
    'category_id' => 1,
    'type' => 'petty_cash',
    'status' => 'pending_checker',
    'amount' => 100,
    'payee' => 'Test',
    'description' => 'Test',
    'current_approval_step' => 1
]);
echo "Status: {$v->status}, Step: {$v->current_approval_step}\n";

$v2 = \App\Models\Voucher::create([
    'user_id' => 1,
    'category_id' => 1,
    'type' => 'petty_cash',
    'status' => 'pending_approver',
    'amount' => 100,
    'payee' => 'Test',
    'description' => 'Test',
    'current_approval_step' => 2
]);
echo "Status: {$v2->status}, Step: {$v2->current_approval_step}\n";
