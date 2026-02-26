<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Workflows configured? " . (\App\Models\ApprovalWorkflow::isConfigured() ? 'YES' : 'NO') . "\n";
foreach(\App\Models\ApprovalWorkflow::all() as $w) {
    echo "STEP: " . $w->step_order . " - USER ID: " . $w->user_id . "\n";
}

echo "Accountant items:\n";
$acc = \App\Models\User::where('email', 'accountant@pettycash.com')->first();
$vouchers = \App\Models\Voucher::actionRequired($acc)->get();
foreach ($vouchers as $v) {
    echo " - ID {$v->id}, Status: {$v->status}, Step: {$v->current_approval_step}\n";
}

echo "Approver items:\n";
$gm = \App\Models\User::where('email', 'gm@pettycash.com')->first();
$vouchers_gm = \App\Models\Voucher::actionRequired($gm)->get();
foreach ($vouchers_gm as $v) {
    echo " - ID {$v->id}, Status: {$v->status}, Step: {$v->current_approval_step}\n";
}
