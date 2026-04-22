<?php
$lines = file('c:/Users/davem/Documents/GitHub/petty-cash-erp/app/Filament/Vouchers/Resources/VoucherResource.php');
foreach ($lines as $i => $line) {
    if (stripos($line, 'getPages') !== false || stripos($line, 'getRelation') !== false || stripos($line, 'Activities') !== false) {
        echo ($i+1) . ': ' . rtrim($line) . "\n";
    }
}
