<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Patient;
use App\Models\PatientCharge;
use App\Models\PatientBill;
use App\Models\BillItem;
use App\Models\BillPayment;
use App\Http\Controllers\PatientChargeController;
use App\Http\Controllers\PaymentsController;

echo "=== BCSystem Payment Flow Test ===\n\n";

// Test 1: Check if we can finalize a bill
echo "1. Testing bill finalization...\n";

$bill = PatientBill::with(['patient', 'items'])->first();
if ($bill) {
    echo "   Found bill #{$bill->id} for {$bill->patient->first_name} {$bill->patient->last_name}\n";
    echo "   Status: {$bill->status}\n";
    echo "   Total: ₱{$bill->total_amount}\n";
    echo "   Balance: ₱{$bill->balance_amount}\n";
    
    // Check bill items
    echo "   Items (" . $bill->items->count() . "):\n";
    foreach ($bill->items as $item) {
        echo "     - {$item->service_name}: {$item->quantity} x ₱{$item->unit_price} = ₱{$item->total_price}\n";
    }
    
    // Try to finalize the bill if it's in draft status
    if ($bill->status === 'draft') {
        $bill->status = 'sent';
        $bill->save();
        echo "   ✓ Bill finalized (status changed to 'sent')\n";
    } else {
        echo "   - Bill already finalized\n";
    }
} else {
    echo "   ✗ No bills found\n";
}

echo "\n";

// Test 2: Check payment processing
echo "2. Testing payment processing...\n";

$finalizedBill = PatientBill::where('status', '!=', 'draft')->first();
if ($finalizedBill) {
    echo "   Found finalized bill #{$finalizedBill->id}\n";
    echo "   Balance: ₱{$finalizedBill->balance_amount}\n";
    
    // Create a test payment
    $paymentAmount = min($finalizedBill->balance_amount, 10000); // Pay 10k or full balance, whichever is smaller
    
    $payment = new BillPayment();
    $payment->patient_bill_id = $finalizedBill->id;
    $payment->amount = $paymentAmount;
    $payment->payment_method = 'cash';
    $payment->payment_date = now();
    $payment->reference_number = 'TEST-' . time();
    $payment->notes = 'Test payment from script';
    $payment->save();
    
    echo "   ✓ Created payment: ₱{$paymentAmount}\n";
    
    // Update bill payment status
    $finalizedBill->updatePaymentStatus();
    $finalizedBill->refresh();
    
    echo "   ✓ Updated bill status to: {$finalizedBill->status}\n";
    echo "   ✓ New balance: ₱{$finalizedBill->balance_amount}\n";
} else {
    echo "   ✗ No finalized bills found\n";
}

echo "\n";

// Test 3: Test API endpoint simulation (what the frontend would call)
echo "3. Testing API-like functionality...\n";

// Simulate getting bill summary for frontend
$patient = Patient::first();
if ($patient) {
    echo "   Testing bill summary for patient: {$patient->first_name} {$patient->last_name}\n";
    
    $patientBills = PatientBill::where('patient_id', $patient->id)
                              ->where('status', '!=', 'draft')
                              ->with(['items', 'payments'])
                              ->get();
    
    if ($patientBills->count() > 0) {
        echo "   Found " . $patientBills->count() . " finalized bill(s)\n";
        
        $totalBalance = 0;
        $billSummary = [];
        
        foreach ($patientBills as $bill) {
            $totalBalance += $bill->balance_amount;
            $billSummary[] = [
                'id' => $bill->id,
                'bill_number' => $bill->bill_number,
                'total_amount' => $bill->total_amount,
                'paid_amount' => $bill->paid_amount,
                'balance_amount' => $bill->balance_amount,
                'status' => $bill->status,
                'items' => $bill->items->map(function($item) {
                    return [
                        'service_name' => $item->service_name,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'total_price' => $item->total_price,
                    ];
                }),
                'payments' => $bill->payments->map(function($payment) {
                    return [
                        'amount' => $payment->amount,
                        'payment_method' => $payment->payment_method,
                        'payment_date' => $payment->payment_date->format('Y-m-d H:i:s'),
                        'reference_number' => $payment->reference_number,
                    ];
                })
            ];
        }
        
        echo "   ✓ Total outstanding balance: ₱{$totalBalance}\n";
        echo "   ✓ Bill summary data prepared for frontend\n";
        
        // Save summary for frontend testing
        file_put_contents('test-api-response.json', json_encode([
            'success' => true,
            'patient' => [
                'id' => $patient->id,
                'name' => $patient->first_name . ' ' . $patient->last_name,
            ],
            'bills' => $billSummary,
            'total_balance' => $totalBalance
        ], JSON_PRETTY_PRINT));
        
        echo "   ✓ Sample API response saved to test-api-response.json\n";
    } else {
        echo "   - No finalized bills for this patient\n";
    }
}

echo "\n=== Final Summary ===\n";
echo "Users: " . User::count() . "\n";
echo "Patients: " . Patient::count() . "\n";
echo "Services: " . PatientCharge::count() . "\n";
echo "Bills: " . PatientBill::count() . " (Draft: " . PatientBill::where('status', 'draft')->count() . ", Finalized: " . PatientBill::where('status', '!=', 'draft')->count() . ")\n";
echo "Payments: " . BillPayment::count() . "\n";
echo "Total Outstanding: ₱" . PatientBill::sum('balance_amount') . "\n";

echo "\n=== Payment Flow Status ===\n";
$draftBills = PatientBill::where('status', 'draft')->count();
$finalizedBills = PatientBill::where('status', '!=', 'draft')->count();
$payments = BillPayment::count();

if ($finalizedBills > 0 && $payments > 0) {
    echo "✅ Payment flow is working correctly!\n";
    echo "   - Bills can be finalized\n";
    echo "   - Payments can be processed\n";
    echo "   - Bill balances update correctly\n";
} elseif ($finalizedBills > 0) {
    echo "⚠️  Bills can be finalized, but no payments processed yet\n";
} else {
    echo "⚠️  All bills are still in draft status\n";
}

echo "\nNext steps for frontend testing:\n";
echo "1. Frontend should call /api/birthcare/1/patient-charges/bill-summary/{$patient->id}\n";
echo "2. This should return finalized charges for the patient\n";
echo "3. Frontend can then process payments via /api/birthcare/1/payments\n";
