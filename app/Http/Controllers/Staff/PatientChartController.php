<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\PatientChart;
use App\Models\PatientAdmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PatientChartController extends Controller
{
    /**
     * Display a listing of patient charts for a birth care center
     */
    public function index(Request $request, $birthcare_id)
    {
        $query = PatientChart::forBirthcare($birthcare_id)
                            ->with(['patient', 'admission']);

        // Filter by patient if provided
        if ($request->has('patient_id')) {
            $query->forPatient($request->patient_id);
        }

        // Filter by status if provided
        if ($request->has('status')) {
            $query->byStatus($request->status);
        }

        $charts = $query->orderBy('updated_at', 'desc')
                       ->paginate($request->get('per_page', 15));

        return response()->json($charts);
    }

    /**
     * Store a newly created patient chart
     */
    public function store(Request $request, $birthcare_id)
    {
        Log::info('Patient chart store method called', [
            'birthcare_id' => $birthcare_id,
            'request_data' => $request->all(),
            'user_id' => Auth::id()
        ]);

        $validator = Validator::make($request->all(), [
            'patient_id' => 'required|exists:patients,id',
            'birth_care_id' => 'nullable|exists:birth_cares,id',
            'admission_id' => 'nullable|exists:patient_admissions,id',
            'patient_info' => 'nullable|array',
            'medical_history' => 'nullable|array',
            'admission_assessment' => 'nullable|array',
            'delivery_record' => 'nullable|array',
            'newborn_care' => 'nullable|array',
            'postpartum_notes' => 'nullable|array',
            'discharge_summary' => 'nullable|array',
            'status' => 'nullable|in:draft,completed,discharged',
        ]);

        if ($validator->fails()) {
            Log::error('Patient chart validation failed', [
                'errors' => $validator->errors(),
                'request_data' => $request->all()
            ]);
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        Log::info('Patient chart validation passed');

        // Verify patient belongs to this birth care center
        $patient = Patient::where('id', $request->patient_id)
                         ->where('birth_care_id', $birthcare_id)
                         ->first();

        if (!$patient) {
            return response()->json([
                'message' => 'Patient not found or does not belong to this birth care center'
            ], 404);
        }

        // Check if chart already exists for this patient
        $existingChart = PatientChart::forBirthcare($birthcare_id)
                                   ->forPatient($request->patient_id)
                                   ->first();

        if ($existingChart) {
            Log::info('Found existing patient chart, returning it', [
                'existing_chart_id' => $existingChart->id,
                'patient_id' => $request->patient_id
            ]);
            
            return response()->json([
                'message' => 'Patient chart already exists',
                'chart' => $existingChart->load(['patient', 'admission'])
            ], 200);
        }

        try {
            $chartData = $request->only([
                'patient_id', 'admission_id', 'patient_info', 'medical_history',
                'admission_assessment', 'delivery_record', 'newborn_care',
                'postpartum_notes', 'discharge_summary', 'status'
            ]);

            $chartData['birth_care_id'] = $birthcare_id;
            $chartData['created_by'] = Auth::id();
            $chartData['status'] = $chartData['status'] ?? 'draft';

            $chart = PatientChart::create($chartData);

            Log::info('Patient chart created', [
                'chart_id' => $chart->id,
                'patient_id' => $request->patient_id,
                'birth_care_id' => $birthcare_id,
                'created_by' => Auth::id()
            ]);

            return response()->json([
                'message' => 'Patient chart created successfully',
                'chart' => $chart->load(['patient', 'admission'])
            ], 201);

        } catch (\Exception $e) {
            Log::error('Failed to create patient chart', [
                'error' => $e->getMessage(),
                'patient_id' => $request->patient_id,
                'birth_care_id' => $birthcare_id
            ]);

            return response()->json([
                'message' => 'Failed to create patient chart',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified patient chart
     */
    public function show($birthcare_id, PatientChart $chart)
    {
        // Ensure chart belongs to this birth care center
        if ($chart->birth_care_id != $birthcare_id) {
            return response()->json(['message' => 'Patient chart not found'], 404);
        }

        return response()->json($chart->load(['patient', 'admission']));
    }

    /**
     * Update the specified patient chart
     */
    public function update(Request $request, $birthcare_id, PatientChart $chart)
    {
        // Ensure chart belongs to this birth care center
        if ($chart->birth_care_id != $birthcare_id) {
            return response()->json(['message' => 'Patient chart not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'patient_info' => 'nullable|array',
            'medical_history' => 'nullable|array',
            'admission_assessment' => 'nullable|array',
            'delivery_record' => 'nullable|array',
            'newborn_care' => 'nullable|array',
            'postpartum_notes' => 'nullable|array',
            'discharge_summary' => 'nullable|array',
            'status' => 'nullable|in:draft,completed,discharged',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $updateData = $request->only([
                'patient_info', 'medical_history', 'admission_assessment',
                'delivery_record', 'newborn_care', 'postpartum_notes',
                'discharge_summary', 'status'
            ]);

            $updateData['updated_by'] = Auth::id();

            $chart->update($updateData);

            Log::info('Patient chart updated', [
                'chart_id' => $chart->id,
                'patient_id' => $chart->patient_id,
                'birth_care_id' => $birthcare_id,
                'updated_by' => Auth::id()
            ]);

            return response()->json([
                'message' => 'Patient chart updated successfully',
                'chart' => $chart->fresh()->load(['patient', 'admission'])
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update patient chart', [
                'error' => $e->getMessage(),
                'chart_id' => $chart->id,
                'birth_care_id' => $birthcare_id
            ]);

            return response()->json([
                'message' => 'Failed to update patient chart',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified patient chart
     */
    public function destroy($birthcare_id, PatientChart $chart)
    {
        // Ensure chart belongs to this birth care center
        if ($chart->birth_care_id != $birthcare_id) {
            return response()->json(['message' => 'Patient chart not found'], 404);
        }

        try {
            $chart->delete();

            Log::info('Patient chart deleted', [
                'chart_id' => $chart->id,
                'patient_id' => $chart->patient_id,
                'birth_care_id' => $birthcare_id,
                'deleted_by' => Auth::id()
            ]);

            return response()->json([
                'message' => 'Patient chart deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to delete patient chart', [
                'error' => $e->getMessage(),
                'chart_id' => $chart->id,
                'birth_care_id' => $birthcare_id
            ]);

            return response()->json([
                'message' => 'Failed to delete patient chart',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get patient chart by patient ID
     */
    public function getByPatient($birthcare_id, $patient_id)
    {
        // Verify patient belongs to this birth care center
        $patient = Patient::where('id', $patient_id)
                         ->where('birth_care_id', $birthcare_id)
                         ->first();

        if (!$patient) {
            return response()->json([
                'message' => 'Patient not found or does not belong to this birth care center'
            ], 404);
        }

        $chart = PatientChart::forBirthcare($birthcare_id)
                           ->forPatient($patient_id)
                           ->with(['patient', 'admission'])
                           ->first();

        if (!$chart) {
            return response()->json([
                'message' => 'Patient chart not found',
                'patient' => $patient
            ], 404);
        }

        return response()->json($chart);
    }
}