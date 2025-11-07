<?php

namespace App\Http\Controllers;

use App\Models\PatientCharge;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    /**
     * Display a listing of the patient charges.
     */
    public function index($birthcare_id)
    {
        try {
            $services = PatientCharge::where('birthcare_id', $birthcare_id)
                ->where('is_active', true)
                ->orderBy('service_name')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $services
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch patient charges',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created patient charge.
     */
    public function store(Request $request, $birthcare_id)
    {
        try {
            $validated = $request->validate([
                'service_name' => 'required|string|max:255',
                'category' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'price' => 'required|numeric|min:0',
            ]);

            $service = PatientCharge::create([
                'birthcare_id' => $birthcare_id,
                'service_name' => $validated['service_name'],
                'category' => $validated['category'],
                'description' => $validated['description'],
                'price' => $validated['price'],
                'is_active' => true
            ]);

            return response()->json([
                'success' => true,
                'data' => $service,
                'message' => 'Patient charge created successfully'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create patient charge',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified patient charge.
     */
    public function show($birthcare_id, $charge)
    {
        try {
            $service = PatientCharge::where('birthcare_id', $birthcare_id)
                ->findOrFail($charge);

            return response()->json([
                'success' => true,
                'data' => $service
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Patient charge not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update the specified patient charge.
     */
    public function update(Request $request, $birthcare_id, $charge)
    {
        try {
            $validated = $request->validate([
                'service_name' => 'required|string|max:255',
                'category' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'price' => 'required|numeric|min:0',
                'is_active' => 'boolean'
            ]);

            $service = PatientCharge::where('birthcare_id', $birthcare_id)
                ->findOrFail($charge);
                
            $service->update($validated);

            return response()->json([
                'success' => true,
                'data' => $service,
                'message' => 'Patient charge updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update patient charge',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified patient charge.
     */
    public function destroy($birthcare_id, $charge)
    {
        try {
            $service = PatientCharge::where('birthcare_id', $birthcare_id)
                ->findOrFail($charge);
                
            $service->delete();

            return response()->json([
                'success' => true,
                'message' => 'Patient charge deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete patient charge',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
