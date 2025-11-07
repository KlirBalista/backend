<?php
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$userIds = [3, 4, 5, 6];

echo "Checking related records for user IDs: " . implode(', ', $userIds) . "\n\n";

// Get SQLite database path and create connection
$database_path = database_path('database.sqlite');
$pdo = new PDO('sqlite:' . $database_path);

// Check each table for related records
$tables_to_check = [
    'birth_care_subscriptions' => 'user_id',
    'birth_cares' => 'user_id', 
    'user_birth_roles' => 'user_id',
    'birth_care_staff' => 'user_id',
    'personal_access_tokens' => 'tokenable_id'
];

foreach ($tables_to_check as $table => $column) {
    $placeholders = str_repeat('?,', count($userIds) - 1) . '?';
    
    if ($table === 'personal_access_tokens') {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE tokenable_type = 'App\\Models\\User' AND {$column} IN ({$placeholders})");
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE {$column} IN ({$placeholders})");
    }
    
    $stmt->execute($userIds);
    $count = $stmt->fetchColumn();
    
    echo "Table '{$table}': {$count} related records\n";
}

echo "\n";