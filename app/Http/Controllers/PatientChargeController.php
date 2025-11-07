<?php

namespace App\Http\Controllers;

use App\Models\PatientAdmission;
use App\Models\PatientCharge;
use App\Models\PatientBill;
use App\Models\BillItem;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PatientChargeController extends Controller
{
    /**
     * Get admitted patients for the birthcare facility.
     */
    public function getAdmittedPatients(Request $request, $birthcare_id): JsonResponse
    {
        try {
            \Log::info("Fetching admitted patients for birth_care_id: {$birthcare_id}");
            
            // First, let's see what admissions exist for this birthcare
            $allAdmissions = PatientAdmission::where('birth_care_id', $birthcare_id)->get();
            \Log::info("Total admissions found for birthcare {$birthcare_id}: " . $allAdmissions->count());
            \Log::info("Admission statuses: " . $allAdmissions->pluck('status')->unique()->join(', '));
            
            // Get unique patients with their latest admission that is currently active
            $admissionIds = PatientAdmission::where('birth_care_id', $birthcare_id)
                ->whereIn('status', ['admitted', 'active', 'in-labor', 'delivered'])
                ->groupBy('patient_id')
                ->selectRaw('MAX(id) as id')
                ->pluck('id');
                
            \Log::info("Found admission IDs for admitted/active patients: " . $admissionIds->join(', '));

            $query = PatientAdmission::with(['patient', 'room'])
                ->whereIn('id', $admissionIds);

            // Apply search filter if provided
            if ($request->filled('search')) {
                $search = $request->get('search');
                $query->where(function ($q) use ($search) {
                    $q->whereHas('patient', function ($patientQuery) use ($search) {
                        $patientQuery->where('first_name', 'like', "%{$search}%")
                                   ->orWhere('last_name', 'like', "%{$search}%")
                                   ->orWhere('middle_name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('room', function ($roomQuery) use ($search) {
                        $roomQuery->where('name', 'like', "%{$search}%");
                    });
                });
            }

            $admittedPatients = $query->orderBy('admission_date', 'desc')
                                    ->get()
                                    ->map(function ($admission) {
                                        return [
                                            'id' => $admission->id,
                                            'patient_id' => $admission->patient_id,
                                            'firstname' => $admission->patient->first_name,
                                            'lastname' => $admission->patient->last_name,
                                            'middlename' => $admission->patient->middle_name,
                                            'age' => $admission->patient->age,
                                            'admission_date' => $admission->admission_date->format('Y-m-d'),
                                            'room_number' => $admission->room->name ?? 'N/A',
                                            'room_price' => $admission->room->price ?? null,
                                            'room_type' => 'Room', // Default value since room_type doesn't exist in schema
                                            'status' => ucfirst($admission->status),
                                            'admission_id' => $admission->id
                                        ];
                                    });

            return response()->json([
                'success' => true,
                'data' => $admittedPatients
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch admitted patients',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available medical services for charging.
     */
    public function getMedicalServices(Request $request, $birthcare_id): JsonResponse
    {
        try {
            $query = PatientCharge::where('birthcare_id', $birthcare_id);

            // Filter by active services if requested
            if ($request->get('active_only', true)) {
                $query->where('is_active', true);
            }

            // Apply search filter if provided
            if ($request->filled('search')) {
                $search = $request->get('search');
                $query->where(function ($q) use ($search) {
                    $q->where('service_name', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%")
                      ->orWhere('category', 'like', "%{$search}%");
                });
            }

            $services = $query->orderBy('service_name')
                            ->get();

            return response()->json([
                'success' => true,
                'data' => $services
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch medical services',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Charge services to a patient.
     */
    public function chargePatient(Request $request, $birthcare_id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'patient_id' => 'required|exists:patients,id',
            'admission_id' => 'required|exists:patient_admissions,id',
            'services' => 'required|array', // Allow empty array for room-only charges
            'services.*.id' => 'required_with:services.*|exists:patient_charges,id',
            'services.*.quantity' => 'required_with:services.*|integer|min:1',
            'services.*.price' => 'required_with:services.*|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Check if active bill exists for this patient (unpaid/partially paid)
            $patientBill = PatientBill::where('patient_id', $request->patient_id)
                                    ->where('birthcare_id', $birthcare_id)
                                    ->whereIn('status', ['draft', 'sent', 'partially_paid', 'overdue'])
                                    ->where('balance_amount', '>', 0)
                                    ->first();

            // Create new patient bill if none exists
            if (!$patientBill) {
                $patientBill = PatientBill::create([
                    'patient_id' => $request->patient_id,
                    'birthcare_id' => $birthcare_id,
                    'bill_number' => PatientBill::generateBillNumber(),
                    'bill_date' => now(),
                    'due_date' => now()->addDays(30), // 30 days due date
                    'total_amount' => 0,
                    'paid_amount' => 0,
                    'balance_amount' => 0,
                    'status' => 'draft',
                    'created_by' => auth()->id() ?? 1, // fallback to user ID 1 if not authenticated
                ]);
            }

            $totalAmount = 0;
            $addedItems = [];

            // Get patient admission data for calculating room accommodation days
            $patientAdmission = PatientAdmission::where('patient_id', $request->patient_id)
                                              ->where('birth_care_id', $birthcare_id)
                                              ->whereIn('status', ['admitted', 'active', 'in-labor', 'delivered'])
                                              ->orderBy('admission_date', 'desc')
                                              ->first();

            // Add each service as a bill item (if any services provided)
            foreach ($request->services as $service) {
                $serviceData = PatientCharge::find($service['id']);
                
                if (!$serviceData) {
                    continue; // Skip if service not found
                }

                $quantity = $service['quantity'];
                
                // Auto-calculate quantity for room accommodation services based on admission days
                if ($patientAdmission && 
                    $serviceData->category && 
                    (stripos($serviceData->category, 'room') !== false || 
                     stripos($serviceData->category, 'accommodation') !== false)) {
                    $quantity = $patientAdmission->admission_days;
                    
                    // Ensure we're using the correct room type charge
                    $correctRoomCharge = $this->determineCorrectRoomCharge($patientAdmission, $serviceData);
                    if ($correctRoomCharge && $correctRoomCharge->id !== $serviceData->id) {
                        // Update to use the correct room charge
                        $serviceData = $correctRoomCharge;
                        $service['price'] = $correctRoomCharge->price;
                    }
                }

                $itemTotal = $quantity * $service['price'];
                $totalAmount += $itemTotal;

                $billItem = BillItem::create([
                    'patient_bill_id' => $patientBill->id,
                    'patient_charge_id' => $serviceData->id,
                    'service_name' => $serviceData->service_name,
                    'description' => $serviceData->description,
                    'quantity' => $quantity,
                    'unit_price' => $service['price'],
                    'total_price' => $itemTotal,
                ]);

                $addedItems[] = [
                    'service_name' => $serviceData->service_name,
                    'quantity' => $service['quantity'],
                    'unit_price' => $service['price'],
                    'total_price' => $itemTotal,
                ];
            }
            
            // AUTOMATICALLY ADD ROOM ACCOMMODATION CHARGES
            // This ensures room charges are always included in the bill
            if ($patientAdmission && $patientAdmission->room && $patientAdmission->room->price) {
                // Calculate room accommodation charges
                $endDate = $patientAdmission->discharge_date 
                    ? \Carbon\Carbon::parse($patientAdmission->discharge_date) 
                    : \Carbon\Carbon::now();
                $startDate = \Carbon\Carbon::parse($patientAdmission->admission_date);
                
                // Normalize to start of day
                $endDate = $endDate->startOfDay();
                $startDate = $startDate->startOfDay();
                
                // Calculate days - minimum 1 day
                $daysStayed = max(1, $startDate->diffInDays($endDate) + 1);
                
                $roomPrice = floatval($patientAdmission->room->price);
                $roomTotal = $roomPrice * $daysStayed;
                
                // Build description
                $dateRange = $startDate->format('M d, Y');
                if ($daysStayed > 1) {
                    $dateRange .= ' - ' . $endDate->format('M d, Y');
                }
                $roomDescription = "Daily room accommodation ({$dateRange})";
                if (!$patientAdmission->discharge_date) {
                    $roomDescription .= " - Ongoing";
                }
                
                // Check if room charge already exists in this bill to avoid duplicates
                $existingRoomCharge = $patientBill->items()
                    ->where('service_name', 'LIKE', 'Room:%')
                    ->where('description', 'LIKE', '%Daily room accommodation%')
                    ->first();
                
                if (!$existingRoomCharge) {
                    // Add room charge as a bill item
                    BillItem::create([
                        'patient_bill_id' => $patientBill->id,
                        'patient_charge_id' => null, // No specific charge ID for auto room charges
                        'service_name' => 'Room: ' . $patientAdmission->room->name,
                        'description' => $roomDescription,
                        'quantity' => $daysStayed,
                        'unit_price' => $roomPrice,
                        'total_price' => $roomTotal,
                    ]);
                    
                    $totalAmount += $roomTotal;
                    
                    $addedItems[] = [
                        'service_name' => 'Room: ' . $patientAdmission->room->name,
                        'quantity' => $daysStayed,
                        'unit_price' => $roomPrice,
                        'total_price' => $roomTotal,
                    ];
                }
            }

            // Update patient bill totals
            $patientBill->total_amount += $totalAmount;
            $patientBill->balance_amount = $patientBill->total_amount - $patientBill->paid_amount;
            
            // Update bill status from draft to sent when charges are added
            if ($patientBill->status === 'draft') {
                $patientBill->status = 'sent';
            }
            
            $patientBill->save();

            DB::commit();

            $successMessage = count($addedItems) > 0 
                ? 'Services charged to patient successfully' 
                : 'Bill created successfully. Room charges will be calculated automatically.';

            return response()->json([
                'success' => true,
                'message' => $successMessage,
                'data' => [
                    'bill_id' => $patientBill->id,
                    'bill_number' => $patientBill->bill_number,
                    'total_charged' => $totalAmount,
                    'new_balance' => $patientBill->balance_amount,
                    'items_added' => $addedItems
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to charge patient',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get patient's current bill summary with detailed items.
     * DYNAMICALLY RECALCULATES ROOM CHARGES ON EVERY FETCH.
     */
    public function getPatientBillSummary(Request $request, $birthcare_id, $patient_id): JsonResponse
    {
        try {
            $patientBill = PatientBill::with('items')
                                    ->where('patient_id', $patient_id)
                                    ->where('birthcare_id', $birthcare_id)
                                    ->whereIn('status', ['draft', 'sent', 'partially_paid', 'overdue'])
                                    ->where('balance_amount', '>', 0)
                                    ->first();

            if (!$patientBill) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'has_bill' => false,
                        'message' => 'No active bill found for this patient'
                    ]
                ]);
            }

            // ===== DYNAMIC ROOM CHARGE RECALCULATION =====
            // Update room accommodation charges to reflect current stay duration
            $patientAdmission = PatientAdmission::where('patient_id', $patient_id)
                                              ->where('birth_care_id', $birthcare_id)
                                              ->whereIn('status', ['admitted', 'active', 'in-labor', 'delivered'])
                                              ->orderBy('admission_date', 'desc')
                                              ->first();
            
            if ($patientAdmission && $patientAdmission->room && $patientAdmission->room->price) {
                // Calculate current room charges
                $endDate = $patientAdmission->discharge_date 
                    ? \Carbon\Carbon::parse($patientAdmission->discharge_date) 
                    : \Carbon\Carbon::now();
                $startDate = \Carbon\Carbon::parse($patientAdmission->admission_date);
                
                $endDate = $endDate->startOfDay();
                $startDate = $startDate->startOfDay();
                
                $daysStayed = max(1, $startDate->diffInDays($endDate) + 1);
                $roomPrice = floatval($patientAdmission->room->price);
                $roomTotal = $roomPrice * $daysStayed;
                
                // Build updated description
                $dateRange = $startDate->format('M d, Y');
                if ($daysStayed > 1) {
                    $dateRange .= ' - ' . $endDate->format('M d, Y');
                }
                $roomDescription = "Daily room accommodation ({$dateRange})";
                if (!$patientAdmission->discharge_date) {
                    $roomDescription .= " - Ongoing";
                }
                
                // Find existing room charge item
                $roomChargeItem = $patientBill->items()
                    ->where('service_name', 'LIKE', 'Room:%')
                    ->where('description', 'LIKE', '%Daily room accommodation%')
                    ->first();
                
                if ($roomChargeItem) {
                    // Update existing room charge with current calculation
                    $oldRoomTotal = $roomChargeItem->total_price;
                    $roomChargeItem->quantity = $daysStayed;
                    $roomChargeItem->unit_price = $roomPrice;
                    $roomChargeItem->total_price = $roomTotal;
                    $roomChargeItem->description = $roomDescription;
                    $roomChargeItem->save();
                    
                    // Update bill total
                    $difference = $roomTotal - $oldRoomTotal;
                    $patientBill->total_amount += $difference;
                    $patientBill->balance_amount = $patientBill->total_amount - $patientBill->paid_amount;
                    $patientBill->save();
                    
                    \Log::info("Updated room charge for patient {$patient_id}: {$daysStayed} days × ₱{$roomPrice} = ₱{$roomTotal}");
                }
            }
            // ===== END DYNAMIC RECALCULATION =====

            // Format individual bill items with their details
            $billItems = $patientBill->items->fresh()->map(function ($item) {
                return [
                    'id' => $item->id,
                    'service_name' => $item->service_name,
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'total_price' => $item->total_price,
                    'created_at' => $item->created_at->format('Y-m-d H:i:s')
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'has_bill' => true,
                    'bill_id' => $patientBill->id,
                    'bill_number' => $patientBill->bill_number,
                    'total_amount' => $patientBill->total_amount,
                    'paid_amount' => $patientBill->paid_amount,
                    'balance_amount' => $patientBill->balance_amount,
                    'status' => $patientBill->status,
                    'items_count' => $patientBill->items->count(),
                    'bill_date' => $patientBill->bill_date->format('Y-m-d H:i:s'),
                    'bill_items' => $billItems // Add detailed items
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch patient bill summary',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all bills for a specific patient (including paid bills).
     */
    public function getPatientBills(Request $request, $birthcare_id, $patient_id): JsonResponse
    {
        try {
            $bills = PatientBill::with(['items', 'payments.receiver'])
                               ->where('patient_id', $patient_id)
                               ->where('birthcare_id', $birthcare_id)
                               ->orderBy('bill_date', 'desc')
                               ->get()
                               ->map(function ($bill) {
                                   return [
                                       'id' => $bill->id,
                                       'bill_number' => $bill->bill_number,
                                       'bill_date' => $bill->bill_date->format('Y-m-d'),
                                       'due_date' => $bill->due_date->format('Y-m-d'),
                                       'subtotal' => $bill->subtotal,
                                       'tax_amount' => $bill->tax_amount,
                                       'discount_amount' => $bill->discount_amount,
                                       'total_amount' => $bill->total_amount,
                                       'paid_amount' => $bill->paid_amount,
                                       'balance_amount' => $bill->balance_amount,
                                       'status' => $bill->status,
                                       'notes' => $bill->notes,
                                       'items_count' => $bill->items->count(),
                                       'payments_count' => $bill->payments->count(),
                                       'created_at' => $bill->created_at->format('Y-m-d H:i:s')
                                   ];
                               });

            return response()->json([
                'success' => true,
                'data' => $bills
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch patient bills',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Apply discount to a patient's bill.
     */
    public function applyDiscount(Request $request, $birthcare_id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'bill_id' => 'required|exists:patient_bills,id',
            'discount_amount' => 'required|numeric|min:0',
            'discount_percentage' => 'nullable|numeric|min:0|max:100',
            'discount_reason' => 'required|string|max:255',
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $bill = PatientBill::where('birthcare_id', $birthcare_id)
                             ->findOrFail($request->bill_id);

            // Only allow discount on unpaid bills
            if ($bill->status === 'paid') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot apply discount to paid bills'
                ], 422);
            }

            $discountAmount = $request->discount_amount;
            
            // If percentage is provided, calculate the discount amount
            if ($request->filled('discount_percentage')) {
                $discountAmount = ($bill->subtotal * $request->discount_percentage) / 100;
            }

            // Ensure discount doesn't exceed the subtotal
            if ($discountAmount > $bill->subtotal) {
                return response()->json([
                    'success' => false,
                    'message' => 'Discount cannot exceed the subtotal amount'
                ], 422);
            }

            // Update the bill
            $bill->discount_amount = $discountAmount;
            $bill->total_amount = $bill->subtotal + $bill->tax_amount - $discountAmount;
            $bill->balance_amount = $bill->total_amount - $bill->paid_amount;
            
            // Update notes with discount information
            $discountNote = "Discount Applied: ₱{$discountAmount} - {$request->discount_reason}";
            $bill->notes = $bill->notes ? $bill->notes . "\n" . $discountNote : $discountNote;
            
            if ($request->filled('notes')) {
                $bill->notes .= "\nAdditional Notes: " . $request->notes;
            }

            $bill->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Discount applied successfully',
                'data' => [
                    'bill_id' => $bill->id,
                    'bill_number' => $bill->bill_number,
                    'discount_applied' => $discountAmount,
                    'new_total' => $bill->total_amount,
                    'new_balance' => $bill->balance_amount
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Failed to apply discount',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate and finalize bill from pending charges.
     */
    public function finalizeBill(Request $request, $birthcare_id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'patient_id' => 'required|exists:patients,id',
            'bill_date' => 'nullable|date',
            'due_date' => 'nullable|date|after_or_equal:bill_date',
            'tax_amount' => 'nullable|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'auto_send' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Find existing draft bill
            $bill = PatientBill::where('patient_id', $request->patient_id)
                             ->where('birthcare_id', $birthcare_id)
                             ->where('status', 'draft')
                             ->first();

            if (!$bill) {
                return response()->json([
                    'success' => false,
                    'message' => 'No draft bill found for this patient'
                ], 404);
            }

            // Update bill details
            $bill->bill_date = $request->bill_date ?? now();
            $bill->due_date = $request->due_date ?? now()->addDays(30);
            $bill->tax_amount = $request->tax_amount ?? 0;
            $bill->discount_amount = $request->discount_amount ?? 0;
            $bill->notes = $request->notes;
            
            // Recalculate totals
            $bill->calculateTotals();
            
            // Update status
            $bill->status = $request->auto_send ? 'sent' : 'draft';
            $bill->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Bill finalized successfully',
                'data' => [
                    'bill_id' => $bill->id,
                    'bill_number' => $bill->bill_number,
                    'total_amount' => $bill->total_amount,
                    'status' => $bill->status
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Failed to finalize bill',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk charge multiple patients with the same services.
     */
    public function bulkChargePatients(Request $request, $birthcare_id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'patient_ids' => 'required|array|min:1',
            'patient_ids.*' => 'required|exists:patients,id',
            'services' => 'required|array|min:1',
            'services.*.id' => 'required|exists:patient_charges,id',
            'services.*.quantity' => 'required|integer|min:1',
            'services.*.price' => 'required|numeric|min:0',
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $results = [];
            $errors = [];

            foreach ($request->patient_ids as $patientId) {
                try {
                    // Find or create active bill for this patient
                    $patientBill = PatientBill::where('patient_id', $patientId)
                                            ->where('birthcare_id', $birthcare_id)
                                            ->whereIn('status', ['draft', 'sent', 'partially_paid', 'overdue'])
                                            ->where('balance_amount', '>', 0)
                                            ->first();

                    if (!$patientBill) {
                        $patientBill = PatientBill::create([
                            'patient_id' => $patientId,
                            'birthcare_id' => $birthcare_id,
                            'bill_number' => PatientBill::generateBillNumber(),
                            'bill_date' => now(),
                            'due_date' => now()->addDays(30),
                            'total_amount' => 0,
                            'paid_amount' => 0,
                            'balance_amount' => 0,
                            'status' => 'draft',
                            'notes' => $request->notes,
                            'created_by' => auth()->id() ?? 1,
                        ]);
                    }

                    // Get patient admission data for calculating room accommodation days
                    $patientAdmission = PatientAdmission::where('patient_id', $patientId)
                                                      ->where('birth_care_id', $birthcare_id)
                                                      ->whereIn('status', ['admitted', 'active', 'in-labor', 'delivered'])
                                                      ->orderBy('admission_date', 'desc')
                                                      ->first();

                    $totalAmount = 0;
                    
                    foreach ($request->services as $service) {
                        $serviceData = PatientCharge::find($service['id']);
                        
                        if ($serviceData) {
                            $quantity = $service['quantity'];
                            
                            // Auto-calculate quantity for room accommodation services based on admission days
                            if ($patientAdmission && 
                                $serviceData->category && 
                                (stripos($serviceData->category, 'room') !== false || 
                                 stripos($serviceData->category, 'accommodation') !== false)) {
                                $quantity = $patientAdmission->admission_days;
                            }

                            $itemTotal = $quantity * $service['price'];
                            $totalAmount += $itemTotal;

                            BillItem::create([
                                'patient_bill_id' => $patientBill->id,
                                'patient_charge_id' => $serviceData->id,
                                'service_name' => $serviceData->service_name,
                                'description' => $serviceData->description,
                                'quantity' => $quantity,
                                'unit_price' => $service['price'],
                                'total_price' => $itemTotal,
                            ]);
                        }
                    }

                    // Update bill totals
                    $patientBill->total_amount += $totalAmount;
                    $patientBill->balance_amount = $patientBill->total_amount - $patientBill->paid_amount;
                    $patientBill->save();

                    $results[] = [
                        'patient_id' => $patientId,
                        'bill_id' => $patientBill->id,
                        'bill_number' => $patientBill->bill_number,
                        'amount_charged' => $totalAmount,
                        'status' => 'success'
                    ];

                } catch (\Exception $e) {
                    $errors[] = [
                        'patient_id' => $patientId,
                        'error' => $e->getMessage()
                    ];
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Bulk charging completed',
                'data' => [
                    'successful_charges' => $results,
                    'failed_charges' => $errors,
                    'total_processed' => count($request->patient_ids),
                    'successful_count' => count($results),
                    'failed_count' => count($errors)
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Failed to process bulk charges',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Determine the correct room charge based on patient admission room.
     */
    private function determineCorrectRoomCharge($patientAdmission, $currentServiceData)
    {
        // If patient admission doesn't have a room, return the current service
        if (!$patientAdmission->room_id || !$patientAdmission->room) {
            return $currentServiceData;
        }

        // If the room has an associated patient charge, use that
        if ($patientAdmission->room->patient_charge_id) {
            $roomCharge = PatientCharge::find($patientAdmission->room->patient_charge_id);
            if ($roomCharge) {
                return $roomCharge;
            }
        }

        // Otherwise, return the current service data
        return $currentServiceData;
    }
}
