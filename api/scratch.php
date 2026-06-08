<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $users = App\Models\User::select([
        'users.id',
        'users.name',
        'users.email',
        'users.company_name',
        'users.role',
        'users.plan_status',
        'users.trial_expires_at',
        'users.created_at',
        'users.owner_id',
        'owners.name as owner_name'
    ])
    ->leftJoin('users as owners', 'users.owner_id', '=', 'owners.id')
    ->orderBy('users.created_at', 'desc')
    ->get();
    echo "Success: " . count($users) . "\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
