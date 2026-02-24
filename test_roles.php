<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$u = \App\Models\User::first();
echo "User: " . $u->name . "\n";
echo "Roles: " . implode(', ', $u->roles->pluck('name')->toArray()) . "\n";
echo "Permissions: " . implode(', ', $u->permissions->pluck('name')->toArray()) . "\n";
echo "Can manage_settings: " . ($u->can('manage_settings') ? 'Yes' : 'No') . "\n";
