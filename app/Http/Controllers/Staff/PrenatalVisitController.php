<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\PrenatalVisit;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class PrenatalVisitController extends Controller
{
    /**
     * Display a listing of prenatal visits for a birthcare facility.
     *
     * @param Request $request
     * @param int $birthcareId
     * @return JsonResponse
     */
    public function index(Request $request, int $birthcareId): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Get all prenatal visits for patients belonging to this birthcare
            $prenatalVisits = PrenatalVisit::whereHas('patient', function ($query) use ($birthcareId) {
                $query->where('birth_care_id', $birthcareId);
            })
            ->with('patient')
            ->orderBy('scheduled_date', 'desc')
            ->get();
            
            return response()->json([
                'data' => $prenatalVisits
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve prenatal visits',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Store a newly created prenatal visit.
     *
     * @param Request $request
     * @param int $birthcareId
     * @return JsonResponse
     */
    public function store(Request $request, int $birthcareId): JsonResponse
    {
        try {
            // Validate the request
            $validated = $request->validate([
                'patient_id' => 'required|integer|exists:patients,id',
                'visit_number' => 'required|integer|min:1|max:8',
                'visit_name' => 'required|string|max:255',
                'recommended_week' => 'required|integer|min:1|max:50',
                'scheduled_date' => 'required|date',
                'status' => 'required|in:Scheduled,Completed,Missed'
            ]);
            
            // Verify that the patient belongs to this birthcare facility
            $patient = Patient::where('id', $validated['patient_id'])
                ->where('birth_care_id', $birthcareId)
                ->first();
            
            if (!$patient) {
                return response()->json([
                    'message' => 'Patient not found or does not belong to this facility.'
                ], 404);
            }
            
            // Check if a visit with this number already exists for this patient
            $existingVisit = PrenatalVisit::where('patient_id', $validated['patient_id'])
                ->where('visit_number', $validated['visit_number'])
                ->first();
            
            if ($existingVisit) {
                return response()->json([
                    'message' => "Visit {$validated['visit_number']} already exists for this patient."
                ], 409);
            }
            
            // Create the prenatal visit
            $prenatalVisit = PrenatalVisit::create($validated);
            
            // Load the patient relationship
            $prenatalVisit->load('patient');
            
            return response()->json([
                'message' => 'Prenatal visit scheduled successfully!',
                'data' => $prenatalVisit
            ], 201);
            
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create prenatal visit',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Store multiple prenatal visits at once (batch creation).
     *
     * @param Request $request
     * @param int $birthcareId
     * @return JsonResponse
     */
    public function batchStore(Request $request, int $birthcareId): JsonResponse
    {
        try {
            // Validate the request
            $validated = $request->validate([
                'visits' => 'required|array|min:1',
                'visits.*.patient_id' => 'required|integer|exists:patients,id',
                'visits.*.visit_number' => 'required|integer|min:1|max:8',
                'visits.*.visit_name' => 'required|string|max:255',
                'visits.*.recommended_week' => 'required',
                'visits.*.scheduled_date' => 'required|date',
                'visits.*.status' => 'required|in:Scheduled,Completed,Missed',
                'visits.*.notes' => 'nullable|string'
            ]);
            
            $createdVisits = [];
            $errors = [];
            
            foreach ($validated['visits'] as $visitData) {
                // Verify that the patient belongs to this birthcare facility
                $patient = Patient::where('id', $visitData['patient_id'])
                    ->where('birth_care_id', $birthcareId)
                    ->first();
                
                if (!$patient) {
                    $errors[] = "Patient {$visitData['patient_id']} not found or does not belong to this facility.";
                    continue;
                }
                
                // Check if a visit with this number already exists for this patient
                $existingVisit = PrenatalVisit::where('patient_id', $visitData['patient_id'])
                    ->where('visit_number', $visitData['visit_number'])
                    ->first();
                
                if ($existingVisit) {
                    $errors[] = "Visit {$visitData['visit_number']} already exists for patient {$visitData['patient_id']}.";
                    continue;
                }
                
                // Create the prenatal visit
                $prenatalVisit = PrenatalVisit::create($visitData);
                $prenatalVisit->load('patient');
                $createdVisits[] = $prenatalVisit;
            }
            
            $response = [
                'message' => 'Batch prenatal visits processing completed.',
                'created' => count($createdVisits),
                'data' => $createdVisits
            ];
            
            if (!empty($errors)) {
                $response['errors'] = $errors;
                $response['skipped'] = count($errors);
            }
            
            return response()->json($response, 201);
            
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create prenatal visits',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Display the specified prenatal visit.
     *
     * @param Request $request
     * @param int $birthcareId
     * @param int $visitId
     * @return JsonResponse
     */
    public function show(Request $request, int $birthcareId, int $visitId): JsonResponse
    {
        try {
            $prenatalVisit = PrenatalVisit::whereHas('patient', function ($query) use ($birthcareId) {
                $query->where('birth_care_id', $birthcareId);
            })
            ->with('patient')
            ->findOrFail($visitId);
            
            return response()->json([
                'data' => $prenatalVisit
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Prenatal visit not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }
    
    /**
     * Update the specified prenatal visit.
     *
     * @param Request $request
     * @param int $birthcareId
     * @param int $visitId
     * @return JsonResponse
     */
    public function update(Request $request, int $birthcareId, int $visitId): JsonResponse
    {
        try {
            // Find the prenatal visit
            $prenatalVisit = PrenatalVisit::whereHas('patient', function ($query) use ($birthcareId) {
                $query->where('birth_care_id', $birthcareId);
            })->findOrFail($visitId);
            
            // Validate the request
            $validated = $request->validate([
                'visit_number' => 'sometimes|integer|min:1|max:8',
                'visit_name' => 'sometimes|string|max:255',
                'recommended_week' => 'sometimes|integer|min:1|max:50',
                'scheduled_date' => 'sometimes|date',
                'status' => 'sometimes|in:Scheduled,Completed,Missed'
            ]);
            
            // Update the prenatal visit
            $prenatalVisit->update($validated);
            
            // Load the patient relationship
            $prenatalVisit->load('patient');
            
            return response()->json([
                'message' => 'Prenatal visit updated successfully!',
                'data' => $prenatalVisit
            ]);
            
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update prenatal visit',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Update the status of a specific prenatal visit.
     *
     * @param Request $request
     * @param int $birthcareId
     * @param int $visitId
     * @return JsonResponse
     */
    public function updateStatus(Request $request, int $birthcareId, int $visitId): JsonResponse
    {
        try {
            // Find the prenatal visit
            $prenatalVisit = PrenatalVisit::whereHas('patient', function ($query) use ($birthcareId) {
                $query->where('birth_care_id', $birthcareId);
            })->findOrFail($visitId);
            
            // Get status from multiple possible fields and normalize case
            $status = $request->input('status') ?? $request->input('new_status') ?? $request->input('visit_status');
            
            if (!$status) {
                return response()->json([
                    'message' => 'Status field is required',
                    'error' => 'Please provide status, new_status, or visit_status field'
                ], 422);
            }
            
            // Normalize status to proper case
            $normalizedStatus = ucfirst(strtolower($status));
            
            // Validate status value
            if (!in_array($normalizedStatus, ['Scheduled', 'Completed', 'Missed'])) {
                return response()->json([
                    'message' => 'Invalid status value',
                    'error' => 'Status must be one of: Scheduled, Completed, Missed'
                ], 422);
            }
            
            // Store the original status before update
            $originalStatus = $prenatalVisit->status;
            
            // Update the status
            $prenatalVisit->update(['status' => $normalizedStatus]);
            
            // Force refresh from database to confirm the update persisted
            $prenatalVisit->refresh();
            
            // Load the patient relationship
            $prenatalVisit->load('patient');
            
            // Verify the update actually happened
            if ($prenatalVisit->status !== $normalizedStatus) {
                throw new \Exception("Status update failed - database shows status as '{$prenatalVisit->status}' but expected '{$normalizedStatus}'");
            }
            
            return response()->json([
                'message' => 'Visit status updated successfully!',
                'data' => $prenatalVisit,
                'previous_status' => $originalStatus,
                'new_status' => $normalizedStatus,
                'debug' => [
                    'original' => $originalStatus,
                    'requested' => $status,
                    'normalized' => $normalizedStatus,
                    'final_db_status' => $prenatalVisit->status,
                    'visit_id' => $visitId
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update visit status',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Debug method to check the actual status of a visit in the database.
     *
     * @param Request $request
     * @param int $birthcareId
     * @param int $visitId
     * @return JsonResponse
     */
    public function debugStatus(Request $request, int $birthcareId, int $visitId): JsonResponse
    {
        try {
            $prenatalVisit = PrenatalVisit::whereHas('patient', function ($query) use ($birthcareId) {
                $query->where('birth_care_id', $birthcareId);
            })->with('patient')->findOrFail($visitId);
            
            return response()->json([
                'visit_id' => $visitId,
                'patient_name' => $prenatalVisit->patient->first_name . ' ' . $prenatalVisit->patient->last_name,
                'current_status' => $prenatalVisit->status,
                'scheduled_date' => $prenatalVisit->scheduled_date,
                'visit_number' => $prenatalVisit->visit_number,
                'visit_name' => $prenatalVisit->visit_name,
                'updated_at' => $prenatalVisit->updated_at,
                'raw_data' => $prenatalVisit->toArray()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to get visit status',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Remove the specified prenatal visit.
     *
     * @param Request $request
     * @param int $birthcareId
     * @param int $visitId
     * @return JsonResponse
     */
    public function destroy(Request $request, int $birthcareId, int $visitId): JsonResponse
    {
        try {
            $prenatalVisit = PrenatalVisit::whereHas('patient', function ($query) use ($birthcareId) {
                $query->where('birth_care_id', $birthcareId);
            })->findOrFail($visitId);
            
            $prenatalVisit->delete();
            
            return response()->json([
                'message' => 'Prenatal visit deleted successfully!'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete prenatal visit',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
