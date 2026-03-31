<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

\App\Models\Voucher::whereNotNull('department')->distinct()->pluck('department')->each(function($d) {
    if (!empty($d)) {
        \App\Models\Department::firstOrCreate(['name' => $d], ['is_active' => true]);
    }
});
echo "Done\n";
