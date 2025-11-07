<?php

use Illuminate\Support\Facades\Route;
use App\Models\BirthCare;

Route::get('/debug/facilities', function () {
    $facilities = BirthCare::with('owner')->get();
    
    $result = [];
    foreach ($facilities as $facility) {
        $result[] = [
            'id' => $facility->id,
            'name' => $facility->name,
            'description' => $facility->description,
            'owner_address' => $facility->owner->address ?? 'NULL',
            'owner_contact' => $facility->owner->contact_number ?? 'NULL',
            'processed_data' => [
                'name' => strtoupper($facility->name),
                'address' => $facility->owner->address ?? 'N/A',
                'contact_number' => $facility->owner->contact_number ?? 'N/A',
                'description' => $facility->description ?? ''
            ]
        ];
    }
    
    return response()->json([
        'count' => count($result),
        'facilities' => $result
    ]);
});