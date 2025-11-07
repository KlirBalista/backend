<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\BirthCare;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * List all users with pagination and filtering
     */
    public function index(Request $request)
    {
        // Only allow admin access
        if ($request->user()->system_role_id !== 1) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            $query = User::with(['birthCare', 'birthCareStaff.birthCare']);

            // Apply search filter
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('firstname', 'like', "%{$search}%")
                      ->orWhere('lastname', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            // Apply role filter
            if ($request->has('role') && $request->role) {
                $query->where('system_role_id', $request->role);
            }

            // Apply status filter
            if ($request->has('status')) {
                if ($request->status === 'active') {
                    $query->where('is_active', true);
                } elseif ($request->status === 'inactive') {
                    $query->where('is_active', false);
                }
            }

            // Apply sorting
            $sortField = $request->get('sort_field', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');
            
            $allowedSortFields = ['firstname', 'lastname', 'email', 'created_at', 'system_role_id', 'is_active'];
            if (in_array($sortField, $allowedSortFields)) {
                $query->orderBy($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');
            } else {
                $query->orderBy('created_at', 'desc');
            }

            // Get paginated results
            $perPage = $request->get('per_page', 10);
            $users = $query->paginate($perPage);

            // Transform the data for frontend
            $transformedUsers = $users->getCollection()->map(function ($user) {
                return [
                    'id' => $user->id,
                    'firstname' => $user->firstname,
                    'lastname' => $user->lastname,
                    'email' => $user->email,
                    'system_role_id' => $user->system_role_id,
                    'is_active' => $user->is_active,
                    'contact_number' => $user->contact_number,
                    'address' => $user->address,
                    'date_of_birth' => $user->date_of_birth,
                    'gender' => $user->gender,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                    'last_login' => $user->last_login,
                    'birth_care' => $user->birthCare ? [
                        'id' => $user->birthCare->id,
                        'name' => $user->birthCare->name
                    ] : null,
                    'birthCareStaff' => $user->birthCareStaff ? [
                        'birthCare' => $user->birthCareStaff->birthCare ? [
                            'id' => $user->birthCareStaff->birthCare->id,
                            'name' => $user->birthCareStaff->birthCare->name
                        ] : null
                    ] : null,
                ];
            });

            return response()->json([
                'users' => $transformedUsers,
                'pagination' => [
                    'total' => $users->total(),
                    'per_page' => $users->perPage(),
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'from' => $users->firstItem(),
                    'to' => $users->lastItem(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch users',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific user
     */
    public function show(Request $request, $id)
    {
        // Only allow admin access
        if ($request->user()->system_role_id !== 1) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            $user = User::with(['birthCare', 'birthCareStaff.birthCare'])->findOrFail($id);

            return response()->json([
                'id' => $user->id,
                'firstname' => $user->firstname,
                'lastname' => $user->lastname,
                'email' => $user->email,
                'system_role_id' => $user->system_role_id,
                'is_active' => $user->is_active,
                'contact_number' => $user->contact_number,
                'address' => $user->address,
                'date_of_birth' => $user->date_of_birth,
                'gender' => $user->gender,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
                'last_login' => $user->last_login,
                'birth_care' => $user->birthCare ? [
                    'id' => $user->birthCare->id,
                    'name' => $user->birthCare->name
                ] : null,
                'birthCareStaff' => $user->birthCareStaff ? [
                    'birthCare' => $user->birthCareStaff->birthCare ? [
                        'id' => $user->birthCareStaff->birthCare->id,
                        'name' => $user->birthCareStaff->birthCare->name
                    ] : null
                ] : null,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'User not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Create a new user
     */
    public function store(Request $request)
    {
        // Only allow admin access
        if ($request->user()->system_role_id !== 1) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'firstname' => ['required', 'string', 'max:255'],
            'lastname' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8'],
            'system_role_id' => ['required', 'integer', 'in:1,2,3'],
            'is_active' => ['boolean'],
            'contact_number' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:500'],
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['nullable', 'string', 'in:Male,Female,Other'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = User::create([
                'firstname' => $request->firstname,
                'lastname' => $request->lastname,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'system_role_id' => $request->system_role_id,
                'is_active' => $request->has('is_active') ? $request->is_active : true,
                'contact_number' => $request->contact_number ?? $request->phone,
                'address' => $request->address,
                'date_of_birth' => $request->date_of_birth,
                'gender' => $request->gender,
            ]);

            // Load relationships for response
            $user->load(['birthCare', 'birthCareStaff.birthCare']);

            return response()->json([
                'message' => 'User created successfully',
                'user' => [
                    'id' => $user->id,
                    'firstname' => $user->firstname,
                    'lastname' => $user->lastname,
                    'email' => $user->email,
                    'system_role_id' => $user->system_role_id,
                    'is_active' => $user->is_active,
                    'contact_number' => $user->contact_number,
                    'address' => $user->address,
                    'date_of_birth' => $user->date_of_birth,
                    'gender' => $user->gender,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                    'last_login' => $user->last_login,
                    'birth_care' => $user->birthCare ? [
                        'id' => $user->birthCare->id,
                        'name' => $user->birthCare->name
                    ] : null,
                    'birthCareStaff' => $user->birthCareStaff ? [
                        'birthCare' => $user->birthCareStaff->birthCare ? [
                            'id' => $user->birthCareStaff->birthCare->id,
                            'name' => $user->birthCareStaff->birthCare->name
                        ] : null
                    ] : null,
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update an existing user
     */
    public function update(Request $request, $id)
    {
        // Only allow admin access
        if ($request->user()->system_role_id !== 1) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            $user = User::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'firstname' => ['required', 'string', 'max:255'],
                'lastname' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
                'password' => ['nullable', 'string', 'min:8'],
                'system_role_id' => ['required', 'integer', 'in:1,2,3'],
                'is_active' => ['boolean'],
                'contact_number' => ['nullable', 'string', 'max:20'],
                'address' => ['nullable', 'string', 'max:500'],
                'date_of_birth' => ['nullable', 'date'],
                'gender' => ['nullable', 'string', 'in:Male,Female,Other'],
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Prevent admin from deactivating themselves
            if ($user->id === $request->user()->id && $request->has('is_active') && !$request->is_active) {
                return response()->json([
                    'message' => 'You cannot deactivate your own account'
                ], 422);
            }

            // Prevent changing system admin role
            if ($user->system_role_id === 1 && $request->system_role_id !== 1 && $user->id === $request->user()->id) {
                return response()->json([
                    'message' => 'You cannot change your own admin role'
                ], 422);
            }

            $updateData = [
                'firstname' => $request->firstname,
                'lastname' => $request->lastname,
                'email' => $request->email,
                'system_role_id' => $request->system_role_id,
                'is_active' => $request->has('is_active') ? $request->is_active : $user->is_active,
                'contact_number' => $request->contact_number ?? $request->phone,
                'address' => $request->address,
                'date_of_birth' => $request->date_of_birth,
                'gender' => $request->gender,
            ];

            // Only update password if provided
            if ($request->password) {
                $updateData['password'] = Hash::make($request->password);
            }

            $user->update($updateData);

            // Load relationships for response
            $user->load(['birthCare', 'birthCareStaff.birthCare']);

            return response()->json([
                'message' => 'User updated successfully',
                'user' => [
                    'id' => $user->id,
                    'firstname' => $user->firstname,
                    'lastname' => $user->lastname,
                    'email' => $user->email,
                    'system_role_id' => $user->system_role_id,
                    'is_active' => $user->is_active,
                    'contact_number' => $user->contact_number,
                    'address' => $user->address,
                    'date_of_birth' => $user->date_of_birth,
                    'gender' => $user->gender,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                    'last_login' => $user->last_login,
                    'birth_care' => $user->birthCare ? [
                        'id' => $user->birthCare->id,
                        'name' => $user->birthCare->name
                    ] : null,
                    'birthCareStaff' => $user->birthCareStaff ? [
                        'birthCare' => $user->birthCareStaff->birthCare ? [
                            'id' => $user->birthCareStaff->birthCare->id,
                            'name' => $user->birthCareStaff->birthCare->name
                        ] : null
                    ] : null,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a user
     */
    public function destroy(Request $request, $id)
    {
        // Only allow admin access
        if ($request->user()->system_role_id !== 1) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            $user = User::findOrFail($id);

            // Prevent admin from deleting themselves
            if ($user->id === $request->user()->id) {
                return response()->json([
                    'message' => 'You cannot delete your own account'
                ], 422);
            }

            // Prevent deleting other system administrators
            if ($user->system_role_id === 1) {
                return response()->json([
                    'message' => 'Cannot delete system administrator accounts'
                ], 422);
            }

            $user->delete();

            return response()->json([
                'message' => 'User deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle user status (activate/deactivate)
     */
    public function toggleStatus(Request $request, $id)
    {
        // Only allow admin access
        if ($request->user()->system_role_id !== 1) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            $user = User::findOrFail($id);

            // Prevent admin from deactivating themselves
            if ($user->id === $request->user()->id && $user->is_active) {
                return response()->json([
                    'message' => 'You cannot deactivate your own account'
                ], 422);
            }

            $user->is_active = !$user->is_active;
            $user->save();

            return response()->json([
                'message' => $user->is_active ? 'User activated successfully' : 'User deactivated successfully',
                'user' => [
                    'id' => $user->id,
                    'is_active' => $user->is_active,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to toggle user status',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}