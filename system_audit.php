<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$logFile = storage_path('logs/laravel.log');
$logContent = file_exists($logFile) ? implode("\n", array_slice(file($logFile), -200)) : "No log file found.";

$audit = [
    'latest_logs' => $logContent,
    'routes_with_errors' => []
];

file_put_contents(storage_path('logs/system_audit_results.json'), json_encode($audit, JSON_PRETTY_PRINT));
echo "Audit complete\n";
