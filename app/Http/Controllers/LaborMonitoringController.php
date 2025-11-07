<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LaborMonitoring;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class LaborMonitoringController extends Controller
{
    /**
     * Display a listing of labor monitoring entries.
     */
    public function index(Request $request, $birthcare_id): JsonResponse
    {
        $query = LaborMonitoring::with(['patient'])
            ->where('birth_care_id', $birthcare_id);

        // Filter by patient if specified
        if ($request->filled('patient_id')) {
            $query->where('patient_id', $request->get('patient_id'));
        }

        // Filter by date range if specified
        if ($request->filled('date_from')) {
            $query->where('monitoring_date', '>=', $request->get('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->where('monitoring_date', '<=', $request->get('date_to'));
        }

        $entries = $query->orderBy('monitoring_date', 'desc')
                        ->orderBy('monitoring_time', 'desc')
                        ->get()
                        ->map(function ($entry) {
                            // Format dates for consistent display
                            $entry->monitoring_date = $entry->monitoring_date ? $entry->monitoring_date->format('Y-m-d') : null;
                            return $entry;
                        });

        return response()->json([
            'data' => $entries
        ]);
    }

    /**
     * Store a newly created labor monitoring entry.
     */
    public function store(Request $request, $birthcare_id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'patient_id' => 'required|exists:patients,id',
            'monitoring_date' => 'required|date',
            'monitoring_time' => 'required',
            'temperature' => 'nullable|string|max:10',
            'pulse' => 'nullable|string|max:10',
            'respiration' => 'nullable|string|max:10',
            'blood_pressure' => 'nullable|string|max:20',
            'fht_location' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $entry = LaborMonitoring::create([
                'patient_id' => $request->patient_id,
                'birth_care_id' => $birthcare_id,
                'monitoring_date' => $request->monitoring_date,
                'monitoring_time' => $request->monitoring_time,
                'temperature' => $request->temperature,
                'pulse' => $request->pulse,
                'respiration' => $request->respiration,
                'blood_pressure' => $request->blood_pressure,
                'fht_location' => $request->fht_location,
                'notes' => $request->notes,
                'created_by' => auth()->id(),
            ]);

            // Format date for consistent response
            $entry->monitoring_date = $entry->monitoring_date ? $entry->monitoring_date->format('Y-m-d') : null;

            return response()->json([
                'message' => 'Labor monitoring entry created successfully',
                'data' => $entry->load('patient')
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create labor monitoring entry',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified labor monitoring entry.
     */
    public function show($birthcare_id, LaborMonitoring $entry): JsonResponse
    {
        $entry->load('patient');
        return response()->json([
            'data' => $entry
        ]);
    }

    /**
     * Update the specified labor monitoring entry.
     */
    public function update(Request $request, $birthcare_id, LaborMonitoring $entry): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'monitoring_date' => 'sometimes|required|date',
            'monitoring_time' => 'sometimes|required',
            'temperature' => 'nullable|string|max:10',
            'pulse' => 'nullable|string|max:10',
            'respiration' => 'nullable|string|max:10',
            'blood_pressure' => 'nullable|string|max:20',
            'fht_location' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $entry->update($request->only([
                'monitoring_date',
                'monitoring_time', 
                'temperature',
                'pulse',
                'respiration',
                'blood_pressure',
                'fht_location',
                'notes'
            ]));

            return response()->json([
                'message' => 'Labor monitoring entry updated successfully',
                'data' => $entry->load('patient')
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update labor monitoring entry',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified labor monitoring entry.
     */
    public function destroy($birthcare_id, LaborMonitoring $entry): JsonResponse
    {
        try {
            $entry->delete();
            
            return response()->json([
                'message' => 'Labor monitoring entry deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete labor monitoring entry',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}