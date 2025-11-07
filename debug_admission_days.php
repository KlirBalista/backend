<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\PatientAdmission;
use App\Models\Patient;

echo "=== DEBUGGING ADMISSION DAYS CALCULATION ===\n";
echo "System Date: " . date('Y-m-d H:i:s') . "\n";
echo "Laravel Now: " . now()->toDateTimeString() . "\n";
echo "Laravel Today: " . today()->toDateString() . "\n\n";

// Find patient Daniah
$patients = Patient::where('first_name', 'like', '%daniah%')
    ->orWhere('last_name', 'like', '%daniah%')
    ->get();

if ($patients->isEmpty()) {
    echo "No patient found with name containing 'daniah'\n";
    
    // List all patients to see what's available
    echo "\nAll patients in database:\n";
    $allPatients = Patient::select('id', 'first_name', 'last_name')->get();
    foreach ($allPatients as $patient) {
        echo "ID: {$patient->id}, Name: {$patient->first_name} {$patient->last_name}\n";
    }
} else {
    foreach ($patients as $patient) {
        echo "Found Patient: {$patient->first_name} {$patient->last_name} (ID: {$patient->id})\n";
        
        $admissions = PatientAdmission::where('patient_id', $patient->id)
            ->orderBy('admission_date', 'desc')
            ->get();
            
        foreach ($admissions as $admission) {
            echo "\nAdmission Details:\n";
            echo "  Admission Date: " . $admission->admission_date->toDateString() . "\n";
            echo "  Discharge Date: " . ($admission->discharge_date ? $admission->discharge_date->toDateString() : 'Not discharged') . "\n";
            echo "  Status: {$admission->status}\n";
            echo "  Admission Days (calculated): " . $admission->admission_days . "\n";
            
            // Manual calculation
            $endDate = $admission->discharge_date ?? now()->toDateString();
            $diffInDays = $admission->admission_date->diffInDays($endDate);
            echo "  Manual calculation: diffInDays({$admission->admission_date->toDateString()}, {$endDate}) = {$diffInDays}\n";
            echo "  Manual admission days: " . ($diffInDays + 1) . "\n";
        }
    }
}

// Check bill items for room charges
echo "\n=== CHECKING BILL ITEMS ===\n";
$billItems = \App\Models\BillItem::join('patient_charges', 'bill_items.patient_charge_id', '=', 'patient_charges.id')
    ->where('patient_charges.service_name', 'like', '%room%')
    ->orWhere('patient_charges.service_name', 'like', '%private%')
    ->select('bill_items.*', 'patient_charges.service_name', 'patient_charges.category')
    ->orderBy('bill_items.created_at', 'desc')
    ->limit(10)
    ->get();

foreach ($billItems as $item) {
    echo "Bill Item: {$item->service_name}\n";
    echo "  Quantity: {$item->quantity}\n";
    echo "  Unit Price: {$item->unit_price}\n";
    echo "  Total Price: {$item->total_price}\n";
    echo "  Category: {$item->category}\n";
    echo "  Created: {$item->created_at}\n\n";
}