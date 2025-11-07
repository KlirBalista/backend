<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Patient;
use App\Models\PatientCharge;
use App\Models\PatientBill;
use App\Models\BillPayment;

echo "=== BCSystem Database Test ===\n\n";

echo "Users:\n";
foreach (User::all() as $user) {
    echo "  - {$user->name} (ID: {$user->id}, Role: {$user->system_role_id})\n";
}

echo "\nPatients:\n";
foreach (Patient::all() as $patient) {
    echo "  - {$patient->first_name} {$patient->last_name} (ID: {$patient->id})\n";
}

echo "\nPatient Charges (Services):\n";
foreach (PatientCharge::all() as $charge) {
    echo "  - {$charge->service_name}: ₱{$charge->price} ({$charge->category})\n";
}

echo "\nPatient Bills:\n";
foreach (PatientBill::with('patient')->get() as $bill) {
    echo "  - Bill #{$bill->id} for {$bill->patient->first_name} {$bill->patient->last_name}: ₱{$bill->total_amount} (Status: {$bill->status})\n";
}

echo "\nBill Payments:\n";
foreach (BillPayment::with(['bill', 'bill.patient'])->get() as $payment) {
    echo "  - Payment #{$payment->id}: ₱{$payment->amount} for {$payment->bill->patient->first_name} {$payment->bill->patient->last_name}\n";
}

echo "\n=== Summary ===\n";
echo "Total Users: " . User::count() . "\n";
echo "Total Patients: " . Patient::count() . "\n";
echo "Total Charges: " . PatientCharge::count() . "\n";
echo "Total Bills: " . PatientBill::count() . "\n";
echo "Total Payments: " . BillPayment::count() . "\n";
