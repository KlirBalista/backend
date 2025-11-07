<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\BirthCareRole;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BirthCareRoleController extends Controller
{
    /**
     * Display a listing of roles for a specific birthcare facility.
     *
     * @param int $birthcareId
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(int $birthcareId): JsonResponse
    {
        try {
            // Get all roles for this birthcare with their permissions
            $roles = BirthCareRole::where('birth_care_id', $birthcareId)
                ->with('permissions')
                ->get();
            
            // Format the response
            $formattedRoles = $roles->map(function ($role) {
                return [
                    'id' => $role->id,
                    'name' => $role->role_name,
                    'permissions' => $role->permissions->map(function ($permission) {
                        return [
                            'id' => $permission->id,
                            'name' => $permission->name
                        ];
                    })
                ];
            });
            
            return response()->json($formattedRoles);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve roles',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created role in storage.
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
                'name' => [
                    'required', 
                    'string', 
                    'max:255',
                    Rule::unique('birth_care_roles', 'role_name')
                        ->where('birth_care_id', $birthcareId)
                ],
                'permissions' => 'required|array',
                'permissions.*' => 'exists:permissions,id',
            ]);
            
            // Create the role with a transaction
            $role = DB::transaction(function () use ($validated, $birthcareId) {
                // Create the role
                $role = BirthCareRole::create([
                    'birth_care_id' => $birthcareId,
                    'role_name' => $validated['name'],
                ]);
                
                // Attach permissions
                $role->permissions()->attach($validated['permissions']);
                
                return $role;
            });
            
            // Load the permissions relationship
            $role->load('permissions');
            
            // Format the response
            $formattedRole = [
                'id' => $role->id,
                'name' => $role->role_name,
                'permissions' => $role->permissions->map(function ($permission) {
                    return [
                        'id' => $permission->id,
                        'name' => $permission->name
                    ];
                })
            ];
            
            return response()->json($formattedRole, 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create role',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified role.
     *
     * @param int $birthcareId
     * @param int $roleId
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(int $birthcareId, int $roleId): JsonResponse
    {
        try {
            // Get the role with its permissions
            $role = BirthCareRole::where('birth_care_id', $birthcareId)
                ->where('id', $roleId)
                ->with('permissions')
                ->firstOrFail();
            
            // Format the response
            $formattedRole = [
                'id' => $role->id,
                'name' => $role->role_name,
                'permissions' => $role->permissions->map(function ($permission) {
                    return [
                        'id' => $permission->id,
                        'name' => $permission->name
                    ];
                })
            ];
            
            return response()->json($formattedRole);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Role not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update the specified role in storage.
     *
     * @param Request $request
     * @param int $birthcareId
     * @param int $roleId
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, int $birthcareId, int $roleId): JsonResponse
    {
        try {
            // Find the role
            $role = BirthCareRole::where('birth_care_id', $birthcareId)
                ->where('id', $roleId)
                ->firstOrFail();
            
            // Validate the request
            $validated = $request->validate([
                'name' => [
                    'required', 
                    'string', 
                    'max:255',
                    Rule::unique('birth_care_roles', 'role_name')
                        ->where('birth_care_id', $birthcareId)
                        ->ignore($role->id)
                ],
                'permissions' => 'required|array',
                'permissions.*' => 'exists:permissions,id',
            ]);
            
            // Update the role with a transaction
            DB::transaction(function () use ($role, $validated) {
                // Update the role name
                $role->update([
                    'role_name' => $validated['name']
                ]);
                
                // Sync permissions
                $role->permissions()->sync($validated['permissions']);
            });
            
            // Reload the role with permissions
            $role->load('permissions');
            
            // Format the response
            $formattedRole = [
                'id' => $role->id,
                'name' => $role->role_name,
                'permissions' => $role->permissions->map(function ($permission) {
                    return [
                        'id' => $permission->id,
                        'name' => $permission->name
                    ];
                })
            ];
            
            return response()->json($formattedRole);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update role',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified role from storage.
     *
     * @param int $birthcareId
     * @param int $roleId
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(int $birthcareId, int $roleId): JsonResponse
    {
        try {
            // Find the role
            $role = BirthCareRole::where('birth_care_id', $birthcareId)
                ->where('id', $roleId)
                ->firstOrFail();
            
            // Delete the role with a transaction
            DB::transaction(function () use ($role) {
                // Detach all permissions first
                $role->permissions()->detach();
                
                // Delete the role
                $role->delete();
            });
            
            return response()->json([
                'message' => 'Role deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete role',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

