<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Patient;
use App\Models\PrenatalVisit;

class PublicApiController extends Controller
{
    /**
     * Search patients across all facilities
     */
    public function searchPatients(Request $request)
    {
        $query = $request->get('query', '');
        
        if (empty($query)) {
            return response()->json([]);
        }

        // Search patients by name or contact
        $patients = Patient::where(function ($q) use ($query) {
            $q->where('first_name', 'like', "%{$query}%")
              ->orWhere('last_name', 'like', "%{$query}%")
              ->orWhere('contact_number', 'like', "%{$query}%");
        })
        ->with(['birthCare:id,name'])
        ->limit(20)
        ->get();

        // Format data for frontend
        $formattedPatients = $patients->map(function ($patient) {
            return [
                'id' => $patient->id,
                'name' => trim($patient->first_name . ' ' . $patient->middle_name . ' ' . $patient->last_name),
                'email' => 'Not provided', // No email field in schema
                'phone' => $patient->contact_number,
                'birth_date' => $patient->date_of_birth,
                'address' => $patient->address,
                'facility' => $patient->birthCare ? $patient->birthCare->name : 'Unknown Facility',
                'facility_id' => $patient->birth_care_id,
            ];
        });

        return response()->json($formattedPatients);
    }

    /**
     * Get consultation history for a specific patient from prenatal_forms table
     */
    public function getPatientConsultations($patientId)
    {
        $patient = Patient::findOrFail($patientId);
        
        // Get prenatal forms as consultation history
        $consultations = \App\Models\PrenatalForm::where('patient_id', $patientId)
            ->with(['patient.birthCare'])
            ->orderBy('form_date', 'desc')
            ->get();

        // Format consultation data for prenatal forms
        $formattedConsultations = $consultations->map(function ($form) {
            return [
                'id' => $form->id,
                'exam_date' => $form->form_date,
                'facility_name' => $form->patient->birthCare->name ?? 'Unknown Facility',
                'gestational_age' => $form->gestational_age ? $form->gestational_age . ' weeks' : 'N/A',
                'blood_pressure' => $form->blood_pressure ?? 'N/A',
                'weight' => $form->weight ? $form->weight . 'kg' : 'N/A',
                'notes' => $form->notes ?? 'N/A',
                'provider' => $form->examined_by ?? 'N/A',
                'created_at' => $form->created_at,
            ];
        });

        return response()->json([
            'patient' => [
                'id' => $patient->id,
                'name' => trim($patient->first_name . ' ' . $patient->middle_name . ' ' . $patient->last_name),
                'email' => 'Not provided',
                'phone' => $patient->contact_number,
            ],
            'consultations' => $formattedConsultations
        ]);
    }
}