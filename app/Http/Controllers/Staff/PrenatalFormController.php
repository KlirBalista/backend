<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\PrenatalForm;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PrenatalFormController extends Controller
{
    /**
     * Display a listing of prenatal forms for a birthcare facility.
     */
    public function index(Request $request, int $birthcareId): JsonResponse
    {
        try {
            $forms = PrenatalForm::whereHas('patient', function ($query) use ($birthcareId) {
                $query->where('birth_care_id', $birthcareId);
            })
            ->with('patient:id,first_name,middle_name,last_name,date_of_birth')
            ->orderBy('form_date', 'desc')
            ->paginate(10);

            return response()->json($forms);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve prenatal forms',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created prenatal form.
     */
    public function store(Request $request, int $birthcareId): JsonResponse
    {
        try {
            $request->validate([
                'patient_id' => 'required|exists:patients,id',
                'form_date' => 'required|date',
                'gestational_age' => 'nullable|string|max:50',
                'weight' => 'nullable|string|max:20',
                'blood_pressure' => 'nullable|string|max:20',
                'notes' => 'nullable|string',
                'next_appointment' => 'nullable|date',
                'examined_by' => 'nullable|string|max:255',
            ]);

            // Verify patient belongs to this birthcare facility
            $patient = Patient::where('id', $request->patient_id)
                ->where('birth_care_id', $birthcareId)
                ->first();

            if (!$patient) {
                return response()->json([
                    'message' => 'Patient not found or does not belong to this facility.'
                ], 404);
            }

            $form = PrenatalForm::create($request->all());
            $form->load('patient:id,first_name,middle_name,last_name,date_of_birth');

            return response()->json([
                'message' => 'Prenatal form created successfully!',
                'data' => $form
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create prenatal form',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified prenatal form.
     */
    public function show(int $birthcareId, int $formId): JsonResponse
    {
        try {
            $form = PrenatalForm::whereHas('patient', function ($query) use ($birthcareId) {
                $query->where('birth_care_id', $birthcareId);
            })
            ->with('patient')
            ->findOrFail($formId);

            return response()->json(['data' => $form]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Prenatal form not found'
            ], 404);
        }
    }

    /**
     * Update the specified prenatal form.
     */
    public function update(Request $request, int $birthcareId, int $formId): JsonResponse
    {
        try {
            $form = PrenatalForm::whereHas('patient', function ($query) use ($birthcareId) {
                $query->where('birth_care_id', $birthcareId);
            })->findOrFail($formId);

            $request->validate([
                'form_date' => 'sometimes|date',
                'gestational_age' => 'nullable|string|max:50',
                'weight' => 'nullable|string|max:20',
                'blood_pressure' => 'nullable|string|max:20',
                'notes' => 'nullable|string',
                'next_appointment' => 'nullable|date',
                'examined_by' => 'nullable|string|max:255',
            ]);

            $form->update($request->all());
            $form->load('patient:id,first_name,middle_name,last_name,date_of_birth');

            return response()->json([
                'message' => 'Prenatal form updated successfully!',
                'data' => $form
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update prenatal form'
            ], 500);
        }
    }

    /**
     * Remove the specified prenatal form.
     */
    public function destroy(int $birthcareId, int $formId): JsonResponse
    {
        try {
            $form = PrenatalForm::whereHas('patient', function ($query) use ($birthcareId) {
                $query->where('birth_care_id', $birthcareId);
            })->findOrFail($formId);

            $form->delete();

            return response()->json([
                'message' => 'Prenatal form deleted successfully!'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete prenatal form'
            ], 500);
        }
    }
}
