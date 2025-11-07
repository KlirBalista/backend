<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateBirthcareStatusRequest;
use App\Models\BirthCare;
use App\Models\BirthCareDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class BirthcareApplicationController extends Controller
{
    /**
     * List all birthcare applications with pagination, sorting, and filtering
     */
    public function index(Request $request)
    {
        // Only allow admin access
        if ($request->user()->system_role_id !== 1) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Get query parameters for pagination, sorting, and filtering
        $page = $request->query('page', 1);
        $perPage = $request->query('perPage', 10);
        $sortField = $request->query('sortField', 'created_at');
        $sortDirection = $request->query('sortDirection', 'desc');
        $status = $request->query('status', '');
        $search = $request->query('search', '');

        // Build query
        $query = BirthCare::with(['owner', 'documents'])
            ->when($status, function ($query) use ($status) {
                return $query->where('status', $status);
            })
            ->when($search, function ($query) use ($search) {
                return $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhereHas('owner', function ($userQuery) use ($search) {
                          $userQuery->where('firstname', 'like', "%{$search}%")
                                  ->orWhere('lastname', 'like', "%{$search}%")
                                  ->orWhere('email', 'like', "%{$search}%");
                      });
                });
            });

        // Apply sorting
        if (in_array($sortField, ['name', 'created_at', 'status'])) {
            $query->orderBy($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');
        } else {
            // Default sort
            $query->orderBy('created_at', 'desc');
        }

        // Get paginated results
        $applications = $query->paginate($perPage, ['*'], 'page', $page);

        // Process applications to include document URLs
        foreach ($applications as $application) {
            // Add user property for frontend compatibility (since frontend expects 'user' not 'owner')
            $application->user = $application->owner;
            $application->user->name = $application->owner->firstname . ' ' . $application->owner->lastname;
            
            // Add document URLs for frontend
            foreach ($application->documents as $document) {
                $document->url = method_exists($document, 'getFileUrl') ? $document->getFileUrl() : asset('storage/' . $document->document_path);
            }
        }

        // Return formatted response
        return response()->json([
            'applications' => $applications->items(),
            'total' => $applications->total(),
            'perPage' => $applications->perPage(),
            'currentPage' => $applications->currentPage(),
            'totalPages' => $applications->lastPage(),
        ]);
    }

    /**
     * Approve a birthcare application
     */
    public function approve(Request $request, $id)
    {
        // Only allow admin access
        if ($request->user()->system_role_id !== 1) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $birthcare = BirthCare::findOrFail($id);

        // Check if already processed
        if ($birthcare->status !== 'pending') {
            return response()->json([
                'message' => 'This application has already been processed.'
            ], 422);
        }

        // Update status
        $birthcare->status = 'approved';
        $birthcare->save();

        return response()->json([
            'message' => 'Application approved successfully.',
            'birthcare' => $birthcare
        ]);
    }

    /**
     * Reject a birthcare application
     */
    public function reject(UpdateBirthcareStatusRequest $request, $id)
    {
        $birthcare = BirthCare::findOrFail($id);

        // Check if already processed
        if ($birthcare->status !== 'pending') {
            return response()->json([
                'message' => 'This application has already been processed.'
            ], 422);
        }

        // Update status and store rejection reason
        $birthcare->status = 'rejected';
        $birthcare->rejection_reason = $request->reason;
        $birthcare->save();

        return response()->json([
            'message' => 'Application rejected successfully.',
            'birthcare' => $birthcare
        ]);
    }

    /**
     * Get a specific birthcare application details
     */
    public function show(Request $request, $id)
    {
        // Only allow admin access
        if ($request->user()->system_role_id !== 1) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $birthcare = BirthCare::with(['owner', 'documents'])->findOrFail($id);

        // Add document URLs for frontend
        foreach ($birthcare->documents as $document) {
            $document->url = method_exists($document, 'getFileUrl') ? $document->getFileUrl() : asset('storage/' . $document->document_path);
        }

        return response()->json($birthcare);
    }

    /**
     * Download a specific document
     */
    public function downloadDocument(Request $request, $id)
    {
        // Only allow admin access
        if ($request->user()->system_role_id !== 1) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $document = BirthCareDocument::findOrFail($id);
        
        // Try Supabase first, then fall back to public storage
        if (Storage::disk('supabase_birthcare')->exists($document->document_path)) {
            return Storage::disk('supabase_birthcare')->download(
                $document->document_path,
                $document->document_type . '_' . $document->birthCare->name . '.' . pathinfo($document->document_path, PATHINFO_EXTENSION)
            );
        } elseif (Storage::disk('public')->exists($document->document_path)) {
            return Storage::disk('public')->download(
                $document->document_path,
                $document->document_type . '_' . $document->birthCare->name . '.' . pathinfo($document->document_path, PATHINFO_EXTENSION)
            );
        }
        
        return response()->json(['message' => 'Document file not found.'], 404);
    }
}

