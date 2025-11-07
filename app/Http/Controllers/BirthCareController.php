<?php

namespace App\Http\Controllers;

use App\Models\BirthCare;
use Illuminate\Http\Request;

class BirthCareController extends Controller
{
    /**
     * Get birth care facility details by ID
     */
    public function show($id)
    {
        $birthcare = BirthCare::with('owner')->find($id);
        
        if (!$birthcare) {
            return response()->json([
                'message' => 'Birth care facility not found.'
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'data' => $birthcare
        ]);
    }
}