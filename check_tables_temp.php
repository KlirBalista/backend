<?php
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Get SQLite database path
$database_path = database_path('database.sqlite');
echo "Database path: " . $database_path . "\n\n";

try {
    // Connect to SQLite and list tables
    $pdo = new PDO('sqlite:' . $database_path);
    
    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table';");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Available tables:\n";
    foreach ($tables as $table) {
        echo "- " . $table . "\n";
    }
    
    // Check specific tables mentioned in the delete command
    $tables_to_check = ['birth_care_subscriptions', 'birth_cares', 'user_birth_roles', 'birth_care_staff', 'personal_access_tokens'];
    echo "\nChecking existence of tables used by delete command:\n";
    foreach ($tables_to_check as $table) {
        if (in_array($table, $tables)) {
            echo "✓ {$table} exists\n";
        } else {
            echo "✗ {$table} does NOT exist\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}