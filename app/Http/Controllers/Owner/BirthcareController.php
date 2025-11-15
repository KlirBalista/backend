<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\RegisterBirthcareRequest;
use App\Models\BirthCare;
use App\Models\BirthCareDocument;
use App\Models\BirthCareSubscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class BirthcareController extends Controller
{
    /**
     * Get the current user's subscription status
     */
    public function getSubscription(Request $request)
    {
        $user = $request->user();
        
        // Get active subscription
        $activeSubscription = BirthCareSubscription::with(['plan', 'paymentSession'])
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->where('end_date', '>=', now())
            ->orderBy('created_at', 'desc')
            ->first();
        
        // Get pending subscriptions (paid but waiting to activate)
        // Free Trial subscriptions don't have payment_session, so check plan name
        $pendingSubscriptions = BirthCareSubscription::with(['plan', 'paymentSession.plan'])
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->where(function($query) {
                // Free Trial (no payment needed) OR has paid payment session
                $query->whereHas('plan', function($q) {
                    $q->where('plan_name', 'Free Trial');
                })->orWhereHas('paymentSession', function($q) {
                    $q->where('status', 'paid');
                });
            })
            ->orderBy('start_date', 'asc')
            ->get();
        
        // Get all payment sessions for invoice history
        $paymentSessions = \App\Models\PaymentSession::with('plan')
            ->where('user_id', $user->id)
            ->where('status', 'paid')
            ->orderBy('paid_at', 'desc')
            ->get();
        
        if (!$activeSubscription) {
            return response()->json([
                'status' => 'inactive',
                'message' => 'No active subscription found.',
                'pending_subscriptions' => $pendingSubscriptions,
                'payment_history' => $paymentSessions
            ]);
        }
        
        return response()->json([
            'status' => 'active',
            'subscription' => $activeSubscription,
            'expires_at' => $activeSubscription->end_date,
            'pending_subscriptions' => $pendingSubscriptions,
            'payment_history' => $paymentSessions
        ]);
    }
    
    /**
     * Get the current user's birthcare
     */
    public function getBirthcare(Request $request)
    {
        $user = $request->user();
        
        $birthcare = BirthCare::with('documents')
            ->where('user_id', $user->id)
            ->first();
        
        if (!$birthcare) {
            return response()->json([
                'message' => 'No birthcare found for this user.'
            ], 404);
        }
        
        // Transform birthcare to array and add document URLs
        $birthcareArray = $birthcare->toArray();
        $birthcareArray['documents'] = $birthcare->documents->map(function($document) {
            return [
                'id' => $document->id,
                'document_type' => $document->document_type,
                'document_path' => $document->document_path,
                'url' => method_exists($document, 'getFileUrl') ? $document->getFileUrl() : url('storage/' . $document->document_path),
                'timestamp' => $document->timestamp,
            ];
        })->toArray();
        
        return response()->json($birthcareArray);
    }
    
    /**
     * Register a new birthcare
     */
    public function register(RegisterBirthcareRequest $request)
    {
        $user = $request->user();
        
        // Check if user already has a birthcare
        $existingBirthcare = BirthCare::where('user_id', $user->id)->first();
        if ($existingBirthcare) {
            // Allow re-application if previous registration was rejected
            if (strtolower($existingBirthcare->status) === 'rejected') {
                DB::beginTransaction();
                try {
                    // Reset core details and status
                    $existingBirthcare->update([
                        'name' => $request->name,
                        'description' => $request->description,
                        'latitude' => $request->latitude,
                        'longitude' => $request->longitude,
                        'is_public' => false,
                        'status' => 'pending',
                    ]);

                    // Only update documents that are uploaded (keep existing if not provided)
                    $documentTypes = [
                        'business_permit' => 'Business Permit',
                        'doh_cert' => 'DOH Certificate',
                        'philhealth_cert' => 'PhilHealth Certificate',
                    ];

                    foreach ($documentTypes as $key => $type) {
                        if ($request->hasFile($key)) {
                            // Delete old document of this type
                            $oldDoc = BirthCareDocument::where('birth_care_id', $existingBirthcare->id)
                                ->where('document_type', $type)
                                ->first();
                            if ($oldDoc) {
                                if ($oldDoc->document_path) {
                                    try {
                                        Storage::disk('public')->delete($oldDoc->document_path);
                                    } catch (\Throwable $t) {
                                        // ignore delete errors
                                    }
                                }
                                $oldDoc->delete();
                            }
                            
                            // Upload new document to Supabase
                            $file = $request->file($key);
                            $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
                            $path = $existingBirthcare->id . '/' . $filename;
                            Storage::disk('supabase_birthcare')->put($path, file_get_contents($file->getRealPath()), [
                                'mimetype' => $file->getMimeType(),
                            ]);

                            BirthCareDocument::create([
                                'birth_care_id' => $existingBirthcare->id,
                                'document_type' => $type,
                                'document_path' => $path,
                                'timestamp' => now(),
                            ]);
                        }
                    }

                    DB::commit();

                    return response()->json([
                        'message' => 'Your application has been resubmitted and is pending admin approval.',
                        'birthcare' => $existingBirthcare,
                    ], 200);
                } catch (\Exception $e) {
                    DB::rollBack();
                    Log::error('Birthcare re-application failed: ' . $e->getMessage());
                    return response()->json([
                        'message' => 'Failed to resubmit application. Please try again.'
                    ], 500);
                }
            }

            return response()->json([
                'message' => 'You already have a registered birthcare facility.'
            ], 409);
        }
        
        // Check if user has a subscription, if not create free trial
        $existingSubscription = BirthCareSubscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->where('end_date', '>', now())
            ->first();
            
        if (!$existingSubscription) {
            $freeTrialPlan = \App\Models\SubscriptionPlan::where('plan_name', 'Free Trial')->first();
            if ($freeTrialPlan) {
                BirthCareSubscription::create([
                    'user_id' => $user->id,
                    'plan_id' => $freeTrialPlan->id,
                    'start_date' => now(),
                    'end_date' => now()->addSeconds(30), // 30 seconds trial
                    'status' => 'active',
                ]);
            }
        }
        
        // Begin transaction to ensure all related records are created together
        DB::beginTransaction();
        
        try {
            // Create the birthcare record
            $birthcare = BirthCare::create([
                'user_id' => $user->id,
                'name' => $request->name,
                'description' => $request->description,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'is_public' => false, // Default to not public
                'status' => 'pending', // Default status is pending
            ]);
            
            // Process and store the documents
            $documentTypes = [
                'business_permit' => 'Business Permit',
                'doh_cert' => 'DOH Certificate',
                'philhealth_cert' => 'PhilHealth Certificate',
            ];
            
            foreach ($documentTypes as $key => $type) {
                if ($request->hasFile($key)) {
                    $file = $request->file($key);
                    // Create unique filename
                    $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
                    // Store file in Supabase birthcare_documents bucket
                    $path = $birthcare->id . '/' . $filename;
                    Storage::disk('supabase_birthcare')->put($path, file_get_contents($file->getRealPath()), [
                        'mimetype' => $file->getMimeType(),
                    ]);
                    
                    // Create document record
                    BirthCareDocument::create([
                        'birth_care_id' => $birthcare->id,
                        'document_type' => $type,
                        'document_path' => $path,
                        'timestamp' => now(),
                    ]);
                }
            }
            
            DB::commit();
            
            return response()->json([
                'message' => 'Birthcare facility registered successfully and pending admin approval.',
                'birthcare' => $birthcare
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            // Log error for debugging
            Log::error('Birthcare registration failed: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Failed to register birthcare facility. Please try again.'
            ], 500);
        }
    }
    
    /**
     * Update birthcare facility information
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();
        
        // Find the birthcare facility
        $birthcare = BirthCare::where('id', $id)
            ->where('user_id', $user->id)
            ->first();
        
        if (!$birthcare) {
            return response()->json([
                'message' => 'Birthcare facility not found or you do not have permission to update it.'
            ], 404);
        }
        
        // Validate the request
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|required|string|max:1000',
            'latitude' => 'sometimes|numeric',
            'longitude' => 'sometimes|numeric',
            'is_public' => 'sometimes|boolean',
        ]);
        
        // Update only the fields that are provided
        $updateData = [];
        if ($request->has('name')) {
            $updateData['name'] = $request->name;
        }
        if ($request->has('description')) {
            $updateData['description'] = $request->description;
        }
        if ($request->has('latitude')) {
            $updateData['latitude'] = $request->latitude;
        }
        if ($request->has('longitude')) {
            $updateData['longitude'] = $request->longitude;
        }
        if ($request->has('is_public')) {
            $updateData['is_public'] = $request->is_public;
        }
        
        try {
            $birthcare->update($updateData);
            
            return response()->json([
                'message' => 'Birthcare facility updated successfully.',
                'birthcare' => $birthcare
            ]);
        } catch (\Exception $e) {
            Log::error('Birthcare update failed: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Failed to update birthcare facility. Please try again.'
            ], 500);
        }
    }
    
    /**
     * Check if a user's birthcare is approved
     */
    public function checkApprovalStatus(Request $request)
    {
        $user = $request->user();
        
        $birthcare = BirthCare::where('user_id', $user->id)->first();
        
        if (!$birthcare) {
            return response()->json([
                'status' => 'not_registered',
                'message' => 'No birthcare facility found for this user.'
            ]);
        }
        
        return response()->json([
            'status' => $birthcare->status,
            'approved' => $birthcare->status === 'approved',
            'message' => $this->getStatusMessage($birthcare->status)
        ]);
    }
    
    /**
     * Get all registered (approved) birthcare facilities for public map display
     * Filters facilities to Davao City area only (lat: 6.8-7.3, lng: 125.2-125.8)
     */
    public function getAllRegistered()
    {
        // Davao City boundaries (approximate)
        $davaoBounds = [
            'lat_min' => 6.8,
            'lat_max' => 7.3,
            'lng_min' => 125.2,
            'lng_max' => 125.8
        ];

        $birthcares = BirthCare::with(['owner'])
            ->where('status', 'approved')
            ->whereBetween('latitude', [$davaoBounds['lat_min'], $davaoBounds['lat_max']])
            ->whereBetween('longitude', [$davaoBounds['lng_min'], $davaoBounds['lng_max']])
            ->select([
                'id',
                'name', 
                'latitude',
                'longitude',
                'description',
                'is_public',
                'status',
                'user_id',
                'created_at'
            ])
            ->get();

        // Format data for frontend consumption
        $formattedBirthcares = $birthcares->map(function ($birthcare) {
            return [
                'id' => $birthcare->id,
                'name' => $birthcare->name,
                'latitude' => (float) $birthcare->latitude,
                'longitude' => (float) $birthcare->longitude,
                'description' => $birthcare->description,
                'is_public' => $birthcare->is_public,
                'status' => $birthcare->status,
                'owner' => $birthcare->owner ? [
                    'name' => $birthcare->owner->firstname . ' ' . $birthcare->owner->lastname,
                    'email' => $birthcare->owner->email,
                ] : null,
                'created_at' => $birthcare->created_at,
                // Add mock data for fields that frontend expects but aren't in DB  
                'phone' => $birthcare->owner->contact_number ?? 'Not provided',
                'staff_count' => rand(5, 50), // Mock staff count
                'visibility' => $birthcare->is_public ? 'public' : 'private',
            ];
        });

        return response()->json($formattedBirthcares);
    }
    
    /**
     * Update birthcare documents (for rejected facilities)
     */
    public function updateDocuments(Request $request, $id)
    {
        $user = $request->user();
        
        $birthcare = BirthCare::where('id', $id)
            ->where('user_id', $user->id)
            ->first();
            
        if (!$birthcare) {
            return response()->json([
                'message' => 'Birthcare facility not found or unauthorized.'
            ], 404);
        }
        
        // Only allow document updates for rejected facilities
        if ($birthcare->status !== 'rejected') {
            return response()->json([
                'message' => 'Documents can only be updated for rejected facilities.'
            ], 403);
        }
        
        $request->validate([
            'business_permit' => 'sometimes|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'doh_certificate' => 'sometimes|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'philhealth_certificate' => 'sometimes|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);
        
        if (!$request->hasFile('business_permit') && !$request->hasFile('doh_certificate') && !$request->hasFile('philhealth_certificate')) {
            return response()->json([
                'message' => 'Please upload at least one document to update.'
            ], 422);
        }
        
        DB::beginTransaction();
        try {
            // Upload new documents (replace only those provided)
            $documentTypes = [
                'business_permit' => 'Business Permit',
                'doh_certificate' => 'DOH Certificate',
                'philhealth_certificate' => 'PhilHealth Certificate',
            ];
            
            foreach ($documentTypes as $key => $type) {
                if ($request->hasFile($key)) {
                    // Remove existing doc of this type (row + file)
                    $existing = BirthCareDocument::where('birth_care_id', $birthcare->id)
                        ->where('document_type', $type)
                        ->first();
                    if ($existing) {
                        if ($existing->document_path) {
                            Storage::disk('public')->delete($existing->document_path);
                        }
                        $existing->delete();
                    }
                    
                    $file = $request->file($key);
                    $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
                    $path = $birthcare->id . '/' . $filename;
                    Storage::disk('supabase_birthcare')->put($path, file_get_contents($file->getRealPath()), [
                        'mimetype' => $file->getMimeType(),
                    ]);
                    
                    BirthCareDocument::create([
                        'birth_care_id' => $birthcare->id,
                        'document_type' => $type,
                        'document_path' => $path,
                        'timestamp' => now(),
                    ]);
                }
            }
            
            // NOTE: Do NOT auto-change status here.
            // Updating documents should not automatically resubmit the application.
            // Resubmission is handled explicitly via the resubmit() endpoint.
            DB::commit();
            
            return response()->json([
                'message' => 'Documents updated successfully. You can now resubmit your application for review.',
                'birthcare' => $birthcare->load('documents'),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Document update failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to update documents. Please try again.'
            ], 500);
        }
    }
    
    /**
     * Resubmit facility for review after rejection
     */
    public function resubmit(Request $request, $id)
    {
        $user = $request->user();
        
        $birthcare = BirthCare::where('id', $id)
            ->where('user_id', $user->id)
            ->first();
            
        if (!$birthcare) {
            return response()->json([
                'message' => 'Birthcare facility not found or unauthorized.'
            ], 404);
        }
        
        if ($birthcare->status !== 'rejected') {
            return response()->json([
                'message' => 'Only rejected facilities can be resubmitted.'
            ], 403);
        }
        
        $birthcare->update([
            'status' => 'pending',
            'rejection_reason' => null,
        ]);
        
        return response()->json([
            'message' => 'Application resubmitted successfully.',
            'birthcare' => $birthcare,
        ]);
    }
    
    /**
     * Get a descriptive message for each status
     */
    private function getStatusMessage($status)
    {
        switch ($status) {
            case 'pending':
                return 'Your birthcare facility registration is pending admin approval.';
            case 'approved':
                return 'Your birthcare facility registration has been approved.';
            case 'rejected':
                return 'Your birthcare facility registration has been rejected.';
            default:
                return 'Unknown status.';
        }
    }
}

