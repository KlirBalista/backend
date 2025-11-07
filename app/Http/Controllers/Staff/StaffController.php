<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\BirthCare;
use App\Models\BirthCareRole;
use App\Models\BirthCareStaff;
use App\Models\User;
use App\Models\UserBirthRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class StaffController extends Controller
{
    /**
     * Display a listing of staff members for a specific birthcare facility.
     *
     * @param int $birthcareId
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(int $birthcareId): JsonResponse
    {
        try {
            // Get all staff for this birthcare with their user information and roles
            $staffMembers = BirthCareStaff::where('birth_care_id', $birthcareId)
                ->with([
                    'user',
                    'user.birthCareRoles' => function ($query) use ($birthcareId) {
                        $query->where('birth_care_id', $birthcareId)->with('role');
                    }
                ])
                ->get();

            // Format the response
            $formattedStaff = $staffMembers->map(function ($staffMember) use ($birthcareId) {
                $user = $staffMember->user;
                $roleAssignment = $user->birthCareRoles->first();
                
                return [
                    'id' => $staffMember->id,
                    'user_id' => $user->id,
                    'user' => [
                        'firstname' => $user->firstname,
                        'lastname' => $user->lastname,
                    ],
                    'name' => $user->firstname . ' ' . $user->lastname,
                    'email' => $user->email,
                    'contact_number' => $user->contact_number,
                    'role' => $roleAssignment ? [
                        'id' => $roleAssignment->role->id,
                        'role_name' => $roleAssignment->role->role_name,
                        'name' => $roleAssignment->role->role_name
                    ] : null,
                    'role_name' => $roleAssignment ? $roleAssignment->role->role_name : null
                ];
            });

            return response()->json($formattedStaff);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve staff members',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created staff member.
     *
     * @param Request $request
     * @param int $birthcareId
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request, int $birthcareId): JsonResponse
    {
        try {
            // Validate the request
            $validated = $request->validate([
                'firstname' => 'required|string|max:255',
                'lastname' => 'required|string|max:255',
                'middlename' => 'nullable|string|max:255',
                'email' => 'required|email|max:255',
                'contact_number' => 'required|string|max:20',
                'address' => 'nullable|string',
                'role_id' => 'required|exists:birth_care_roles,id',
                'password' => 'sometimes|string|min:8',
            ]);

            // Ensure the birthcare exists
            $birthcare = BirthCare::findOrFail($birthcareId);
            
            // Ensure the role belongs to this birthcare
            $role = BirthCareRole::where('id', $validated['role_id'])
                ->where('birth_care_id', $birthcareId)
                ->firstOrFail();

            // Create or find the user and assign staff role in a transaction
            $result = DB::transaction(function () use ($validated, $birthcareId, $role) {
                // Check if user already exists
                $existingUser = User::where('email', $validated['email'])->first();
                
                if ($existingUser) {
                    // Check if the user is already a staff member at another birthcare
                    $existingStaff = BirthCareStaff::where('user_id', $existingUser->id)->first();
                    if ($existingStaff) {
                        throw new \Exception('User is already a staff member at another facility');
                    }
                    
                    $user = $existingUser;
                } else {
                    // Create a new user
                    $userData = array_filter([
                        'firstname' => $validated['firstname'],
                        'lastname' => $validated['lastname'],
                        'middlename' => $validated['middlename'] ?? null,
                        'email' => $validated['email'],
                        'contact_number' => $validated['contact_number'],
                        'address' => $validated['address'] ?? null,
                        'password' => isset($validated['password']) ? Hash::make($validated['password']) : Hash::make('password123'), // Default password
                        'status' => 'active',
                        'system_role_id' => 3, // Assuming 3 is the staff role in system_roles table
                        'email_verified_at' => Carbon::now(), // Set email as verified immediately
                    ]);
                    
                    $user = User::create($userData);
                }
                
                // Create staff record
                $staff = BirthCareStaff::create([
                    'user_id' => $user->id,
                    'birth_care_id' => $birthcareId
                ]);
                
                // Assign role
                UserBirthRole::create([
                    'user_id' => $user->id,
                    'birth_care_id' => $birthcareId,
                    'role_id' => $role->id
                ]);
                
                return [
                    'staff' => $staff,
                    'user' => $user,
                    'role' => $role
                ];
            });
            
            // Format the response
            $formattedResponse = [
                'id' => $result['staff']->id,
                'user_id' => $result['user']->id,
                'name' => $result['user']->firstname . ' ' . $result['user']->lastname,
                'email' => $result['user']->email,
                'contact_number' => $result['user']->contact_number,
                'role' => [
                    'id' => $result['role']->id,
                    'name' => $result['role']->role_name
                ],
                'email_verified' => !is_null($result['user']->email_verified_at)
            ];
            
            return response()->json([
                'message' => 'Staff member created successfully.',
                'data' => $formattedResponse
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create staff member',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified staff member.
     *
     * @param int $birthcareId
     * @param int $staffId
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(int $birthcareId, int $staffId): JsonResponse
    {
        try {
            // Get the staff member with user information and role
            $staff = BirthCareStaff::where('birth_care_id', $birthcareId)
                ->where('id', $staffId)
                ->with([
                    'user',
                    'user.birthCareRoles' => function ($query) use ($birthcareId) {
                        $query->where('birth_care_id', $birthcareId)->with('role');
                    }
                ])
                ->firstOrFail();
            
            $user = $staff->user;
            $roleAssignment = $user->birthCareRoles->first();
            
            // Format the response
            $formattedStaff = [
                'id' => $staff->id,
                'user_id' => $user->id,
                'firstname' => $user->firstname,
                'lastname' => $user->lastname,
                'middlename' => $user->middlename,
                'email' => $user->email,
                'contact_number' => $user->contact_number,
                'address' => $user->address,
                'role' => $roleAssignment ? [
                    'id' => $roleAssignment->role->id,
                    'name' => $roleAssignment->role->role_name
                ] : null
            ];
            
            return response()->json($formattedStaff);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Staff member not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update the specified staff member.
     *
     * @param Request $request
     * @param int $birthcareId
     * @param int $staffId
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, int $birthcareId, int $staffId): JsonResponse
    {
        try {
            // Validate the request
            $validated = $request->validate([
                'firstname' => 'sometimes|required|string|max:255',
                'lastname' => 'sometimes|required|string|max:255',
                'middlename' => 'nullable|string|max:255',
                'email' => 'sometimes|required|email|max:255',
                'contact_number' => 'sometimes|required|string|max:20',
                'address' => 'nullable|string',
                'role_id' => 'sometimes|required|exists:birth_care_roles,id',
                'password' => 'sometimes|nullable|string|min:8',
            ]);
            
            // Get the staff member
            $staff = BirthCareStaff::where('birth_care_id', $birthcareId)
                ->where('id', $staffId)
                ->firstOrFail();
            
            // Get the user
            $user = User::findOrFail($staff->user_id);
            
            // Ensure the role belongs to this birthcare if it's being updated
            if (isset($validated['role_id'])) {
                $role = BirthCareRole::where('id', $validated['role_id'])
                    ->where('birth_care_id', $birthcareId)
                    ->firstOrFail();
            }
            
            // Update the user and role in a transaction
            $result = DB::transaction(function () use ($validated, $user, $birthcareId, $staff) {
                // Update user information
                $userData = array_filter([
                    'firstname' => $validated['firstname'] ?? null,
                    'lastname' => $validated['lastname'] ?? null,
                    'middlename' => $validated['middlename'] ?? null,
                    'email' => $validated['email'] ?? null,
                    'contact_number' => $validated['contact_number'] ?? null,
                    'address' => $validated['address'] ?? null,
                ], function ($value) {
                    return $value !== null;
                });
                
                if (!empty($userData)) {
                    $user->update($userData);
                }
                
                // Update password if provided
                if (isset($validated['password']) && $validated['password']) {
                    $user->update([
                        'password' => Hash::make($validated['password'])
                    ]);
                }
                
                // Update role if provided
                if (isset($validated['role_id'])) {
                    $roleAssignment = UserBirthRole::where('user_id', $user->id)
                        ->where('birth_care_id', $birthcareId)
                        ->first();
                    
                    if ($roleAssignment) {
                        $roleAssignment->update([
                            'role_id' => $validated['role_id']
                        ]);
                    } else {
                        UserBirthRole::create([
                            'user_id' => $user->id,
                            'birth_care_id' => $birthcareId,
                            'role_id' => $validated['role_id']
                        ]);
                    }
                    
                    // Reload the role relationship
                    $role = BirthCareRole::find($validated['role_id']);
                } else {
                    $roleAssignment = UserBirthRole::where('user_id', $user->id)
                        ->where('birth_care_id', $birthcareId)
                        ->with('role')
                        ->first();
                    
                    $role = $roleAssignment ? $roleAssignment->role : null;
                }
                
                return [
                    'staff' => $staff,
                    'user' => $user->fresh(),
                    'role' => $role
                ];
            });
            
            // Format the response
            $formattedResponse = [
                'id' => $result['staff']->id,
                'user_id' => $result['user']->id,
                'firstname' => $result['user']->firstname,
                'lastname' => $result['user']->lastname,
                'middlename' => $result['user']->middlename,
                'email' => $result['user']->email,
                'contact_number' => $result['user']->contact_number,
                'address' => $result['user']->address,
                'role' => $result['role'] ? [
                    'id' => $result['role']->id,
                    'name' => $result['role']->role_name
                ] : null
            ];
            
            return response()->json($formattedResponse);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update staff member',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified staff member.
     *
     * @param int $birthcareId
     * @param int $staffId
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(int $birthcareId, int $staffId): JsonResponse
    {
        try {
            // Get the staff member
            $staff = BirthCareStaff::where('birth_care_id', $birthcareId)
                ->where('id', $staffId)
                ->firstOrFail();
            
            // Remove the staff record and role assignment in a transaction
            DB::transaction(function () use ($staff, $birthcareId) {
                // Remove role assignment
                UserBirthRole::where('user_id', $staff->user_id)
                    ->where('birth_care_id', $birthcareId)
                    ->delete();
                
                // Remove staff record
                $staff->delete();
                
                // Note: We don't delete the user record to preserve history
                // The user can be assigned to another birthcare in the future
            });
            
            return response()->json([
                'message' => 'Staff member removed successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to remove staff member',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

