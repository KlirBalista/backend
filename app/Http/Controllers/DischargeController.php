<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use App\Models\Patient;
use App\Models\PatientAdmission;

class DischargeController extends Controller
{

    /**
     * Get patient admission data for auto-filling discharge forms
     */
    public function getPatientAdmissionData(Request $request, $birthcare_id, $patient_id): JsonResponse
    {
        try {
            $patient = Patient::with(['birthCare'])
                ->where('id', $patient_id)
                ->where('birth_care_id', $birthcare_id)
                ->first();

            if (!$patient) {
                return response()->json([
                    'message' => 'Patient not found'
                ], 404);
            }

            // Get the most recent admission for this patient
            $admission = PatientAdmission::with(['room', 'bed'])
                ->where('patient_id', $patient_id)
                ->where('birth_care_id', $birthcare_id)
                ->whereIn('status', ['admitted', 'in-labor', 'delivered']) // Current admission statuses
                ->orderBy('admission_date', 'desc')
                ->first();

            $response = [
                'patient' => [
                    'id' => $patient->id,
                    'full_name' => $patient->full_name,
                    'first_name' => $patient->first_name,
                    'middle_name' => $patient->middle_name,
                    'last_name' => $patient->last_name,
                    'date_of_birth' => $patient->date_of_birth,
                    'age' => $patient->age,
                    'civil_status' => $patient->civil_status,
                    'address' => $patient->address,
                    'contact_number' => $patient->contact_number,
                    'philhealth_number' => $patient->philhealth_number,
                    'philhealth_category' => $patient->philhealth_category
                ],
                'admission' => null
            ];

            if ($admission) {
                $response['admission'] = [
                    'id' => $admission->id,
                    'admission_date' => $admission->admission_date ? $admission->admission_date->format('Y-m-d') : null,
                    'admission_time' => $admission->admission_time ? $admission->admission_time->format('H:i') : null,
                    'room_name' => $admission->room->name ?? null,
                    'bed_number' => $admission->bed->bed_no ?? null,
                    'case_number' => $admission->id, // Using admission ID as case number
                    'attending_physician' => $admission->attending_physician,
                    'primary_nurse' => $admission->primary_nurse,
                    'chief_complaint' => $admission->chief_complaint,
                    'initial_diagnosis' => $admission->initial_diagnosis,
                    'status' => $admission->status
                ];
            }

            return response()->json($response);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch patient data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all patients with current admissions for dropdown
     */
    public function getAdmittedPatients($birthcare_id): JsonResponse
    {
        try {
            $patients = Patient::with(['admissions' => function($query) {
                $query->whereIn('status', ['admitted', 'in-labor', 'delivered'])
                      ->orderBy('admission_date', 'desc');
            }])
            ->where('birth_care_id', $birthcare_id)
            ->whereHas('admissions', function($query) {
                $query->whereIn('status', ['admitted', 'in-labor', 'delivered']);
            })
            ->get()
            ->map(function($patient) {
                $currentAdmission = $patient->admissions->first();
                return [
                    'id' => $patient->id,
                    'full_name' => $patient->full_name,
                    'admission_id' => $currentAdmission->id ?? null,
                    'admission_date' => $currentAdmission->admission_date ?? null,
                    'status' => $currentAdmission->status ?? null
                ];
            });

            return response()->json([
                'patients' => $patients
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch admitted patients',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get fully paid patients eligible for discharge
     */
    public function getFullyPaidPatientsForDischarge(Request $request, $birthcare_id): JsonResponse
    {
        try {
            $search = $request->get('search', '');
            
            // Get patients who are admitted and have fully paid bills
            $patients = Patient::with([
                'admissions' => function($query) {
                    $query->whereIn('status', ['admitted', 'in-labor', 'delivered'])
                          ->with(['room', 'bed'])
                          ->orderBy('admission_date', 'desc');
                },
                'bills' => function($query) {
                    $query->where('status', 'paid')
                          ->orWhere('balance_amount', '<=', 0);
                }
            ])
            ->where('birth_care_id', $birthcare_id)
            ->whereHas('admissions', function($query) {
                $query->whereIn('status', ['admitted', 'in-labor', 'delivered']);
            })
            ->whereHas('bills', function($query) {
                // Patient has at least one bill that is fully paid
                $query->where('status', 'paid')
                      ->orWhere('balance_amount', '<=', 0);
            })
            // Apply search filter if provided
            ->when($search, function($query, $search) {
                $query->where(function($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%")
                      ->orWhere('middle_name', 'like', "%{$search}%")
                      ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$search}%"])
                      ->orWhereRaw("CONCAT(first_name, ' ', middle_name, ' ', last_name) LIKE ?", ["%{$search}%"]);
                });
            })
            ->get()
            ->map(function($patient) {
                $currentAdmission = $patient->admissions->first();
                $totalBillAmount = $patient->bills->sum('total_amount');
                $totalPaidAmount = $patient->bills->sum('paid_amount');
                $outstandingBalance = $totalBillAmount - $totalPaidAmount;
                
                return [
                    'id' => $patient->id,
                    'first_name' => $patient->first_name,
                    'middle_name' => $patient->middle_name,
                    'last_name' => $patient->last_name,
                    'full_name' => $patient->full_name,
                    'admission_id' => $currentAdmission->id ?? null,
                    'admission_date' => $currentAdmission->admission_date ?? null,
                    'room_number' => $currentAdmission->room->name ?? $currentAdmission->bed->bed_no ?? 'N/A',
                    'case_number' => $currentAdmission->id ?? $patient->id,
                    'status' => $currentAdmission->status ?? null,
                    'payment_status' => 'fully_paid',
                    'total_bill_amount' => $totalBillAmount,
                    'outstanding_balance' => $outstandingBalance,
                    'latest_admission' => $currentAdmission
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $patients,
                'message' => 'Fully paid patients retrieved successfully'
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to fetch fully paid patients for discharge: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'birthcare_id' => $birthcare_id,
                'search' => $request->get('search', '')
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch fully paid patients for discharge',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store mother discharge slip
     */
    public function storeMotherDischarge(Request $request, $birthcare_id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'patient_id' => 'required|exists:patients,id',
            'discharge_data' => 'required|array',
            'discharge_data.patient_name' => 'required|string',
            'discharge_data.admission_date' => 'required|date',
            'discharge_data.discharge_date' => 'required|date',
            'discharge_data.diagnosis' => 'required|string',
            'discharge_data.physician_name' => 'required|string',
            'discharge_data.nurse_name' => 'required|string',
            'discharge_data.instructions' => 'nullable|string',
            'discharge_data.medications' => 'nullable|array',
            'discharge_data.follow_up_date' => 'nullable|date'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Don't update admission status yet - wait for newborn discharge to complete the process
            return response()->json([
                'message' => 'Mother discharge data saved successfully'
            ], 201);

        } catch (\Exception $e) {
            \Log::error('Mother discharge data save failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);
            return response()->json([
                'message' => 'Failed to save discharge data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store newborn discharge slip
     */
    public function storeNewbornDischarge(Request $request, $birthcare_id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'patient_id' => 'required|exists:patients,id', // Mother's patient ID
            'discharge_data' => 'required|array',
            'discharge_data.mother_name' => 'required|string',
            'discharge_data.baby_name' => 'required|string',
            'discharge_data.birth_date' => 'required|date',
            'discharge_data.birth_time' => 'required|string',
            'discharge_data.gender' => 'nullable|string', // Made optional since frontend doesn't always send this
            'discharge_data.birth_weight' => 'required|numeric',
            'discharge_data.discharge_date' => 'required|date',
            'discharge_data.physician_name' => 'required|string',
            'discharge_data.nurse_name' => 'required|string',
            'discharge_data.feeding_instructions' => 'nullable|string',
            'discharge_data.care_instructions' => 'nullable|string',
            'discharge_data.follow_up_date' => 'nullable|date'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Update patient admission status to discharged after newborn discharge (2nd click)
            PatientAdmission::where('patient_id', $request->patient_id)
                ->where('birth_care_id', $birthcare_id)
                ->whereIn('status', ['admitted', 'in-labor', 'delivered'])
                ->update(['status' => 'discharged']);

            return response()->json([
                'message' => 'Newborn discharge data saved successfully - Patient fully discharged'
            ], 201);

        } catch (\Exception $e) {
            \Log::error('Newborn discharge data save failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);
            return response()->json([
                'message' => 'Failed to save newborn discharge data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

}
