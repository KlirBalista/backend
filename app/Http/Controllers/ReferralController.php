<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Referral;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Response;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Patient;
use App\Services\PDFService;
use App\Models\BirthCare;
use App\Models\PatientDocument;
use Illuminate\Support\Facades\Storage;

class ReferralController extends Controller
{
    /**
     * Display a listing of referrals.
     */
    public function index(Request $request, $birthcare_id): JsonResponse
    {
        $query = Referral::with(['patient', 'createdBy'])
            ->where('birth_care_id', $birthcare_id);

        // Apply filters
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->search($search);
        }

        if ($request->filled('status')) {
            $query->withStatus($request->get('status'));
        }

        if ($request->filled('urgency_level')) {
            $query->where('urgency_level', $request->get('urgency_level'));
        }

        if ($request->filled('patient_id')) {
            $query->where('patient_id', $request->get('patient_id'));
        }

        // Date range filter
        if ($request->filled('date_from')) {
            $query->where('referral_date', '>=', $request->get('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->where('referral_date', '<=', $request->get('date_to'));
        }

        $referrals = $query->orderBy('referral_date', 'desc')
                          ->orderBy('referral_time', 'desc')
                          ->paginate(15);

        return response()->json($referrals);
    }

    /**
     * Store a newly created referral.
     */
    public function store(Request $request, $birthcare_id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'patient_id' => 'required|exists:patients,id',
            'referring_facility' => 'required|string|max:255',
            'referring_physician' => 'required|string|max:255',
            'referring_physician_contact' => 'nullable|string|max:255',
            'receiving_facility' => 'required|string|max:255',
            'receiving_physician' => 'nullable|string|max:255',
            'receiving_physician_contact' => 'nullable|string|max:255',
            'referral_date' => 'required|date',
            'referral_time' => 'required|date_format:H:i',
            'urgency_level' => 'required|in:routine,urgent,emergency,critical',
            'reason_for_referral' => 'required|string',
            'clinical_summary' => 'nullable|string',
            'current_diagnosis' => 'nullable|string',
            'relevant_history' => 'nullable|string',
            'current_medications' => 'nullable|string',
            'allergies' => 'nullable|string',
            'vital_signs' => 'nullable|string',
            'laboratory_results' => 'nullable|string',
            'imaging_results' => 'nullable|string',
            'treatment_provided' => 'nullable|string',
            'patient_condition' => 'nullable|string|max:100',
            'transportation_mode' => 'required|in:ambulance,private_transport,helicopter,wheelchair,stretcher',
            'accompanies_patient' => 'nullable|string|max:255',
            'special_instructions' => 'nullable|string',
            'equipment_required' => 'nullable|string|max:255',
            'isolation_precautions' => 'nullable|string|max:255',
            'anticipated_care_level' => 'nullable|string|max:100',
            'expected_duration' => 'nullable|string|max:255',
            'insurance_information' => 'nullable|string',
            'family_contact_name' => 'nullable|string|max:255',
            'family_contact_phone' => 'nullable|string|max:50',
            'family_contact_relationship' => 'nullable|string|max:100',
            'status' => 'required|in:pending,accepted,completed,cancelled',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $referral = Referral::create([
                'patient_id' => $request->patient_id,
                'birth_care_id' => $birthcare_id,
                'referring_facility' => $request->referring_facility,
                'referring_physician' => $request->referring_physician,
                'referring_physician_contact' => $request->referring_physician_contact,
                'receiving_facility' => $request->receiving_facility,
                'receiving_physician' => $request->receiving_physician,
                'receiving_physician_contact' => $request->receiving_physician_contact,
                'referral_date' => $request->referral_date,
                'referral_time' => $request->referral_time,
                'urgency_level' => $request->urgency_level,
                'reason_for_referral' => $request->reason_for_referral,
                'clinical_summary' => $request->clinical_summary,
                'current_diagnosis' => $request->current_diagnosis,
                'relevant_history' => $request->relevant_history,
                'current_medications' => $request->current_medications,
                'allergies' => $request->allergies,
                'vital_signs' => $request->vital_signs,
                'laboratory_results' => $request->laboratory_results,
                'imaging_results' => $request->imaging_results,
                'treatment_provided' => $request->treatment_provided,
                'patient_condition' => $request->patient_condition,
                'transportation_mode' => $request->transportation_mode,
                'accompanies_patient' => $request->accompanies_patient,
                'special_instructions' => $request->special_instructions,
                'equipment_required' => $request->equipment_required,
                'isolation_precautions' => $request->isolation_precautions,
                'anticipated_care_level' => $request->anticipated_care_level,
                'expected_duration' => $request->expected_duration,
                'insurance_information' => $request->insurance_information,
                'family_contact_name' => $request->family_contact_name,
                'family_contact_phone' => $request->family_contact_phone,
                'family_contact_relationship' => $request->family_contact_relationship,
                'status' => $request->status ?? 'pending',
                'notes' => $request->notes,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            // Generate and save PDF to patient documents
            $this->saveReferralPDFToDocuments($referral, $birthcare_id);

            DB::commit();

            return response()->json([
                'message' => 'Referral created successfully',
                'data' => $referral->load(['patient', 'createdBy'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'message' => 'Failed to create referral',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified referral.
     */
    public function show($birthcare_id, Referral $referral): JsonResponse
    {
        $referral->load(['patient', 'createdBy', 'updatedBy']);
        return response()->json($referral);
    }

    /**
     * Update the specified referral.
     */
    public function update(Request $request, $birthcare_id, Referral $referral): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'patient_id' => 'sometimes|required|exists:patients,id',
            'referring_facility' => 'sometimes|required|string|max:255',
            'referring_physician' => 'sometimes|required|string|max:255',
            'referring_physician_contact' => 'nullable|string|max:255',
            'receiving_facility' => 'sometimes|required|string|max:255',
            'receiving_physician' => 'nullable|string|max:255',
            'receiving_physician_contact' => 'nullable|string|max:255',
            'referral_date' => 'sometimes|required|date',
            'referral_time' => 'sometimes|required|date_format:H:i',
            'urgency_level' => 'sometimes|required|in:routine,urgent,emergency,critical',
            'reason_for_referral' => 'sometimes|required|string',
            'clinical_summary' => 'nullable|string',
            'current_diagnosis' => 'nullable|string',
            'relevant_history' => 'nullable|string',
            'current_medications' => 'nullable|string',
            'allergies' => 'nullable|string',
            'vital_signs' => 'nullable|string',
            'laboratory_results' => 'nullable|string',
            'imaging_results' => 'nullable|string',
            'treatment_provided' => 'nullable|string',
            'patient_condition' => 'nullable|string|max:100',
            'transportation_mode' => 'sometimes|required|in:ambulance,private_transport,helicopter,wheelchair,stretcher',
            'accompanies_patient' => 'nullable|string|max:255',
            'special_instructions' => 'nullable|string',
            'equipment_required' => 'nullable|string|max:255',
            'isolation_precautions' => 'nullable|string|max:255',
            'anticipated_care_level' => 'nullable|string|max:100',
            'expected_duration' => 'nullable|string|max:255',
            'insurance_information' => 'nullable|string',
            'family_contact_name' => 'nullable|string|max:255',
            'family_contact_phone' => 'nullable|string|max:50',
            'family_contact_relationship' => 'nullable|string|max:100',
            'status' => 'sometimes|required|in:pending,accepted,completed,cancelled',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $updateData = $request->all();
            $updateData['updated_by'] = auth()->id();

            $referral->update($updateData);

            // Generate and save updated PDF to patient documents
            $this->saveReferralPDFToDocuments($referral, $birthcare_id);

            DB::commit();

            return response()->json([
                'message' => 'Referral updated successfully',
                'data' => $referral->load(['patient', 'createdBy', 'updatedBy'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'message' => 'Failed to update referral',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified referral.
     */
    public function destroy($birthcare_id, Referral $referral): JsonResponse
    {
        try {
            DB::beginTransaction();
            
            $referral->delete();
            
            DB::commit();
            
            return response()->json([
                'message' => 'Referral deleted successfully'
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'message' => 'Failed to delete referral',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate PDF for the specified referral.
     */
    public function generatePDF($birthcare_id, Referral $referral): Response
    {
        try {
            $referral->load(['patient', 'createdBy']);
            
            // Get facility information
            $facility = BirthCare::find($birthcare_id);
            
            // Generate PDF using PDFService
            $pdf = PDFService::generateReferralPDF($referral, $facility);
            
            // Generate filename
            $patientName = $referral->patient 
                ? str_replace(' ', '_', $referral->patient->first_name . '_' . $referral->patient->last_name)
                : 'unknown_patient';
            $filename = "referral_{$patientName}_{$referral->referral_date}.pdf";
            
            // Return PDF as download
            return response($pdf->Output('', 'S'), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to generate PDF',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get referrals statistics for dashboard.
     */
    public function stats($birthcare_id): JsonResponse
    {
        try {
            $stats = [
                'total_referrals' => Referral::where('birth_care_id', $birthcare_id)->count(),
                'pending_referrals' => Referral::where('birth_care_id', $birthcare_id)->where('status', 'pending')->count(),
                'completed_referrals' => Referral::where('birth_care_id', $birthcare_id)->where('status', 'completed')->count(),
                'urgent_referrals' => Referral::where('birth_care_id', $birthcare_id)->where('urgency_level', 'urgent')->count(),
                'emergency_referrals' => Referral::where('birth_care_id', $birthcare_id)->where('urgency_level', 'emergency')->count(),
                'recent_referrals' => Referral::with(['patient'])
                    ->where('birth_care_id', $birthcare_id)
                    ->orderBy('created_at', 'desc')
                    ->limit(5)
                    ->get(),
            ];

            return response()->json($stats);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to get referrals statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate referral PDF and save it to patient documents
     */
    private function saveReferralPDFToDocuments(Referral $referral, $birthcare_id)
    {
        try {
            $referral->load(['patient', 'createdBy']);
            
            // Get facility information
            $facility = BirthCare::find($birthcare_id);
            
            // Generate PDF using PDFService
            $pdf = PDFService::generateReferralPDF($referral, $facility);
            
            // Generate filename and title
            $patientName = $referral->patient 
                ? $referral->patient->first_name . ' ' . $referral->patient->last_name
                : 'Unknown Patient';
            
            $documentTitle = "Referral - {$patientName} - {$referral->referral_date}";
            $fileName = time() . '_referral_' . str_replace(' ', '_', $patientName) . '_' . $referral->referral_date . '.pdf';
            $filePath = $fileName;
            
            // Save PDF to Supabase storage
            $pdfContent = $pdf->Output('', 'S'); // Get PDF as string
            Storage::disk('supabase_patient')->put($filePath, $pdfContent, [
                'mimetype' => 'application/pdf',
            ]);
            $fileSize = Storage::disk('supabase_patient')->size($filePath);
            
            // Check for duplicate titles and apply numeric suffix if needed
            $finalTitle = $this->getUniqueDocumentTitle($documentTitle, $referral->patient_id, $birthcare_id);
            
            // Create patient document record
            PatientDocument::create([
                'patient_id' => $referral->patient_id,
                'birth_care_id' => $birthcare_id,
                'title' => $finalTitle,
                'file_name' => $fileName,
                'file_path' => $filePath,
                'document_type' => 'Referral',
                'file_size' => $fileSize,
                'mime_type' => 'application/pdf',
                'metadata' => [
                    'referral_id' => $referral->id,
                    'referring_facility' => $referral->referring_facility,
                    'receiving_facility' => $referral->receiving_facility,
                    'urgency_level' => $referral->urgency_level,
                    'auto_generated' => true
                ],
                'created_by' => auth()->id(),
            ]);
            
        } catch (\Exception $e) {
            // Log the error but don't fail the referral creation
            \Log::error('Failed to save referral PDF to patient documents: ' . $e->getMessage());
        }
    }
    
    /**
     * Generate a unique document title by adding numeric suffix if title already exists
     */
    private function getUniqueDocumentTitle(string $originalTitle, int $patientId, int $birthcareId): string
    {
        // Check if the original title exists
        $existingDocument = PatientDocument::where('title', $originalTitle)
            ->where('patient_id', $patientId)
            ->where('birth_care_id', $birthcareId)
            ->first();
        
        // If original title doesn't exist, use it as is
        if (!$existingDocument) {
            return $originalTitle;
        }
        
        // Find all documents with titles that start with the original title
        $pattern = $originalTitle . '%';
        $existingTitles = PatientDocument::where('title', 'like', $pattern)
            ->where('patient_id', $patientId)
            ->where('birth_care_id', $birthcareId)
            ->pluck('title')
            ->toArray();
        
        // Extract numeric suffixes from existing titles
        $maxSuffix = 0;
        
        foreach ($existingTitles as $title) {
            if ($title === $originalTitle) {
                $maxSuffix = max($maxSuffix, 0);
            } else {
                // Check if title matches pattern "BaseTitle (n)"
                $pattern = '/^' . preg_quote($originalTitle, '/') . ' \((\d+)\)$/';
                if (preg_match($pattern, $title, $matches)) {
                    $maxSuffix = max($maxSuffix, (int)$matches[1]);
                }
            }
        }
        
        // Generate next available suffix
        $nextSuffix = $maxSuffix + 1;
        return $originalTitle . ' (' . $nextSuffix . ')';
    }
}
