<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\Bed;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RoomController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $birthCareId = $request->route('birthcare_Id');
            
            $rooms = Room::with(['beds'])
                ->where('birth_care_id', $birthCareId)
                ->orderBy('name')
                ->get()
                ->map(function ($room) {
                    return [
                        'id' => $room->id,
                        'name' => $room->name,
                        'price' => $room->price,
                        'bed_count' => $room->beds->count(),
                        'occupied_beds' => $room->occupied_beds_count,
                        'available_beds' => $room->available_beds_count,
                        'is_fully_occupied' => $room->is_fully_occupied,
                        'created_at' => $room->created_at,
                        'updated_at' => $room->updated_at,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $rooms
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch rooms',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $birthCareId = $request->route('birthcare_Id');
            
            $validated = $request->validate([
                'name' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('rooms')->where(function ($query) use ($birthCareId) {
                        return $query->where('birth_care_id', $birthCareId);
                    })
                ],
                'beds' => 'required|integer|min:1|max:20',
                'price' => 'nullable|numeric|min:0'
            ]);

            DB::beginTransaction();

            // Create the room
            $room = Room::create([
                'name' => $validated['name'],
                'price' => $validated['price'] ?? null,
                'birth_care_id' => $birthCareId,
            ]);

            // Create beds for the room
            $bedCount = (int) $validated['beds'];
            $beds = [];
            for ($i = 1; $i <= $bedCount; $i++) {
                $beds[] = [
                    'bed_no' => $i,
                    'room_id' => $room->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            
            Bed::insert($beds);

            DB::commit();

            // Return the created room with bed count
            $room->load('beds');
            $responseData = [
                'id' => $room->id,
                'name' => $room->name,
                'price' => $room->price,
                'bed_count' => $room->beds->count(),
                'occupied_beds' => 0,
                'available_beds' => $room->beds->count(),
                'is_fully_occupied' => false,
                'created_at' => $room->created_at,
                'updated_at' => $room->updated_at,
            ];

            return response()->json([
                'success' => true,
                'message' => 'Room created successfully',
                'data' => $responseData
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create room',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        try {
            $birthCareId = $request->route('birthcare_Id');
            
            $room = Room::with(['beds.currentPatientAdmission.patient'])
                ->where('id', $id)
                ->where('birth_care_id', $birthCareId)
                ->firstOrFail();

            $responseData = [
                'id' => $room->id,
                'name' => $room->name,
                'price' => $room->price,
                'bed_count' => $room->beds->count(),
                'occupied_beds' => $room->occupied_beds_count,
                'available_beds' => $room->available_beds_count,
                'is_fully_occupied' => $room->is_fully_occupied,
                'beds' => $room->beds->map(function ($bed) {
                    return [
                        'id' => $bed->id,
                        'bed_no' => $bed->bed_no,
                        'status' => $bed->status,
                        'current_patient' => $bed->current_patient ? [
                            'id' => $bed->current_patient->id,
                            'name' => $bed->current_patient->first_name . ' ' . $bed->current_patient->last_name,
                        ] : null,
                    ];
                }),
                'created_at' => $room->created_at,
                'updated_at' => $room->updated_at,
            ];

            return response()->json([
                'success' => true,
                'data' => $responseData
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Room not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch room',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $birthCareId = $request->route('birthcare_Id');
            
            $room = Room::where('id', $id)
                ->where('birth_care_id', $birthCareId)
                ->firstOrFail();

            $validated = $request->validate([
                'name' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('rooms')->where(function ($query) use ($birthCareId) {
                        return $query->where('birth_care_id', $birthCareId);
                    })->ignore($room->id)
                ],
                'beds' => 'required|integer|min:1|max:20',
                'price' => 'nullable|numeric|min:0'
            ]);

            DB::beginTransaction();

            // Update room name and price
            $room->update([
                'name' => $validated['name'],
                'price' => $validated['price'] ?? null
            ]);

            // Update beds count
            $newBedCount = (int) $validated['beds'];
            $currentBeds = $room->beds()->orderBy('bed_no')->get();
            $currentBedCount = $currentBeds->count();

            if ($newBedCount !== $currentBedCount) {
                if ($newBedCount < $currentBedCount) {
                    // Remove beds from the highest bed numbers
                    // But only remove beds that are not occupied
                    $bedsToRemove = $currentBeds->reverse()->take($currentBedCount - $newBedCount);
                    
                    foreach ($bedsToRemove as $bed) {
                        if (!$bed->is_occupied) {
                            $bed->delete();
                        }
                    }
                } else {
                    // Add new beds
                    $maxBedNo = $currentBeds->max('bed_no') ?? 0;
                    $bedsToAdd = [];
                    
                    for ($i = $maxBedNo + 1; $i <= $newBedCount; $i++) {
                        $bedsToAdd[] = [
                            'bed_no' => $i,
                            'room_id' => $room->id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                    
                    if (!empty($bedsToAdd)) {
                        Bed::insert($bedsToAdd);
                    }
                }
            }

            DB::commit();

            // Return updated room data
            $room->load('beds');
            $responseData = [
                'id' => $room->id,
                'name' => $room->name,
                'price' => $room->price,
                'bed_count' => $room->beds->count(),
                'occupied_beds' => $room->occupied_beds_count,
                'available_beds' => $room->available_beds_count,
                'is_fully_occupied' => $room->is_fully_occupied,
                'created_at' => $room->created_at,
                'updated_at' => $room->updated_at,
            ];

            return response()->json([
                'success' => true,
                'message' => 'Room updated successfully',
                'data' => $responseData
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Room not found'
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update room',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            $birthCareId = $request->route('birthcare_Id');
            
            $room = Room::where('id', $id)
                ->where('birth_care_id', $birthCareId)
                ->firstOrFail();

            // Check if any beds in the room are occupied
            $occupiedBeds = $room->beds()->whereHas('patientAdmissions', function ($query) {
                $query->whereIn('status', ['in-labor', 'delivered']);
            })->count();

            if ($occupiedBeds > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete room with occupied beds. Please discharge or transfer patients first.'
                ], 422);
            }

            DB::beginTransaction();
            
            // Delete all beds (this will cascade and handle any related records)
            $room->beds()->delete();
            
            // Delete the room
            $room->delete();
            
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Room deleted successfully'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Room not found'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete room',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get beds for a specific room.
     */
    public function getBeds(Request $request): JsonResponse
    {
        try {
            $birthCareId = $request->route('birthcare_Id');
            $roomId = $request->route('roomId');
            
            // Log incoming parameters and request details
            \Log::info("Request path: " . $request->path());
            \Log::info("Received roomId: $roomId, birthCareId: $birthCareId");
            \Log::info("Route parameters: ", $request->route()->parameters());

            $beds = Bed::where('room_id', $roomId)
                ->whereHas('room', function ($query) use ($birthCareId) {
                    $query->where('birth_care_id', $birthCareId);
                })
                ->with(['currentPatientAdmission.patient'])
                ->orderBy('bed_no')
                ->get()
                ->map(function ($bed) {
                    return [
                        'id' => $bed->id,
                        'bed_no' => $bed->bed_no,
                        'status' => $bed->status,
                        'is_occupied' => $bed->is_occupied,
                        'current_patient' => $bed->current_patient ? [
                            'id' => $bed->current_patient->id,
                            'name' => $bed->current_patient->first_name . ' ' . $bed->current_patient->last_name,
                        ] : null,
                    ];
                });

            if ($beds->isEmpty()) {
                \Log::warning("No beds found for room_id: $roomId, birth_care_id: $birthCareId");
                return response()->json([
                    'success' => false,
                    'message' => 'No beds found for the specified room',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $beds
            ]);
        } catch (\Exception $e) {
            \Log::error("Failed to fetch beds: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch beds',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
