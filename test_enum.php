<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    Illuminate\Support\Facades\DB::statement("ALTER TABLE vouchers MODIFY COLUMN type ENUM('petty_cash', 'payment', 'receipt', 'bank_encashment') DEFAULT 'payment'");
    echo "SUCCESS\n";
} catch (\Exception $e) {
    echo $e->getMessage() . "\n";
}
