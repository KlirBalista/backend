<?php
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$userIds = [3, 4, 5, 6];

echo "Users to be deleted:\n";

// First, show the users that will be deleted
$users = DB::table('users')->whereIn('id', $userIds)->get();
foreach ($users as $user) {
    echo " - ID {$user->id}: {$user->email} ({$user->firstname} {$user->lastname})\n";
}

echo "\nProceeding with deletion...\n";

try {
    DB::beginTransaction();
    
    // Since we confirmed there are no related records, we can delete directly
    $deleted = DB::table('users')->whereIn('id', $userIds)->delete();
    
    DB::commit();
    
    echo "Successfully deleted {$deleted} user(s).\n";
    
} catch (Exception $e) {
    DB::rollBack();
    echo "Error during deletion: " . $e->getMessage() . "\n";
}