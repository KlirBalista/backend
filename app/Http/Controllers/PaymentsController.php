<?php

namespace App\Http\Controllers;

use App\Models\PatientBill;
use App\Models\BillItem;
use App\Models\BillPayment;
use App\Models\Patient;
use App\Models\PatientCharge;
use App\Models\PatientAdmission;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Services\PDFService;

class PaymentsController extends Controller
{
    /**
     * Display a listing of payments.
     */
    public function index(Request $request, $birthcare_id)
    {
        try {
            $query = PatientBill::with(['patient', 'creator', 'items', 'payments'])
                ->forBirthcare($birthcare_id)
                ->orderBy('created_at', 'desc');

            // Apply filters
            if ($request->has('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
            }

            if ($request->has('patient_id') && $request->patient_id) {
                $query->where('patient_id', $request->patient_id);
            }

            if ($request->has('date_from') && $request->date_from) {
                $query->whereDate('bill_date', '>=', $request->date_from);
            }

            if ($request->has('date_to') && $request->date_to) {
                $query->whereDate('bill_date', '<=', $request->date_to);
            }

            $bills = $query->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $bills,
                'summary' => $this->getPaymentsSummary($birthcare_id)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch payments',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created bill (charges).
     */
    public function store(Request $request, $birthcare_id)
    {
        try {
            $validated = $request->validate([
                'patient_id' => 'required|exists:patients,id',
                'bill_date' => 'required|date',
                'due_date' => 'required|date|after_or_equal:bill_date',
                'tax_amount' => 'numeric|min:0',
                'discount_amount' => 'numeric|min:0',
                'notes' => 'nullable|string',
                'items' => 'required|array|min:1',
                'items.*.patient_charge_id' => 'nullable|exists:patient_charges,id',
                'items.*.service_name' => 'required|string|max:255',
                'items.*.description' => 'nullable|string',
                'items.*.quantity' => 'required|integer|min:1',
                'items.*.unit_price' => 'required|numeric|min:0',
            ]);

            DB::beginTransaction();

            // Find existing active bill (unpaid or partially paid) for this patient
            $bill = PatientBill::where('birthcare_id', $birthcare_id)
                ->where('patient_id', $validated['patient_id'])
                ->whereIn('status', ['draft', 'sent', 'partially_paid', 'overdue'])
                ->where('balance_amount', '>', 0)
                ->first();

            // Create new bill only if no active bill exists
            if (!$bill) {
                $bill = PatientBill::create([
                    'birthcare_id' => $birthcare_id,
                    'patient_id' => $validated['patient_id'],
                    'bill_number' => PatientBill::generateBillNumber(),
                    'bill_date' => $validated['bill_date'],
                    'due_date' => $validated['due_date'],
                    'tax_amount' => $validated['tax_amount'] ?? 0,
                    'discount_amount' => $validated['discount_amount'] ?? 0,
                    'subtotal' => 0,
                    'total_amount' => 0,
                    'paid_amount' => 0,
                    'balance_amount' => 0,
                    'status' => 'draft',
                    'notes' => $validated['notes'],
                    'created_by' => Auth::id(),
                ]);
            } else {
                // Update existing bill dates and notes if provided
                if ($validated['notes'] && $validated['notes'] !== $bill->notes) {
                    $bill->notes = $bill->notes ? $bill->notes . "\n" . $validated['notes'] : $validated['notes'];
                }
                // Update tax/discount if provided
                if (isset($validated['tax_amount'])) {
                    $bill->tax_amount += $validated['tax_amount'];
                }
                if (isset($validated['discount_amount'])) {
                    $bill->discount_amount += $validated['discount_amount'];
                }
            }

            // Add new bill items to existing or new bill
            foreach ($validated['items'] as $itemData) {
                BillItem::create([
                    'patient_bill_id' => $bill->id,
                    'patient_charge_id' => $itemData['patient_charge_id'],
                    'service_name' => $itemData['service_name'],
                    'description' => $itemData['description'],
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $itemData['unit_price'],
                    'total_price' => $itemData['quantity'] * $itemData['unit_price'],
                ]);
            }

            // Recalculate totals
            $bill->load('items');
            $bill->calculateTotals();
            
            // Update bill status after adding charges
            if ($bill->status === 'draft' && $bill->total_amount > 0) {
                $bill->status = 'sent'; // Change from draft to sent when charges are added
            }
            
            // Also call updatePaymentStatus to ensure status is correctly calculated
            $bill->load('payments');
            $bill->updatePaymentStatus();

            DB::commit();

            $bill->load(['patient', 'creator', 'items', 'payments']);

            return response()->json([
                'success' => true,
                'data' => $bill,
                'message' => $bill->wasRecentlyCreated ? 'Bill created successfully' : 'Charges added to existing bill successfully'
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create/update bill',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified payment.
     */
    public function show($birthcare_id, $bill_id)
    {
        try {
            $bill = PatientBill::with(['patient', 'creator', 'items.patientCharge', 'payments.receiver'])
                ->forBirthcare($birthcare_id)
                ->findOrFail($bill_id);

            return response()->json([
                'success' => true,
                'data' => $bill
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Payment not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update the specified payment.
     */
    public function update(Request $request, $birthcare_id, $bill_id)
    {
        try {
            $bill = PatientBill::forBirthcare($birthcare_id)->findOrFail($bill_id);

            // Only allow editing draft payments
            if ($bill->status !== 'draft') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only draft payments can be edited'
                ], 422);
            }

            $validated = $request->validate([
                'bill_date' => 'required|date',
                'due_date' => 'required|date|after_or_equal:bill_date',
                'tax_amount' => 'numeric|min:0',
                'discount_amount' => 'numeric|min:0',
                'notes' => 'nullable|string',
                'items' => 'required|array|min:1',
                'items.*.patient_charge_id' => 'nullable|exists:patient_charges,id',
                'items.*.service_name' => 'required|string|max:255',
                'items.*.description' => 'nullable|string',
                'items.*.quantity' => 'required|integer|min:1',
                'items.*.unit_price' => 'required|numeric|min:0',
            ]);

            DB::beginTransaction();

            // Update bill
            $bill->update([
                'bill_date' => $validated['bill_date'],
                'due_date' => $validated['due_date'],
                'tax_amount' => $validated['tax_amount'] ?? 0,
                'discount_amount' => $validated['discount_amount'] ?? 0,
                'notes' => $validated['notes'],
            ]);

            // Delete existing items
            $bill->items()->delete();

            // Create new items
            foreach ($validated['items'] as $itemData) {
                BillItem::create([
                    'patient_bill_id' => $bill->id,
                    'patient_charge_id' => $itemData['patient_charge_id'],
                    'service_name' => $itemData['service_name'],
                    'description' => $itemData['description'],
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $itemData['unit_price'],
                    'total_price' => $itemData['quantity'] * $itemData['unit_price'],
                ]);
            }

            // Recalculate totals
            $bill->load('items');
            $bill->calculateTotals();
            $bill->save();

            DB::commit();

            $bill->load(['patient', 'creator', 'items', 'payments']);

            return response()->json([
                'success' => true,
                'data' => $bill,
                'message' => 'Payment updated successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified payment.
     */
    public function destroy($birthcare_id, $bill_id)
    {
        try {
            $bill = PatientBill::forBirthcare($birthcare_id)->findOrFail($bill_id);

            // Only allow deleting draft payments
            if ($bill->status !== 'draft') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only draft payments can be deleted'
                ], 422);
            }

            $bill->delete();

            return response()->json([
                'success' => true,
                'message' => 'Payment deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update payment status.
     */
    public function updateStatus(Request $request, $birthcare_id, $bill_id)
    {
        try {
            $validated = $request->validate([
                'status' => 'required|in:draft,sent,cancelled'
            ]);

            $bill = PatientBill::forBirthcare($birthcare_id)->findOrFail($bill_id);

            $bill->update(['status' => $validated['status']]);

            return response()->json([
                'success' => true,
                'data' => $bill,
                'message' => 'Payment status updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update payment status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add payment to a bill.
     */
    public function addPayment(Request $request, $birthcare_id, $bill_id)
    {
        try {
            $bill = PatientBill::forBirthcare($birthcare_id)->findOrFail($bill_id);

            $validated = $request->validate([
                'amount' => 'required|numeric|min:0.01|max:' . $bill->balance_amount,
                'payment_date' => 'required|date',
                'payment_method' => 'required|in:cash,credit_card,philhealth,dswd,doh,hmo,private,others',
                'reference_number' => 'nullable|string|max:255',
                'notes' => 'nullable|string'
            ]);

            DB::beginTransaction();

            $payment = BillPayment::create([
                'patient_bill_id' => $bill->id,
                'payment_number' => BillPayment::generatePaymentNumber(),
                'payment_date' => $validated['payment_date'],
                'amount' => $validated['amount'],
                'payment_method' => $validated['payment_method'],
                'reference_number' => $validated['reference_number'],
                'notes' => $validated['notes'],
                'received_by' => Auth::id(),
            ]);

            // Update bill payment status
            $bill->updatePaymentStatus();

            DB::commit();

            $payment->load('receiver');

            return response()->json([
                'success' => true,
                'data' => $payment,
                'bill' => $bill->fresh(['payments']),
                'message' => 'Payment added successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to add payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process payment for a patient (finds active bill automatically).
     */
    public function processPayment(Request $request, $birthcare_id)
    {
        try {
            $validated = $request->validate([
                'patient_id' => 'required|exists:patients,id',
                'bill_id' => 'nullable|exists:patient_bills,id',
                'amount' => 'required|numeric|min:0.01',
                'payment_date' => 'required|date',
                'payment_method' => 'required|in:cash,credit_card,debit_card,bank_transfer,check,philhealth,dswd,doh,hmo,private,others',
                'reference_number' => 'nullable|string|max:255',
                'notes' => 'nullable|string'
            ]);

            DB::beginTransaction();

            // Find specific bill if bill_id provided, otherwise find active bill automatically
            if (isset($validated['bill_id'])) {
                $bill = PatientBill::where('birthcare_id', $birthcare_id)
                    ->where('patient_id', $validated['patient_id'])
                    ->where('id', $validated['bill_id'])
                    ->first();
            } else {
                // Debug: Log search parameters
                \Log::info('Searching for active bill', [
                    'birthcare_id' => $birthcare_id,
                    'patient_id' => $validated['patient_id']
                ]);
                
                // First, check all bills for this patient
                $allBills = PatientBill::where('birthcare_id', $birthcare_id)
                    ->where('patient_id', $validated['patient_id'])
                    ->get(['id', 'status', 'balance_amount', 'bill_date', 'total_amount']);
                    
                \Log::info('All bills for patient', ['bills' => $allBills->toArray()]);
                
                // Find the active bill for this patient
                $bill = PatientBill::where('birthcare_id', $birthcare_id)
                    ->where('patient_id', $validated['patient_id'])
                    ->whereIn('status', ['draft', 'sent', 'partially_paid', 'overdue'])
                    ->where('balance_amount', '>', 0)
                    ->orderBy('bill_date', 'asc') // Pay oldest bill first
                    ->first();
                    
                \Log::info('Active bill found', ['bill' => $bill ? $bill->toArray() : null]);
            }

            if (!$bill) {
                \Log::error('No active bill found for payment processing', [
                    'birthcare_id' => $birthcare_id,
                    'patient_id' => $validated['patient_id']
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'No active bill found for this patient. Please create charges in the Patient Charges system first before accepting payment.',
                    'error_code' => 'NO_BILL_FOUND'
                ], 422);
            }

            // Validate payment amount doesn't exceed balance
            if ($validated['amount'] > $bill->balance_amount) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment amount exceeds outstanding balance of ₱' . number_format($bill->balance_amount, 2)
                ], 422);
            }

            // Create the payment
            $payment = BillPayment::create([
                'patient_bill_id' => $bill->id,
                'payment_number' => BillPayment::generatePaymentNumber(),
                'payment_date' => $validated['payment_date'],
                'amount' => $validated['amount'],
                'payment_method' => $validated['payment_method'],
                'reference_number' => $validated['reference_number'],
                'notes' => $validated['notes'],
                'received_by' => Auth::id() ?? 1, // Fallback to user ID 1 if not authenticated
            ]);

            // Update bill payment status and amounts
            $bill->updatePaymentStatus();

            DB::commit();

            $payment->load('receiver');
            $bill->load(['patient', 'payments']);

            return response()->json([
                'success' => true,
                'data' => [
                    'payment' => $payment,
                    'bill' => $bill,
                    'remaining_balance' => $bill->balance_amount,
                    'status' => $bill->status
                ],
                'message' => $bill->status === 'paid' 
                    ? 'Payment processed successfully. Bill is now fully paid.' 
                    : 'Partial payment processed successfully. Remaining balance: ₱' . number_format($bill->balance_amount, 2)
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to process payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payments for a specific bill.
     */
    public function getBillPayments($birthcare_id, $bill_id)
    {
        try {
            $bill = PatientBill::with('payments.receiver')
                ->forBirthcare($birthcare_id)
                ->findOrFail($bill_id);

            return response()->json([
                'success' => true,
                'data' => $bill->payments
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch bill payments',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payments dashboard data.
     */
    public function dashboard($birthcare_id)
    {
        try {
            \Log::info('Dashboard request', ['birthcare_id' => $birthcare_id]);
            
            // Get summary data
            $summary = $this->getPaymentsSummary($birthcare_id);
            \Log::info('Summary data retrieved successfully');
            
            // Get recent bills with their payments
            $recentBills = PatientBill::with(['patient', 'payments'])
                ->forBirthcare($birthcare_id)
                ->orderBy('created_at', 'desc')
                ->take(5)
                ->get();
            \Log::info('Recent bills retrieved', ['count' => $recentBills->count()]);

            // Get overdue count
            $overdueCount = PatientBill::forBirthcare($birthcare_id)
                ->overdue()
                ->count();
            \Log::info('Overdue count', ['count' => $overdueCount]);

            // Monthly revenue by actual payments received (MySQL)
            $monthlyRevenue = BillPayment::whereHas('bill', function($query) use ($birthcare_id) {
                    $query->forBirthcare($birthcare_id);
                })
                ->whereYear('payment_date', date('Y'))
                ->selectRaw('MONTH(payment_date) as month, SUM(amount) as revenue')
                ->groupBy('month')
                ->pluck('revenue', 'month');

            // Fallback: if no payments yet, use billed totals per month
            if ($monthlyRevenue->isEmpty()) {
                $monthlyRevenue = PatientBill::forBirthcare($birthcare_id)
                    ->whereYear('bill_date', date('Y'))
                    ->selectRaw('MONTH(bill_date) as month, SUM(total_amount) as revenue')
                    ->groupBy('month')
                    ->pluck('revenue', 'month');

                \Log::info('Monthly revenue fallback from PatientBill.total_amount used', [
                    'entries' => $monthlyRevenue->count(),
                ]);
            }

            \Log::info('Monthly revenue data retrieved', ['entries' => $monthlyRevenue->count()]);

            return response()->json([
                'success' => true,
                'data' => [
                    'summary' => $summary,
                    'recent_bills' => $recentBills,
                    'overdue_count' => $overdueCount,
                    'monthly_revenue' => $monthlyRevenue
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('Dashboard error: ' . $e->getMessage(), [
                'birthcare_id' => $birthcare_id,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch dashboard data: ' . $e->getMessage(),
                'error' => $e->getMessage(),
                'debug' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]
            ], 500);
        }
    }

    /**
     * Get patients for payments (admitted patients only).
     */
    public function getPatients($birthcare_id)
    {
        try {
            // Log for debugging
            \Log::info('Fetching admitted patients', ['birthcare_id' => $birthcare_id]);
            
            // First, let's check if we have any patient admissions at all
            $admissionCount = PatientAdmission::where('birth_care_id', $birthcare_id)->count();
            \Log::info('Total admissions for birthcare', ['count' => $admissionCount]);
            
            $activeAdmissionCount = PatientAdmission::where('birth_care_id', $birthcare_id)
                ->whereIn('status', ['admitted', 'in-labor', 'delivered', 'active'])
                ->count();
            \Log::info('Active admissions for birthcare', ['count' => $activeAdmissionCount]);
            
            // Get unique patients who are currently admitted (broader status filter)
            $admissionIds = PatientAdmission::where('birth_care_id', $birthcare_id)
                ->whereIn('status', ['admitted', 'in-labor', 'delivered', 'active'])
                ->groupBy('patient_id')
                ->selectRaw('MAX(id) as id')
                ->pluck('id');
            
            \Log::info('Found admission IDs', ['ids' => implode(', ', $admissionIds->toArray())]);

            // Fetch admissions with relationships
            $admissions = PatientAdmission::with(['patient', 'room'])
                ->whereIn('id', $admissionIds)
                ->orderBy('admission_date', 'desc')
                ->get();
            
            \Log::info('Loaded admissions with relationships', ['count' => $admissions->count()]);

            $admittedPatients = $admissions->map(function ($admission) {
                // Get ALL billing information for this patient (including completed bills)
                $allBills = PatientBill::where('patient_id', $admission->patient_id)
                    ->where('birthcare_id', $admission->birth_care_id)
                    ->orderBy('bill_date', 'desc')
                    ->get();
                
                // Get the most recent active (unpaid) bill
                $activeBill = $allBills->whereIn('status', ['draft', 'sent', 'partially_paid', 'overdue'])
                    ->where('balance_amount', '>', 0)
                    ->first();
                
                // Calculate totals from active bill or most recent bill
                $displayBill = $activeBill ?: $allBills->first();
                $billCharges = $displayBill ? $displayBill->total_amount : 0;
                $totalPayments = $displayBill ? $displayBill->paid_amount : 0;
                
                // Calculate room accommodation charges
                $roomCharges = 0;
                if ($admission->room && $admission->room->price) {
                    $endDate = $admission->discharge_date ? \Carbon\Carbon::parse($admission->discharge_date) : \Carbon\Carbon::now();
                    $startDate = \Carbon\Carbon::parse($admission->admission_date);
                    
                    // Normalize to start of day to count calendar days only
                    $endDate = $endDate->startOfDay();
                    $startDate = $startDate->startOfDay();
                    
                    $daysStayed = max(1, $startDate->diffInDays($endDate) + 1);
                    $roomCharges = floatval($admission->room->price) * $daysStayed;
                }
                
                // Calculate REAL total charges including room accommodation
                $totalCharges = $billCharges + $roomCharges;
                $outstandingBalance = $totalCharges - $totalPayments;
                
                // Determine payment status based on active bills and total situation
                $paymentStatus = 'pending';
                if ($allBills->isEmpty() || $totalCharges <= 0.01) {
                    $paymentStatus = 'pending'; // No charges yet
                } elseif ($activeBill && $outstandingBalance > 0.01) {
                    if ($totalPayments > 0.01) {
                        $paymentStatus = 'partial'; // Partially paid
                    } else {
                        $paymentStatus = 'pending'; // Unpaid
                    }
                } else {
                    $paymentStatus = 'paid'; // Fully paid or no active bills
                }
                
                return [
                    'id' => $admission->patient_id, // Use patient_id for compatibility with existing payment forms
                    'patient_id' => $admission->patient_id,
                    'admission_id' => $admission->id,
                    'first_name' => $admission->patient->first_name ?? 'Unknown',
                    'last_name' => $admission->patient->last_name ?? 'Unknown',
                    'middle_name' => $admission->patient->middle_name ?? '',
                    'phone' => $admission->patient->contact_number ?? '',
                    'admission_date' => $admission->admission_date ? $admission->admission_date->format('Y-m-d') : null,
                    'room_number' => $admission->room ? $admission->room->name : 'N/A',
                    'status' => ucfirst($admission->status),
                    // Additional data for frontend compatibility
                    'firstname' => $admission->patient->first_name ?? 'Unknown',
                    'lastname' => $admission->patient->last_name ?? 'Unknown',
                    'middlename' => $admission->patient->middle_name ?? '',
                    'age' => $admission->patient->age ?? null,
                    'room_type' => 'Room', // Default value
                    // Billing information
                    'total_charges' => $totalCharges,
                    'total_payments' => $totalPayments,
                    'outstanding_balance' => $outstandingBalance,
                    'payment_status' => $paymentStatus,
                    'last_payment_date' => $displayBill ? $displayBill->bill_date->format('Y-m-d') : null,
                ];
            });

            // Try to fetch ALL admitted patients if none found for specific birthcare
            if ($admittedPatients->isEmpty()) {
                \Log::info('No admitted patients found, fetching all admitted patients', ['birthcare_id' => $birthcare_id]);
                
                // Get all admitted patients regardless of birthcare_id
                $allAdmissions = PatientAdmission::with(['patient', 'room'])
                    ->whereIn('status', ['admitted', 'in-labor', 'delivered', 'active'])
                    ->orderBy('admission_date', 'desc')
                    ->get();
                
                $admittedPatients = $allAdmissions->map(function ($admission) {
                    // Get ALL billing information for this patient (including completed bills)
                    $allBills = PatientBill::where('patient_id', $admission->patient_id)
                        ->where('birthcare_id', $admission->birth_care_id)
                        ->orderBy('bill_date', 'desc')
                        ->get();
                    
                    // Get the most recent active (unpaid) bill
                    $activeBill = $allBills->whereIn('status', ['draft', 'sent', 'partially_paid', 'overdue'])
                        ->where('balance_amount', '>', 0)
                        ->first();
                    
                    // Calculate totals from active bill or most recent bill
                    $displayBill = $activeBill ?: $allBills->first();
                    $billCharges = $displayBill ? $displayBill->total_amount : 0;
                    $totalPayments = $displayBill ? $displayBill->paid_amount : 0;
                    
                    // Calculate room accommodation charges
                    $roomCharges = 0;
                    if ($admission->room && $admission->room->price) {
                        $endDate = $admission->discharge_date ? \Carbon\Carbon::parse($admission->discharge_date) : \Carbon\Carbon::now();
                        $startDate = \Carbon\Carbon::parse($admission->admission_date);
                        
                        // Normalize to start of day to count calendar days only
                        $endDate = $endDate->startOfDay();
                        $startDate = $startDate->startOfDay();
                        
                        $daysStayed = max(1, $startDate->diffInDays($endDate) + 1);
                        $roomCharges = floatval($admission->room->price) * $daysStayed;
                    }
                    
                    // Calculate REAL total charges including room accommodation
                    $totalCharges = $billCharges + $roomCharges;
                    $outstandingBalance = $totalCharges - $totalPayments;
                    
                    // Determine payment status based on active bills and total situation
                    $paymentStatus = 'pending';
                    if ($allBills->isEmpty() || $totalCharges <= 0.01) {
                        $paymentStatus = 'pending'; // No charges yet
                    } elseif ($activeBill && $outstandingBalance > 0.01) {
                        if ($totalPayments > 0.01) {
                            $paymentStatus = 'partial'; // Partially paid
                        } else {
                            $paymentStatus = 'pending'; // Unpaid
                        }
                    } else {
                        $paymentStatus = 'paid'; // Fully paid or no active bills
                    }
                    
                    return [
                        'id' => $admission->patient_id,
                        'patient_id' => $admission->patient_id,
                        'admission_id' => $admission->id,
                        'first_name' => $admission->patient->first_name ?? 'Unknown',
                        'last_name' => $admission->patient->last_name ?? 'Unknown',
                        'middle_name' => $admission->patient->middle_name ?? '',
                        'phone' => $admission->patient->contact_number ?? '',
                        'admission_date' => $admission->admission_date ? $admission->admission_date->format('Y-m-d') : null,
                        'room_number' => $admission->room ? $admission->room->name : 'N/A',
                        'status' => ucfirst($admission->status),
                        'firstname' => $admission->patient->first_name ?? 'Unknown',
                        'lastname' => $admission->patient->last_name ?? 'Unknown',
                        'middlename' => $admission->patient->middle_name ?? '',
                        'age' => $admission->patient->age ?? null,
                        'room_type' => 'Room',
                        'birth_care_id' => $admission->birth_care_id,
                        // Billing information
                        'total_charges' => $totalCharges,
                        'total_payments' => $totalPayments,
                        'outstanding_balance' => $outstandingBalance,
                        'payment_status' => $paymentStatus,
                        'last_payment_date' => $bill ? $bill->bill_date->format('Y-m-d') : null,
                    ];
                });
                
                \Log::info('Found total admitted patients', ['count' => $admittedPatients->count()]);
            }

            return response()->json([
                'success' => true,
                'data' => $admittedPatients
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to fetch admitted patients: ' . $e->getMessage(), [
                'birthcare_id' => $birthcare_id,
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch admitted patients',
                'error' => $e->getMessage(),
                'debug' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]
            ], 500);
        }
    }

    /**
     * Create test billing data for demo purposes - REMOVE IN PRODUCTION
     */
    public function createTestData($birthcare_id)
    {
        try {
            // Only run in development/testing
            if (app()->environment('production')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Test data creation not allowed in production'
                ], 403);
            }

            DB::beginTransaction();

            // Get some admitted patients
            $admissions = PatientAdmission::where('birth_care_id', $birthcare_id)
                ->whereIn('status', ['admitted', 'in-labor', 'delivered', 'active'])
                ->with('patient')
                ->limit(5)
                ->get();

            $billsCreated = 0;
            foreach ($admissions as $admission) {
                // Check if bill already exists
                $existingBill = PatientBill::where('patient_id', $admission->patient_id)
                    ->where('birthcare_id', $birthcare_id)
                    ->first();

                if (!$existingBill) {
                    // Create a test bill with random amounts
                    $totalAmount = rand(15000, 45000); // Random bill between 15k-45k
                    $paidAmount = rand(0, $totalAmount); // Random payment up to total
                    $balanceAmount = $totalAmount - $paidAmount;

                    $bill = PatientBill::create([
                        'patient_id' => $admission->patient_id,
                        'birthcare_id' => $birthcare_id,
                        'bill_number' => PatientBill::generateBillNumber(),
                        'bill_date' => now()->subDays(rand(1, 30)),
                        'due_date' => now()->addDays(30),
                        'total_amount' => $totalAmount,
                        'paid_amount' => $paidAmount,
                        'balance_amount' => $balanceAmount,
                        'status' => $balanceAmount > 0 ? ($paidAmount > 0 ? 'partially_paid' : 'sent') : 'paid',
                        'created_by' => 1,
                    ]);

                    // Add some sample bill items
                    $services = [
                        ['name' => 'Normal Delivery Package', 'price' => 25000],
                        ['name' => 'Room Accommodation', 'price' => 1500],
                        ['name' => 'Laboratory Tests', 'price' => 2000],
                        ['name' => 'Medical Supplies', 'price' => 3500],
                        ['name' => 'Professional Fees', 'price' => 5000]
                    ];

                    foreach (array_slice($services, 0, rand(2, 4)) as $service) {
                        BillItem::create([
                            'patient_bill_id' => $bill->id,
                            'service_name' => $service['name'],
                            'description' => 'Test service for ' . $service['name'],
                            'quantity' => 1,
                            'unit_price' => $service['price'],
                            'total_price' => $service['price'],
                        ]);
                    }

                    $billsCreated++;
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Created $billsCreated test bills for patients",
                'data' => [
                    'bills_created' => $billsCreated,
                    'patients_processed' => $admissions->count()
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create test data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get patient charges (services) available for billing admitted patients.
     */
    public function getPatientCharges($birthcare_id)
    {
        try {
            $services = PatientCharge::where('birthcare_id', $birthcare_id)
                ->where('is_active', true)
                ->orderBy('service_name')
                ->get();
            
            // If no services found for specific birthcare, try to get all services
            if ($services->isEmpty()) {
                \Log::info('No services found, fetching all services', ['birthcare_id' => $birthcare_id]);
                $services = PatientCharge::where('is_active', true)
                    ->orderBy('service_name')
                    ->get();
            }

            return response()->json([
                'success' => true,
                'data' => $services
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to fetch patient charges: ' . $e->getMessage(), [
                'birthcare_id' => $birthcare_id,
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch patient charges',
                'error' => $e->getMessage(),
                'debug' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]
            ], 500);
        }
    }

    /**
     * Get actual charges (bills) for admitted patients.
     */
    public function getAdmittedPatientsCharges($birthcare_id)
    {
        try {
            // Get unique patients who are in-labor or delivered
            $admissionIds = PatientAdmission::where('birth_care_id', $birthcare_id)
                ->whereIn('status', ['in-labor', 'delivered'])
                ->groupBy('patient_id')
                ->selectRaw('MAX(id) as id')
                ->pluck('id');

            $admissions = PatientAdmission::with(['patient'])
                ->whereIn('id', $admissionIds)
                ->get();

            $patientIds = $admissions->pluck('patient_id');

            // Get bills for these patients
            $bills = PatientBill::with(['patient', 'items', 'payments'])
                ->whereIn('patient_id', $patientIds)
                ->where('birthcare_id', $birthcare_id)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($bill) use ($admissions) {
                    $admission = $admissions->firstWhere('patient_id', $bill->patient_id);
                    return [
                        'id' => $bill->id,
                        'bill_number' => $bill->bill_number,
                        'patient_id' => $bill->patient_id,
                        'patient' => [
                            'id' => $bill->patient->id,
                            'first_name' => $bill->patient->first_name,
                            'last_name' => $bill->patient->last_name,
                            'middle_name' => $bill->patient->middle_name,
                        ],
                        'total_amount' => $bill->total_amount,
                        'paid_amount' => $bill->paid_amount,
                        'balance_amount' => $bill->balance_amount,
                        'status' => $bill->status,
                        'bill_date' => $bill->bill_date,
                        'due_date' => $bill->due_date,
                        'items' => $bill->items,
                        'payments' => $bill->payments,
                        'room_number' => $admission ? ($admission->room->name ?? 'N/A') : 'N/A',
                        'admission_date' => $admission ? $admission->admission_date->format('Y-m-d') : null,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $bills
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to fetch admitted patients charges: ' . $e->getMessage(), [
                'birthcare_id' => $birthcare_id,
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch admitted patients charges',
                'error' => $e->getMessage(),
                'debug' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]
            ], 500);
        }
    }

    /**
     * Trigger scheduler to calculate room accommodation charges for a patient.
     */
    public function triggerRoomChargeScheduler(Request $request, $birthcare_id)
    {
        try {
            $validated = $request->validate([
                'patient_id' => 'required|integer|exists:patients,id'
            ]);

            $patientId = $validated['patient_id'];

            // Get the patient's admission for this birthcare facility
            $admission = PatientAdmission::where('patient_id', $patientId)
                ->where('birth_care_id', $birthcare_id)
                ->whereIn('status', ['admitted', 'active', 'in-labor', 'delivered'])
                ->latest('admission_date')
                ->first();

            if (!$admission) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active admission found for this patient'
                ], 404);
            }

            // Call the backfill command to ensure all room charges are calculated
            try {
                \Illuminate\Support\Facades\Artisan::call('billing:backfill-room-charges', [
                    '--patient-id' => $patientId
                ]);
                
                $output = \Illuminate\Support\Facades\Artisan::output();
                
                return response()->json([
                    'success' => true,
                    'message' => 'Room accommodation charges have been calculated and updated',
                    'data' => [
                        'patient_id' => $patientId,
                        'admission_id' => $admission->id,
                        'command_output' => $output
                    ]
                ]);
            } catch (\Exception $e) {
                \Log::error('Failed to run room charge scheduler: ' . $e->getMessage(), [
                    'patient_id' => $patientId,
                    'admission_id' => $admission->id,
                    'trace' => $e->getTraceAsString()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to calculate room accommodation charges: ' . $e->getMessage(),
                    'error' => $e->getMessage()
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to trigger room charge scheduler',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get comprehensive Statement of Account data for a patient.
     */
    public function getStatementOfAccount(Request $request, $birthcare_id)
    {
        try {
            $validated = $request->validate([
                'patient_id' => 'required|integer|exists:patients,id',
                'admission_id' => 'nullable|integer|exists:patient_admissions,id'
            ]);

            $patientId = $validated['patient_id'];
            $admissionId = $validated['admission_id'] ?? null;
            
            \Log::info('SOA Request received', [
                'patient_id' => $patientId,
                'admission_id' => $admissionId,
                'birthcare_id' => $birthcare_id
            ]);

            // Get patient information with latest admission if not specified
            $patient = Patient::with([
                'prenatalVisits' => function($query) {
                    $query->latest()->limit(1);
                }
            ])->findOrFail($patientId);

            // Get admission info
            $admission = null;
            if ($admissionId) {
                $admission = PatientAdmission::with(['room', 'bed'])
                    ->where('patient_id', $patientId)
                    ->where('birth_care_id', $birthcare_id)
                    ->findOrFail($admissionId);
            } else {
                // Get the most recent admission
                $admission = PatientAdmission::with(['room', 'bed'])
                    ->where('patient_id', $patientId)
                    ->where('birth_care_id', $birthcare_id)
                    ->latest('admission_date')
                    ->first();
            }

            // Get ALL bills for comprehensive SOA (not just one bill)
            $allBills = PatientBill::with([
                'items.patientCharge',
                'payments.receiver'
            ])
            ->where('patient_id', $patientId)
            ->where('birthcare_id', $birthcare_id)
            ->orderBy('bill_date', 'desc')
            ->get();

            // Get the most recent active bill for reference
            $activeBill = $allBills->whereIn('status', ['draft', 'sent', 'partially_paid', 'overdue'])
                ->where('balance_amount', '>', 0)
                ->first();
            
            // Debug: Log all bills found for this patient
            \Log::info('SOA Bills found for patient', [
                'patient_id' => $patientId,
                'bills_count' => $allBills->count(),
                'bills_summary' => $allBills->map(function($bill) {
                    return [
                        'id' => $bill->id,
                        'bill_number' => $bill->bill_number,
                        'status' => $bill->status,
                        'total_amount' => $bill->total_amount,
                        'items_count' => $bill->items->count()
                    ];
                })
            ]);
            
            // Calculate CONSOLIDATED totals from ALL bills
            $totalCharges = $allBills->sum('total_amount');
            $totalPayments = $allBills->sum('paid_amount');
            $outstandingBalance = $allBills->where('status', '!=', 'paid')->sum('balance_amount');
            
            // Get itemized charges from ALL bills (consolidated view)
            // Ensure SOA shows EXACTLY what was charged in Patient Charges system
            $allItems = [];
            foreach ($allBills as $bill) {
                foreach ($bill->items as $item) {
                    $isAutoDaily = false;
                    // Heuristic: auto daily room charges have a date in service name or a specific description
                    if (preg_match('/\(\d{4}-\d{2}-\d{2}\)/', (string)$item->service_name)) {
                        $isAutoDaily = true;
                    }
                    if (stripos((string)$item->description, 'Daily room accommodation') !== false) {
                        $isAutoDaily = true;
                    }

                    $allItems[] = [
                        'bill_id' => $bill->id,
                        'bill_number' => $bill->bill_number,
                        'service_name' => $item->service_name,
                        'description' => $item->description ?? '',
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'total_price' => $item->total_price,
                        'date' => $item->created_at, // Use item creation date instead of bill date
                        'category' => optional($item->patientCharge)->category ?? 'General',
                        'bill_status' => $bill->status,
                        'item_created_at' => $item->created_at,
                        'charge_date' => optional($item->created_at)->format('Y-m-d'),
                        'is_auto_daily' => $isAutoDaily,
                    ];
                }
            }

            // Add room charge as first item if room has a price
            if ($admission && $admission->room && $admission->room->price) {
                // Calculate number of days stayed
                // If patient is discharged, use discharge date; otherwise use today
                $endDate = $admission->discharge_date ? Carbon::parse($admission->discharge_date) : Carbon::now();
                $startDate = Carbon::parse($admission->admission_date);
                
                // Normalize to start of day to count calendar days only (not hours/minutes)
                $endDate = $endDate->startOfDay();
                $startDate = $startDate->startOfDay();
                
                // Calculate days - minimum 1 day even for same-day admission
                $daysStayed = max(1, $startDate->diffInDays($endDate) + 1);
                
                // Calculate total room charge
                $roomPrice = floatval($admission->room->price);
                $totalRoomCharge = $roomPrice * $daysStayed;
                
                // Build description with date range
                $dateRange = $startDate->format('M d, Y');
                if ($daysStayed > 1) {
                    $dateRange .= ' - ' . $endDate->format('M d, Y');
                }
                $description = "Daily room accommodation ({$dateRange})";
                if (!$admission->discharge_date) {
                    $description .= " - Ongoing";
                }
                
                array_unshift($allItems, [
                    'bill_id' => null,
                    'bill_number' => 'AUTO',
                    'service_name' => 'Room: ' . $admission->room->name,
                    'description' => $description,
                    'quantity' => $daysStayed,
                    'unit_price' => $roomPrice,
                    'total_price' => $totalRoomCharge,
                    'date' => $admission->admission_date,
                    'category' => 'Room Accommodation',
                    'bill_status' => $admission->discharge_date ? 'finalized' : 'ongoing',
                    'item_created_at' => $admission->admission_date,
                    'charge_date' => $admission->admission_date->format('Y-m-d'),
                    'is_auto_daily' => false,
                ]);
            }

            // Only include NON-auto charges in SOA to reflect Patient Charges entries
            $itemizedCharges = collect($allItems)
                ->where('is_auto_daily', false)
                ->sortBy('item_created_at')
                ->map(function ($item) {
                    // Format the date consistently
                    $item['date'] = $item['charge_date'];
                    return $item;
                })
                ->values()
                ->all();

            // Get payments from ALL bills (consolidated payment history)
            $paymentHistory = [];
            foreach ($allBills as $bill) {
                foreach ($bill->payments as $payment) {
                    $paymentHistory[] = [
                        'id' => $payment->id,
                        'bill_id' => $bill->id,
                        'bill_number' => $bill->bill_number,
                        'payment_number' => $payment->payment_number,
                        'payment_date' => $payment->payment_date,
                        'amount' => $payment->amount,
                        'payment_method' => $payment->payment_method,
                        'payment_method_label' => $payment->payment_method_label,
                        'reference_number' => $payment->reference_number,
                        'notes' => $payment->notes,
                        'received_by' => $payment->receiver ? $payment->receiver->name : null,
                        'bill_status' => $bill->status
                    ];
                }
            }

            // Compute totals based on NON-auto items only (for SOA display)
            $totalChargesManual = collect($itemizedCharges)->sum('total_price');
            
            // Sum payments only for bills that contain at least one NON-auto item
            $billIdsWithManualItems = collect($allItems)
                ->where('is_auto_daily', false)
                ->pluck('bill_id')
                ->unique();
            
            $totalPaymentsManual = $allBills
                ->whereIn('id', $billIdsWithManualItems->all())
                ->sum(function ($bill) {
                    return $bill->payments->sum('amount');
                });
            
            $outstandingBalanceManual = max(0, $totalChargesManual - $totalPaymentsManual);
            
            // Also compute totals including ALL items (for accurate payment processing)
            $totalChargesAll = collect($allItems)->where('is_auto_daily', false)->sum('total_price');
            $totalPaymentsAll = $allBills->sum(function ($bill) {
                return $bill->payments->sum('amount');
            });
            $outstandingBalanceAll = max(0, $totalChargesAll - $totalPaymentsAll);
            
            // Determine SOA status based on manual charges only
            $soaStatus = collect($itemizedCharges)->isEmpty() ? 'No Charges' : ($outstandingBalanceManual <= 0 ? 'Paid in Full' : 'Balance Remaining');

            // Separate active and historical bills
            $activeBills = $allBills->whereIn('status', ['draft', 'sent', 'partially_paid', 'overdue']);
            $historicalBills = $allBills->where('status', 'paid');
            $totalHistoricalCharges = $historicalBills->sum('total_amount');
            $totalHistoricalPayments = $historicalBills->sum('paid_amount');

            // Sort payment history by date (newest first)
            usort($paymentHistory, function($a, $b) {
                return strtotime($b['payment_date']) - strtotime($a['payment_date']);
            });

            // Generate SOA number based on active bill
            $soaNumber = 'SOA-' . $patientId . '-' . date('Ymd') . '-' . str_pad($allBills->count(), 3, '0', STR_PAD_LEFT);

            return response()->json([
                'success' => true,
                'data' => [
                    'soa_number' => $soaNumber,
                    'soa_date' => now()->toDateString(),
                    'soa_status' => $soaStatus,
                    'patient' => [
                        'id' => $patient->id,
                        'first_name' => $patient->first_name,
                        'middle_name' => $patient->middle_name,
                        'last_name' => $patient->last_name,
                        'full_name' => $patient->full_name,
                        'date_of_birth' => $patient->date_of_birth,
                        'age' => $patient->age,
                        'contact_number' => $patient->contact_number,
                        'address' => $patient->address,
                        'philhealth_number' => $patient->philhealth_number,
                        'philhealth_category' => $patient->philhealth_category
                    ],
                    'admission' => $admission ? [
                        'id' => $admission->id,
                        'admission_date' => $admission->admission_date,
                        'admission_time' => $admission->admission_time,
                        'admission_type' => $admission->admission_type,
                        'room_number' => $admission->room->name ?? 'N/A',
                        'bed_number' => $admission->bed->bed_number ?? 'N/A',
                        'attending_physician' => $admission->attending_physician,
                        'status' => $admission->status
                    ] : null,
                    'current_bill' => $activeBill ? [
                        'id' => $activeBill->id,
                        'bill_number' => $activeBill->bill_number,
                        'bill_date' => $activeBill->bill_date,
                        'due_date' => $activeBill->due_date,
                        'status' => $activeBill->status,
                        'subtotal' => $activeBill->subtotal,
                        'tax_amount' => $activeBill->tax_amount,
                        'discount_amount' => $activeBill->discount_amount,
                        'total_amount' => $activeBill->total_amount,
                        'paid_amount' => $activeBill->paid_amount,
                        'balance_amount' => $activeBill->balance_amount,
                        'notes' => $activeBill->notes
                    ] : null,
                    'all_bills' => $allBills->map(function($bill) {
                        return [
                            'id' => $bill->id,
                            'bill_number' => $bill->bill_number,
                            'bill_date' => $bill->bill_date,
                            'due_date' => $bill->due_date,
                            'status' => $bill->status,
                            'total_amount' => $bill->total_amount,
                            'paid_amount' => $bill->paid_amount,
                            'balance_amount' => $bill->balance_amount,
                            'items_count' => $bill->items->count(),
                            'payments_count' => $bill->payments->count()
                        ];
                    }),
                    'historical_bills' => $historicalBills->map(function($bill) {
                        return [
                            'id' => $bill->id,
                            'bill_number' => $bill->bill_number,
                            'bill_date' => $bill->bill_date,
                            'due_date' => $bill->due_date,
                            'status' => $bill->status,
                            'subtotal' => $bill->subtotal,
                            'tax_amount' => $bill->tax_amount,
                            'discount_amount' => $bill->discount_amount,
                            'total_amount' => $bill->total_amount,
                            'paid_amount' => $bill->paid_amount,
                            'balance_amount' => $bill->balance_amount,
                            'notes' => $bill->notes
                        ];
                    }),
                    'itemized_charges' => $itemizedCharges,
                    'payment_history' => $paymentHistory,
                    'totals' => [
                        'current_charges' => $totalChargesAll, // All charges including room (for accurate billing)
                        'current_payments' => $totalPaymentsAll, // All payments
                        'outstanding_balance' => $outstandingBalanceAll, // Outstanding balance for all charges
                        'historical_charges' => $totalHistoricalCharges, // From paid bills only
                        'historical_payments' => $totalHistoricalPayments, // From paid bills only
                        'active_charges' => $activeBills->sum('total_amount'), // From active bills only
                        'active_payments' => $activeBills->sum('paid_amount'), // From active bills only
                        'active_balance' => $activeBills->sum('balance_amount'), // From active bills only
                        'active_bills_count' => $activeBills->count(),
                        'historical_bills_count' => $historicalBills->count(),
                        'total_bills_count' => $allBills->count(),
                        'total_items_count' => collect($itemizedCharges)->count(), // Only manual items for display
                        'total_payments_count' => $allBills->sum(function($bill) { return $bill->payments->count(); }),
                        // Add display-specific totals for SOA
                        'display_charges' => $totalChargesManual, // Manual charges only for SOA display
                        'display_payments' => $totalPaymentsManual, // Payments for manual charges
                        'display_balance' => $outstandingBalanceManual // Balance for manual charges (SOA display)
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to fetch SOA data: ' . $e->getMessage(), [
                'birthcare_id' => $birthcare_id,
                'patient_id' => $request->patient_id ?? null,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch Statement of Account data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process partial payment for a patient bill.
     */
    public function processPartialPayment(Request $request, $birthcare_id, $bill_id)
    {
        try {
            $validated = $request->validate([
                'amount' => 'required|numeric|min:0.01',
                'payment_date' => 'required|date',
                'payment_method' => 'required|in:cash,credit_card',
                'reference_number' => 'nullable|string|max:255',
                'notes' => 'nullable|string',
                'apply_to_oldest_first' => 'boolean'
            ]);

            DB::beginTransaction();

            $bill = PatientBill::forBirthcare($birthcare_id)->findOrFail($bill_id);
            
            if ($validated['amount'] > $bill->balance_amount) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment amount exceeds outstanding balance'
                ], 422);
            }

            $payment = BillPayment::create([
                'patient_bill_id' => $bill->id,
                'payment_number' => BillPayment::generatePaymentNumber(),
                'payment_date' => $validated['payment_date'],
                'amount' => $validated['amount'],
                'payment_method' => $validated['payment_method'],
                'reference_number' => $validated['reference_number'],
                'notes' => $validated['notes'],
                'received_by' => Auth::id(),
            ]);

            // Update bill payment status
            $bill->updatePaymentStatus();

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => [
                    'payment' => $payment,
                    'bill' => $bill->fresh(['payments']),
                    'remaining_balance' => $bill->balance_amount
                ],
                'message' => 'Partial payment processed successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to process partial payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Generate payment reminder for overdue bills.
     */
    public function generatePaymentReminders($birthcare_id)
    {
        try {
            $overdueBills = PatientBill::with(['patient'])
                ->forBirthcare($birthcare_id)
                ->where('balance_amount', '>', 0)
                ->where('due_date', '<', now())
                ->get();

            $reminders = $overdueBills->map(function ($bill) {
                $daysOverdue = now()->diffInDays($bill->due_date);
                
                return [
                    'bill_id' => $bill->id,
                    'bill_number' => $bill->bill_number,
                    'patient_name' => $bill->patient->first_name . ' ' . $bill->patient->last_name,
                    'patient_phone' => $bill->patient->contact_number ?? 'No phone provided',
                    'balance_amount' => $bill->balance_amount,
                    'due_date' => $bill->due_date->format('Y-m-d'),
                    'days_overdue' => $daysOverdue,
                    'urgency_level' => $this->getUrgencyLevel($daysOverdue),
                    'suggested_message' => $this->generateReminderMessage($bill, $daysOverdue)
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'reminders' => $reminders,
                    'total_overdue' => $reminders->count(),
                    'total_overdue_amount' => $overdueBills->sum('balance_amount')
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate payment reminders',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk payment processing for multiple bills.
     */
    public function processBulkPayments(Request $request, $birthcare_id)
    {
        try {
            $validated = $request->validate([
                'payments' => 'required|array|min:1',
                'payments.*.bill_id' => 'required|exists:patient_bills,id',
                'payments.*.amount' => 'required|numeric|min:0.01',
                'payments.*.payment_method' => 'required|in:cash,credit_card',
                'payments.*.payment_date' => 'required|date',
                'payments.*.reference_number' => 'nullable|string|max:255',
                'payments.*.notes' => 'nullable|string'
            ]);

            DB::beginTransaction();

            $results = [];
            $errors = [];
            $totalAmount = 0;

            foreach ($validated['payments'] as $paymentData) {
                try {
                    $bill = PatientBill::forBirthcare($birthcare_id)->findOrFail($paymentData['bill_id']);
                    
                    if ($paymentData['amount'] > $bill->balance_amount) {
                        $errors[] = [
                            'bill_id' => $paymentData['bill_id'],
                            'error' => 'Payment amount exceeds outstanding balance'
                        ];
                        continue;
                    }

                    $payment = BillPayment::create([
                        'patient_bill_id' => $bill->id,
                        'payment_number' => BillPayment::generatePaymentNumber(),
                        'payment_date' => $paymentData['payment_date'],
                        'amount' => $paymentData['amount'],
                        'payment_method' => $paymentData['payment_method'],
                        'reference_number' => $paymentData['reference_number'] ?? null,
                        'notes' => $paymentData['notes'] ?? null,
                        'received_by' => Auth::id(),
                    ]);

                    $bill->updatePaymentStatus();
                    $totalAmount += $paymentData['amount'];

                    $results[] = [
                        'bill_id' => $bill->id,
                        'payment_id' => $payment->id,
                        'amount' => $paymentData['amount'],
                        'remaining_balance' => $bill->fresh()->balance_amount,
                        'status' => 'success'
                    ];

                } catch (\Exception $e) {
                    $errors[] = [
                        'bill_id' => $paymentData['bill_id'] ?? 'unknown',
                        'error' => $e->getMessage()
                    ];
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => [
                    'successful_payments' => $results,
                    'failed_payments' => $errors,
                    'total_processed' => count($validated['payments']),
                    'successful_count' => count($results),
                    'failed_count' => count($errors),
                    'total_amount_processed' => $totalAmount
                ],
                'message' => 'Bulk payments processed'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to process bulk payments',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payment analytics and insights.
     */
    public function getPaymentAnalytics($birthcare_id)
    {
        try {
            // Base query for all bills for this birthcare
            $baseQuery = PatientBill::forBirthcare($birthcare_id);

            // Payment method breakdown
            $paymentMethods = BillPayment::whereIn('patient_bill_id', (clone $baseQuery)->pluck('id'))
                ->selectRaw('payment_method, COUNT(*) as count, SUM(amount) as total_amount')
                ->groupBy('payment_method')
                ->get();

            // Monthly revenue trend (last 12 months) - DB driver aware
            $dbDriver = config('database.default');

            $monthlyRevenueQuery = (clone $baseQuery)
                ->where('status', 'paid')
                ->where('bill_date', '>=', now()->subYear());

            if ($dbDriver === 'sqlite') {
                $monthlyRevenue = $monthlyRevenueQuery
                    ->selectRaw("strftime('%Y-%m', bill_date) as month, SUM(total_amount) as revenue")
                    ->groupBy('month')
                    ->orderBy('month')
                    ->get();
            } elseif ($dbDriver === 'pgsql') {
                $monthlyRevenue = $monthlyRevenueQuery
                    ->selectRaw("to_char(bill_date, 'YYYY-MM') as month, SUM(total_amount) as revenue")
                    ->groupBy('month')
                    ->orderBy('month')
                    ->get();
            } else {
                // MySQL / MariaDB
                $monthlyRevenue = $monthlyRevenueQuery
                    ->selectRaw("DATE_FORMAT(bill_date, '%Y-%m') as month, SUM(total_amount) as revenue")
                    ->groupBy('month')
                    ->orderBy('month')
                    ->get();
            }

            // Average payment time (days from bill_date to first payment)
            $avgPaymentTime = (clone $baseQuery)
                ->where('status', 'paid')
                ->with('payments')
                ->get()
                ->map(function ($bill) {
                    $firstPayment = $bill->payments->min('payment_date');
                    if ($firstPayment) {
                        return $bill->bill_date->diffInDays($firstPayment);
                    }
                    return null;
                })
                ->filter()
                ->avg();

            // Outstanding amounts by age buckets
            $outstandingByAge = (clone $baseQuery)
                ->where('balance_amount', '>', 0)
                ->get()
                ->groupBy(function ($bill) {
                    $daysOverdue = now()->diffInDays($bill->due_date);
                    if ($daysOverdue <= 0) return 'current';
                    if ($daysOverdue <= 30) return '1-30_days';
                    if ($daysOverdue <= 60) return '31-60_days';
                    if ($daysOverdue <= 90) return '61-90_days';
                    return 'over_90_days';
                })
                ->map(function ($group) {
                    return [
                        'count' => $group->count(),
                        'total_amount' => $group->sum('balance_amount'),
                    ];
                });

            // Summary using the unfiltered baseQuery
            $summaryQuery = clone $baseQuery;

            $summary = [
                'total_bills' => (clone $summaryQuery)->count(),
                'paid_bills' => (clone $summaryQuery)->where('status', 'paid')->count(),
                'overdue_bills' => (clone $summaryQuery)->where('due_date', '<', now())->where('balance_amount', '>', 0)->count(),
                'total_revenue' => (clone $summaryQuery)->sum('total_amount'),
                'collected_amount' => (clone $summaryQuery)->sum('paid_amount'),
                'outstanding_amount' => (clone $summaryQuery)->sum('balance_amount'),
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'payment_methods' => $paymentMethods,
                    'monthly_revenue' => $monthlyRevenue,
                    'average_payment_time_days' => $avgPaymentTime ? round($avgPaymentTime, 1) : 0,
                    'outstanding_by_age' => $outstandingByAge,
                    'summary' => $summary,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate payment analytics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Helper function to determine urgency level based on days overdue.
     */
    private function getUrgencyLevel($daysOverdue)
    {
        if ($daysOverdue <= 7) return 'low';
        if ($daysOverdue <= 30) return 'medium';
        if ($daysOverdue <= 60) return 'high';
        return 'critical';
    }

    /**
     * Helper function to generate reminder message.
     */
    private function generateReminderMessage($bill, $daysOverdue)
    {
        $patientName = $bill->patient->first_name . ' ' . $bill->patient->last_name;
        $amount = number_format($bill->balance_amount, 2);
        
        if ($daysOverdue <= 7) {
            return "Dear {$patientName}, this is a friendly reminder that your payment of ₱{$amount} (Bill #{$bill->bill_number}) is now overdue. Please settle at your earliest convenience.";
        } elseif ($daysOverdue <= 30) {
            return "Dear {$patientName}, your payment of ₱{$amount} (Bill #{$bill->bill_number}) is {$daysOverdue} days overdue. Please contact us to arrange payment or discuss payment options.";
        } else {
            return "Dear {$patientName}, your payment of ₱{$amount} (Bill #{$bill->bill_number}) is significantly overdue ({$daysOverdue} days). Please contact our billing department immediately to resolve this matter.";
        }
    }

    /**
     * Get payments summary (focused on admitted patients).
     */
    private function getPaymentsSummary($birthcare_id)
    {
        try {
            // Get ALL unique admitted patients (not just in-labor/delivered)
            $admissionIds = PatientAdmission::where('birth_care_id', $birthcare_id)
                ->whereIn('status', ['admitted', 'in-labor', 'delivered', 'active'])
                ->groupBy('patient_id')
                ->selectRaw('MAX(id) as id')
                ->pluck('id');

            $admissions = PatientAdmission::whereIn('id', $admissionIds)->get();
            $patientIds = $admissions->pluck('patient_id');

            // Base query for ALL bills of this birthcare (not filtered by patient status)
            // This ensures we count all revenue, regardless of patient admission status
            $baseQuery = PatientBill::forBirthcare($birthcare_id);
            
            // Note: We don't filter by patient_ids to include all bills for accurate revenue tracking
            // The old logic excluded bills from patients not in 'in-labor' or 'delivered' status
            
            // Debug logging
            $allBills = (clone $baseQuery)->get();
            \Log::info('Payment Summary Debug', [
                'birthcare_id' => $birthcare_id,
                'total_bills_count' => $allBills->count(),
                'bills_detail' => $allBills->map(function($bill) {
                    return [
                        'bill_number' => $bill->bill_number,
                        'patient_id' => $bill->patient_id,
                        'total_amount' => $bill->total_amount,
                        'status' => $bill->status
                    ];
                }),
                'sum_total_amount' => $allBills->sum('total_amount')
            ]);

            $paymentsCount = BillPayment::whereHas('bill', function ($query) use ($birthcare_id) {
                    $query->forBirthcare($birthcare_id);
                })->count();

            return [
                'total_bills' => (clone $baseQuery)->count(),
                'total_payments' => $paymentsCount,
                'total_revenue' => (clone $baseQuery)->sum('total_amount') ?? 0,
                'total_paid' => (clone $baseQuery)->sum('paid_amount') ?? 0,
                'total_outstanding' => (clone $baseQuery)->where('balance_amount', '>', 0)->sum('balance_amount') ?? 0,
                'overdue_amount' => (clone $baseQuery)->where('due_date', '<', now())->where('balance_amount', '>', 0)->sum('balance_amount') ?? 0,
                'admitted_patients_count' => $admissions->count(),
                'bills_by_status' => (clone $baseQuery)->selectRaw('status, COUNT(*) as count')
                    ->groupBy('status')
                    ->pluck('count', 'status')
            ];
        } catch (\Exception $e) {
            \Log::error('Failed to calculate payments summary: ' . $e->getMessage(), [
                'birthcare_id' => $birthcare_id,
                'trace' => $e->getTraceAsString()
            ]);
            
            // Return default values on error
            return [
                'total_bills' => 0,
                'total_revenue' => 0,
                'total_paid' => 0,
                'total_outstanding' => 0,
                'overdue_amount' => 0,
                'admitted_patients_count' => 0,
                'bills_by_status' => []
            ];
        }
    }

    /**
     * Get payment reports with daily collections and other analytics.
     */
    public function getReports(Request $request, $birthcare_id)
    {
        try {
            $startDate = $request->get('start_date', now()->subMonth()->toDateString());
            $endDate = $request->get('end_date', now()->toDateString());
            $period = $request->get('period', 'daily');
            
            // Get facility information to determine approval date
            $facility = \App\Models\BirthCare::find($birthcare_id);
            
            // Determine the start date for daily collections
            $facilityStartDate = null;
            if ($facility) {
                if ($facility->status === 'approved') {
                    // Use updated_at as approval date (when status was changed to approved)
                    $facilityStartDate = $facility->updated_at->toDateString();
                } else {
                    // If not approved, use created_at
                    $facilityStartDate = $facility->created_at->toDateString();
                }
            }
            
            // Default to 30 days ago if we can't determine facility start date
            $collectionsStartDate = $facilityStartDate ?? now()->subDays(29)->toDateString();
            $collectionsEndDate = now()->toDateString();
            
            // Get daily collections from facility approval date to today
            $dailyCollections = BillPayment::whereHas('bill', function($query) use ($birthcare_id) {
                    $query->forBirthcare($birthcare_id);
                })
                ->whereBetween('payment_date', [$collectionsStartDate, $collectionsEndDate])
                ->selectRaw('payment_date as date, SUM(amount) as amount')
                ->groupBy('payment_date')
                ->orderBy('payment_date')
                ->get()
                ->keyBy('date');
            
            // Calculate days between facility start and today
            $daysDiff = \Carbon\Carbon::parse($collectionsStartDate)->diffInDays(now()) + 1;
            
            // Fill in missing dates with zero amounts from facility start date to today
            $dailyCollectionsArray = [];
            for ($i = $daysDiff - 1; $i >= 0; $i--) {
                $date = now()->subDays($i)->toDateString();
                $dailyCollectionsArray[] = [
                    'date' => $date,
                    'amount' => $dailyCollections->get($date)->amount ?? 0
                ];
            }
            
            // Payment methods breakdown
            $paymentMethods = BillPayment::whereHas('bill', function($query) use ($birthcare_id) {
                    $query->forBirthcare($birthcare_id);
                })
                ->whereBetween('payment_date', [$startDate, $endDate])
                ->selectRaw('payment_method, SUM(amount) as total_amount')
                ->groupBy('payment_method')
                ->pluck('total_amount', 'payment_method');
            
            // Top services by revenue
            $topServices = BillItem::whereHas('bill', function($query) use ($birthcare_id) {
                    $query->forBirthcare($birthcare_id);
                })
                ->selectRaw('service_name, SUM(total_price) as total_revenue, SUM(quantity) as count')
                ->groupBy('service_name')
                ->orderByDesc('total_revenue')
                ->limit(5)
                ->get()
                ->map(function($item) {
                    return [
                        'service_name' => $item->service_name,
                        'total_revenue' => $item->total_revenue,
                        'count' => $item->count
                    ];
                });
            
            return response()->json([
                'success' => true,
                'data' => [
                    'daily_collections' => $dailyCollectionsArray,
                    'payment_methods' => $paymentMethods,
                    'top_services' => $topServices,
                    'facility_info' => [
                        'start_date' => $collectionsStartDate,
                        'status' => $facility ? $facility->status : null,
                        'approval_date' => $facilityStartDate,
                        'days_since_start' => $daysDiff,
                        'name' => $facility ? $facility->name : null
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to fetch payment reports: ' . $e->getMessage(), [
                'birthcare_id' => $birthcare_id,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch payment reports',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get payment analytics data.
     */
    public function getAnalytics(Request $request, $birthcare_id)
    {
        try {
            $period = $request->get('period', 'month');
            
            // Revenue trends
            $currentMonthRevenue = BillPayment::whereHas('bill', function($query) use ($birthcare_id) {
                    $query->forBirthcare($birthcare_id);
                })
                ->whereMonth('payment_date', now()->month)
                ->whereYear('payment_date', now()->year)
                ->sum('amount');
                
            $lastMonthRevenue = BillPayment::whereHas('bill', function($query) use ($birthcare_id) {
                    $query->forBirthcare($birthcare_id);
                })
                ->whereMonth('payment_date', now()->subMonth()->month)
                ->whereYear('payment_date', now()->subMonth()->year)
                ->sum('amount');
            
            $growthRate = 0;
            $trend = 'stable';
            
            if ($lastMonthRevenue > 0) {
                $growthRate = (($currentMonthRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100;
                $trend = $growthRate > 0 ? 'up' : ($growthRate < 0 ? 'down' : 'stable');
            }
            
            // Patient demographics (age groups)
            $ageGroups = Patient::whereHas('bills', function($query) use ($birthcare_id) {
                    $query->forBirthcare($birthcare_id);
                })
                ->selectRaw('CASE 
                    WHEN age BETWEEN 18 AND 25 THEN "18-25"
                    WHEN age BETWEEN 26 AND 35 THEN "26-35" 
                    WHEN age BETWEEN 36 AND 45 THEN "36-45"
                    ELSE "45+"
                    END as age_group, COUNT(*) as count')
                ->groupBy('age_group')
                ->pluck('count', 'age_group');
            
            return response()->json([
                'success' => true,
                'data' => [
                    'revenue_trends' => [
                        'growth_rate' => round($growthRate, 1),
                        'comparison_period' => 'last_month',
                        'trend' => $trend
                    ],
                    'patient_demographics' => [
                        'age_groups' => $ageGroups
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to fetch payment analytics: ' . $e->getMessage(), [
                'birthcare_id' => $birthcare_id,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch payment analytics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate PDF for Statement of Account.
     */
    public function generateSOAPDF(Request $request, $birthcare_id)
    {
        try {
            // Validate required parameters
            if (!$request->has('patient_id')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Patient ID is required'
                ], 400);
            }
            
            // Handle token authentication from query parameter if present
            if ($request->has('token') && !auth()->check()) {
                $token = $request->get('token');
                $user = \Laravel\Sanctum\PersonalAccessToken::findToken($token)?->tokenable;
                if ($user) {
                    auth()->login($user);
                }
            }
            
            // Get SOA data using the existing method
            $soaResponse = $this->getStatementOfAccount($request, $birthcare_id);
            
            if (!$soaResponse->getData()->success) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch SOA data for PDF generation'
                ], 500);
            }

            $soaData = $soaResponse->getData()->data;
            
            // Extract patient and admission data from SOA response
            $patientData = (object) $soaData->patient;
            $admissionData = $soaData->admission ? (object) $soaData->admission : null;
            
            // Fetch facility data
            $facility = \App\Models\BirthCare::with('owner')->find($birthcare_id);
            if (!$facility) {
                return response()->json([
                    'success' => false,
                    'message' => 'Facility not found'
                ], 404);
            }
            
            // Debug: Log the actual facility data from database
            \Log::info('PDF Generation - Raw Facility Data:', [
                'facility_id' => $facility->id,
                'facility_name' => $facility->name,
                'facility_description' => $facility->description,
                'owner_exists' => $facility->owner ? 'YES' : 'NO',
                'owner_address' => $facility->owner->address ?? 'NULL',
                'owner_contact' => $facility->owner->contact_number ?? 'NULL'
            ]);
            
            $facilityData = (object) [
                'name' => strtoupper($facility->name),
                'address' => $facility->owner->address ?? 'N/A',
                'contact_number' => $facility->owner->contact_number ?? 'N/A',
                'description' => $facility->description ?? ''
            ];
            
            // Debug: Log the processed facility data being passed to PDF
            \Log::info('PDF Generation - Processed Facility Data:', [
                'name' => $facilityData->name,
                'address' => $facilityData->address,
                'contact_number' => $facilityData->contact_number,
                'description' => $facilityData->description
            ]);
            
            // Generate PDF using our custom PDFService
            $pdf = PDFService::generateStatementOfAccount($soaData, $patientData, $admissionData, $facilityData);

            // Generate filename
            $patientName = str_replace(' ', '_', trim($patientData->first_name . ' ' . $patientData->last_name));
            $date = now()->format('Y-m-d');
            $filename = "SOA_{$patientName}_{$date}.pdf";

            // Return PDF as download
            return response($pdf->output(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0'
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to generate SOA PDF: ' . $e->getMessage(), [
                'birthcare_id' => $birthcare_id,
                'patient_id' => $request->patient_id ?? null,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate SOA PDF: ' . $e->getMessage(),
                'error' => $e->getMessage(),
                'debug' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]
            ], 500);
        }
    }
}
