<?php

use App\Models\User;
use App\Models\BirthCare;
use App\Models\Patient;
use App\Models\PatientBill;
use App\Models\BillItem;
use App\Models\BillPayment;
use App\Models\PatientCharge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create test data
    $this->user = User::factory()->create([
        'system_role_id' => 3, // Staff
    ]);
    
    $this->birthcare = BirthCare::factory()->create();
    
    $this->patient = Patient::factory()->create([
        'birth_care_id' => $this->birthcare->id,
    ]);
    
    $this->patientCharge = PatientCharge::factory()->create([
        'birthcare_id' => $this->birthcare->id,
        'service_name' => 'Test Service',
        'price' => 1000.00,
        'is_active' => true,
    ]);
    
    Sanctum::actingAs($this->user);
});

test('it creates a new bill when no active bill exists', function () {
    $response = $this->postJson("/api/birthcare/{$this->birthcare->id}/payments", [
        'patient_id' => $this->patient->id,
        'bill_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
        'items' => [
            [
                'patient_charge_id' => $this->patientCharge->id,
                'service_name' => $this->patientCharge->service_name,
                'description' => 'Test service',
                'quantity' => 1,
                'unit_price' => 1000.00,
            ]
        ]
    ]);
    
    $response->assertStatus(201);
    $response->assertJsonPath('success', true);
    $response->assertJsonPath('message', 'Bill created successfully');
    
    $this->assertDatabaseHas('patient_bills', [
        'patient_id' => $this->patient->id,
        'birthcare_id' => $this->birthcare->id,
        'total_amount' => 1000.00,
        'balance_amount' => 1000.00,
        'paid_amount' => 0,
        'status' => 'draft',
    ]);
    
    $this->assertDatabaseHas('bill_items', [
        'service_name' => $this->patientCharge->service_name,
        'quantity' => 1,
        'unit_price' => 1000.00,
        'total_price' => 1000.00,
    ]);
});

test('it adds charges to existing active bill', function () {
    // Create an existing active bill
    $existingBill = PatientBill::factory()->create([
        'patient_id' => $this->patient->id,
        'birthcare_id' => $this->birthcare->id,
        'total_amount' => 500.00,
        'balance_amount' => 500.00,
        'paid_amount' => 0,
        'status' => 'draft',
    ]);
    
    $response = $this->postJson("/api/birthcare/{$this->birthcare->id}/payments", [
        'patient_id' => $this->patient->id,
        'bill_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
        'items' => [
            [
                'patient_charge_id' => $this->patientCharge->id,
                'service_name' => $this->patientCharge->service_name,
                'description' => 'Additional service',
                'quantity' => 1,
                'unit_price' => 1000.00,
            ]
        ]
    ]);
    
    $response->assertStatus(201);
    $response->assertJsonPath('success', true);
    $response->assertJsonPath('message', 'Charges added to existing bill successfully');
    
    // Should update existing bill, not create new one
    $existingBill->refresh();
    expect($existingBill->total_amount)->toBe(1500.00);
    expect($existingBill->balance_amount)->toBe(1500.00);
    
    // Should only have one bill for this patient
    $billCount = PatientBill::where('patient_id', $this->patient->id)->count();
    expect($billCount)->toBe(1);
});

test('it creates new bill when previous bill is fully paid', function () {
    // Create a fully paid bill
    $paidBill = PatientBill::factory()->create([
        'patient_id' => $this->patient->id,
        'birthcare_id' => $this->birthcare->id,
        'total_amount' => 500.00,
        'balance_amount' => 0.00,
        'paid_amount' => 500.00,
        'status' => 'paid',
    ]);
    
    $response = $this->postJson("/api/birthcare/{$this->birthcare->id}/payments", [
        'patient_id' => $this->patient->id,
        'bill_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
        'items' => [
            [
                'patient_charge_id' => $this->patientCharge->id,
                'service_name' => $this->patientCharge->service_name,
                'description' => 'New service after payment',
                'quantity' => 1,
                'unit_price' => 1000.00,
            ]
        ]
    ]);
    
    $response->assertStatus(201);
    $response->assertJsonPath('success', true);
    $response->assertJsonPath('message', 'Bill created successfully');
    
    // Should create a new bill
    $billCount = PatientBill::where('patient_id', $this->patient->id)->count();
    expect($billCount)->toBe(2);
    
    // New bill should exist with correct amount
    $newBill = PatientBill::where('patient_id', $this->patient->id)
        ->where('id', '!=', $paidBill->id)
        ->first();
    expect($newBill->total_amount)->toBe(1000.00);
    expect($newBill->balance_amount)->toBe(1000.00);
    expect($newBill->status)->toBe('draft');
});

test('it processes payment and updates bill status correctly', function () {
    // Create a bill with balance
    $bill = PatientBill::factory()->create([
        'patient_id' => $this->patient->id,
        'birthcare_id' => $this->birthcare->id,
        'total_amount' => 1000.00,
        'balance_amount' => 1000.00,
        'paid_amount' => 0,
        'status' => 'sent',
    ]);
    
    // Process partial payment
    $response = $this->postJson("/api/birthcare/{$this->birthcare->id}/payments/process", [
        'patient_id' => $this->patient->id,
        'amount' => 400.00,
        'payment_date' => now()->toDateString(),
        'payment_method' => 'cash',
        'notes' => 'Partial payment',
    ]);
    
    $response->assertStatus(200);
    $response->assertJsonPath('success', true);
    $response->assertJsonPath('data.bill.status', 'partially_paid');
    $response->assertJsonPath('data.remaining_balance', 600.00);
    
    $bill->refresh();
    expect($bill->paid_amount)->toBe(400.00);
    expect($bill->balance_amount)->toBe(600.00);
    expect($bill->status)->toBe('partially_paid');
    
    $this->assertDatabaseHas('bill_payments', [
        'patient_bill_id' => $bill->id,
        'amount' => 400.00,
        'payment_method' => 'cash',
        'notes' => 'Partial payment',
    ]);
});

test('it marks bill as paid when full payment is made', function () {
    // Create a bill with balance
    $bill = PatientBill::factory()->create([
        'patient_id' => $this->patient->id,
        'birthcare_id' => $this->birthcare->id,
        'total_amount' => 1000.00,
        'balance_amount' => 1000.00,
        'paid_amount' => 0,
        'status' => 'sent',
    ]);
    
    // Process full payment
    $response = $this->postJson("/api/birthcare/{$this->birthcare->id}/payments/process", [
        'patient_id' => $this->patient->id,
        'amount' => 1000.00,
        'payment_date' => now()->toDateString(),
        'payment_method' => 'cash',
        'notes' => 'Full payment',
    ]);
    
    $response->assertStatus(200);
    $response->assertJsonPath('success', true);
    $response->assertJsonPath('data.bill.status', 'paid');
    $response->assertJsonPath('data.remaining_balance', 0.00);
    
    $bill->refresh();
    expect($bill->paid_amount)->toBe(1000.00);
    expect($bill->balance_amount)->toBe(0.00);
    expect($bill->status)->toBe('paid');
});

test('it prevents payment exceeding balance', function () {
    // Create a bill with small balance
    $bill = PatientBill::factory()->create([
        'patient_id' => $this->patient->id,
        'birthcare_id' => $this->birthcare->id,
        'total_amount' => 500.00,
        'balance_amount' => 500.00,
        'paid_amount' => 0,
        'status' => 'sent',
    ]);
    
    // Attempt to pay more than balance
    $response = $this->postJson("/api/birthcare/{$this->birthcare->id}/payments/process", [
        'patient_id' => $this->patient->id,
        'amount' => 600.00,
        'payment_date' => now()->toDateString(),
        'payment_method' => 'cash',
    ]);
    
    $response->assertStatus(422);
    $response->assertJsonPath('success', false);
    $response->assertJsonFragment(['message' => 'Payment amount exceeds outstanding balance of ₱500.00']);
    
    // Bill should remain unchanged
    $bill->refresh();
    expect($bill->paid_amount)->toBe(0.00);
    expect($bill->balance_amount)->toBe(500.00);
    expect($bill->status)->toBe('sent');
});

test('it shows only active bill charges in SOA', function () {
    // Create a paid bill (historical)
    $paidBill = PatientBill::factory()->create([
        'patient_id' => $this->patient->id,
        'birthcare_id' => $this->birthcare->id,
        'total_amount' => 500.00,
        'balance_amount' => 0.00,
        'paid_amount' => 500.00,
        'status' => 'paid',
    ]);
    
    BillItem::factory()->create([
        'patient_bill_id' => $paidBill->id,
        'service_name' => 'Historical Service',
        'total_price' => 500.00,
    ]);
    
    // Create an active bill
    $activeBill = PatientBill::factory()->create([
        'patient_id' => $this->patient->id,
        'birthcare_id' => $this->birthcare->id,
        'total_amount' => 1000.00,
        'balance_amount' => 800.00,
        'paid_amount' => 200.00,
        'status' => 'partially_paid',
    ]);
    
    BillItem::factory()->create([
        'patient_bill_id' => $activeBill->id,
        'service_name' => 'Current Service',
        'total_price' => 1000.00,
    ]);
    
    // Get SOA
    $response = $this->getJson("/api/birthcare/{$this->birthcare->id}/payments/soa", [
        'patient_id' => $this->patient->id,
    ]);
    
    $response->assertStatus(200);
    $response->assertJsonPath('success', true);
    
    // SOA should show only active bill totals
    $response->assertJsonPath('data.totals.current_charges', 1000.00);
    $response->assertJsonPath('data.totals.current_payments', 200.00);
    $response->assertJsonPath('data.totals.outstanding_balance', 800.00);
    
    // Historical totals should be separate
    $response->assertJsonPath('data.totals.historical_charges', 500.00);
    $response->assertJsonPath('data.totals.historical_payments', 500.00);
    
    // Should have only one itemized charge (from active bill)
    $itemizedCharges = $response->json('data.itemized_charges');
    expect(count($itemizedCharges))->toBe(1);
    expect($itemizedCharges[0]['service_name'])->toBe('Current Service');
    expect($itemizedCharges[0]['bill_id'])->toBe($activeBill->id);
    
    // Should have active bill but not historical bill in main bill data
    $response->assertJsonPath('data.active_bill.id', $activeBill->id);
});

test('it shows no active charges when patient has only paid bills', function () {
    // Create only paid bills
    $paidBill = PatientBill::factory()->create([
        'patient_id' => $this->patient->id,
        'birthcare_id' => $this->birthcare->id,
        'total_amount' => 500.00,
        'balance_amount' => 0.00,
        'paid_amount' => 500.00,
        'status' => 'paid',
    ]);
    
    // Get SOA
    $response = $this->getJson("/api/birthcare/{$this->birthcare->id}/payments/soa", [
        'patient_id' => $this->patient->id,
    ]);
    
    $response->assertStatus(200);
    $response->assertJsonPath('success', true);
    $response->assertJsonPath('data.soa_status', 'Paid');
    $response->assertJsonPath('data.totals.current_charges', 0);
    $response->assertJsonPath('data.totals.outstanding_balance', 0);
    $response->assertJsonPath('data.active_bill', null);
    
    // Should have empty itemized charges (no active bill)
    $itemizedCharges = $response->json('data.itemized_charges');
    expect(count($itemizedCharges))->toBe(0);
});

test('it handles multiple partial payments correctly', function () {
    // Create a bill
    $bill = PatientBill::factory()->create([
        'patient_id' => $this->patient->id,
        'birthcare_id' => $this->birthcare->id,
        'total_amount' => 1000.00,
        'balance_amount' => 1000.00,
        'paid_amount' => 0,
        'status' => 'sent',
    ]);
    
    // First partial payment
    $this->postJson("/api/birthcare/{$this->birthcare->id}/payments/process", [
        'patient_id' => $this->patient->id,
        'amount' => 300.00,
        'payment_date' => now()->toDateString(),
        'payment_method' => 'cash',
    ]);
    
    $bill->refresh();
    expect($bill->status)->toBe('partially_paid');
    expect($bill->balance_amount)->toBe(700.00);
    
    // Second partial payment
    $this->postJson("/api/birthcare/{$this->birthcare->id}/payments/process", [
        'patient_id' => $this->patient->id,
        'amount' => 400.00,
        'payment_date' => now()->toDateString(),
        'payment_method' => 'cash',
    ]);
    
    $bill->refresh();
    expect($bill->status)->toBe('partially_paid');
    expect($bill->balance_amount)->toBe(300.00);
    expect($bill->paid_amount)->toBe(700.00);
    
    // Final payment
    $this->postJson("/api/birthcare/{$this->birthcare->id}/payments/process", [
        'patient_id' => $this->patient->id,
        'amount' => 300.00,
        'payment_date' => now()->toDateString(),
        'payment_method' => 'cash',
    ]);
    
    $bill->refresh();
    expect($bill->status)->toBe('paid');
    expect($bill->balance_amount)->toBe(0.00);
    expect($bill->paid_amount)->toBe(1000.00);
    
    // Should have 3 payment records
    $paymentCount = BillPayment::where('patient_bill_id', $bill->id)->count();
    expect($paymentCount)->toBe(3);
});

test('it returns error when no active bill exists for payment', function () {
    // Try to process payment for patient with no bills
    $response = $this->postJson("/api/birthcare/{$this->birthcare->id}/payments/process", [
        'patient_id' => $this->patient->id,
        'amount' => 100.00,
        'payment_date' => now()->toDateString(),
        'payment_method' => 'cash',
    ]);
    
    $response->assertStatus(404);
    $response->assertJsonPath('success', false);
    $response->assertJsonPath('message', 'No active bill found for this patient');
});