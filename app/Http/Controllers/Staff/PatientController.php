<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\PrenatalVisit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class PatientController extends Controller
{
    /**
     * Display a listing of patients for the birth care center
     */
    public function index(Request $request, $birthcare_id)
    {
        $query = Patient::where('birth_care_id', $birthcare_id)
                       ->with(['prenatalVisits' => function($query) {
                           $query->where('scheduled_date', '>=', now())
                                 ->orderBy('scheduled_date', 'asc')
                                 ->limit(1);
                       }]);

        // Search functionality
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('middle_name', 'like', "%{$search}%")
                  ->orWhere('contact_number', 'like', "%{$search}%")
                  ->orWhereRaw("(first_name || ' ' || COALESCE(middle_name, '') || ' ' || last_name) LIKE ?", ["%{$search}%"]);
            });
        }

        // Status filter
        if ($request->has('status') && !empty($request->status)) {
            $query->where('status', $request->status);
        }

        // Check if pagination is requested (default is paginated)
        if ($request->has('all') && $request->all === 'true') {
            // Return all patients without pagination (for dropdowns, selects, etc.)
            $patients = $query->orderBy('created_at', 'asc')
                             ->get();
            
            return response()->json([
                'data' => $patients,
                'total' => $patients->count()
            ]);
        }
        
        // Default paginated response
        $patients = $query->orderBy('created_at', 'asc')
                         ->paginate(5);

        return response()->json($patients);
    }

    /**
     * Store a newly created patient
     */
    public function store(Request $request, $birthcare_id)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'date_of_birth' => 'required|date',
            'age' => 'required|integer|min:1|max:100',
            'civil_status' => 'required|in:Single,Married,Widowed,Separated,Divorced',
            'address' => 'required|string|max:500',
            'contact_number' => 'nullable|string|max:20',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_number' => 'nullable|string|max:20',
            'lmp' => 'nullable|date',
            'edc' => 'nullable|date',
            'gravida' => 'nullable|integer|min:0',
            'para' => 'nullable|integer|min:0',
            'philhealth_number' => 'nullable|string|max:50',
            'philhealth_category' => 'nullable|in:None,Direct,Indirect',
            'facility_name' => 'nullable|string|max:255',
            'principal_philhealth_number' => 'nullable|string|max:50',
            'principal_name' => 'nullable|string|max:255',
            'relationship_to_principal' => 'nullable|string|max:100',
            'principal_date_of_birth' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->all();
        $data['birth_care_id'] = $birthcare_id;

        $patient = Patient::create($data);

        // Automatically schedule prenatal visits if LMP is provided
        if ($patient->lmp) {
            $patient->schedulePrenatalVisits();
        }

        return response()->json([
            'message' => 'Patient created successfully',
            'patient' => $patient->load('prenatalVisits')
        ], 201);
    }

    /**
     * Display the specified patient
     */
    public function show($birthcare_id, Patient $patient)
    {
        // Ensure patient belongs to this birth care center
        if ($patient->birth_care_id != $birthcare_id) {
            return response()->json(['message' => 'Patient not found'], 404);
        }

        return response()->json($patient->load('prenatalVisits'));
    }

    /**
     * Update the specified patient
     */
    public function update(Request $request, $birthcare_id, Patient $patient)
    {
        // Ensure patient belongs to this birth care center
        if ($patient->birth_care_id != $birthcare_id) {
            return response()->json(['message' => 'Patient not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'date_of_birth' => 'required|date',
            'age' => 'required|integer|min:1|max:100',
            'civil_status' => 'required|in:Single,Married,Widowed,Separated,Divorced',
            'address' => 'required|string|max:500',
            'contact_number' => 'required|string|max:20',
            'emergency_contact_name' => 'required|string|max:255',
            'emergency_contact_number' => 'required|string|max:20',
            'status' => 'nullable|in:Active,Completed,Transferred,Inactive',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $patient->update($request->all());

        return response()->json([
            'message' => 'Patient updated successfully',
            'patient' => $patient->fresh()->load('prenatalVisits')
        ]);
    }

    /**
     * Remove the specified patient
     */
    public function destroy($birthcare_id, Patient $patient)
    {
        // Ensure patient belongs to this birth care center
        if ($patient->birth_care_id != $birthcare_id) {
            return response()->json(['message' => 'Patient not found'], 404);
        }

        $patient->delete();

        return response()->json([
            'message' => 'Patient deleted successfully'
        ]);
    }

    /**
     * Get prenatal visits calendar data
     */
    public function getCalendarData(Request $request, $birthcare_id)
    {
        // Parse the date parameters and ensure they're in the correct format
        $startDate = $request->get('start', Carbon::now()->startOfMonth()->toDateString());
        $endDate = $request->get('end', Carbon::now()->endOfMonth()->toDateString());
        
        // Convert to Carbon instances to ensure proper date handling
        $startCarbon = Carbon::parse($startDate)->startOfDay();
        $endCarbon = Carbon::parse($endDate)->endOfDay();

        $visits = PrenatalVisit::whereHas('patient', function($query) use ($birthcare_id) {
                                    $query->where('birth_care_id', $birthcare_id);
                                })
                               ->with(['patient:id,first_name,middle_name,last_name,contact_number'])
                               ->whereBetween('scheduled_date', [$startCarbon->toDateString(), $endCarbon->toDateString()])
                               ->orderBy('scheduled_date')
                               ->get();

        return response()->json($visits);
    }

    /**
     * Get today's scheduled visits
     */
    public function getTodaysVisits($birthcare_id)
    {
        // Get today's date in the application timezone
        $today = Carbon::today();
        
        $todaysVisits = PrenatalVisit::whereHas('patient', function($query) use ($birthcare_id) {
                                          $query->where('birth_care_id', $birthcare_id);
                                      })
                                     ->with(['patient:id,first_name,middle_name,last_name,contact_number,address'])
                                     ->whereDate('scheduled_date', $today->toDateString())
                                     ->orderBy('scheduled_date')
                                     ->get();

        return response()->json($todaysVisits);
    }
}
