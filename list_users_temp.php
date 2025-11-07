<?php
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$users = App\Models\User::all(['id', 'firstname', 'lastname', 'email', 'contact_number', 'status']);

echo "Current users in the database:\n";
foreach ($users as $user) {
    echo "ID: {$user->id} | Name: {$user->firstname} {$user->lastname} | Email: {$user->email} | Contact: {$user->contact_number} | Status: {$user->status}\n";
}