<?php

namespace App\Http\Controllers;

use App\Models\Feedback;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\JsonResponse;

class FeedbackController extends Controller
{
    /**
     * Display a listing of feedback (for admin purposes)
     */
    public function index(Request $request): JsonResponse
    {
        $query = Feedback::query();

        // Filter by status if provided
        if ($request->has('status') && in_array($request->status, ['pending', 'approved', 'rejected'])) {
            $query->where('status', $request->status);
        }

        // Filter by consent if provided
        if ($request->has('consent_given')) {
            $query->where('consent_given', filter_var($request->consent_given, FILTER_VALIDATE_BOOLEAN));
        }

        $feedback = $query->orderBy('submitted_at', 'desc')
                         ->paginate($request->get('per_page', 15));

        return response()->json($feedback);
    }

    /**
     * Get public approved feedback for display on website
     */
    public function public(): JsonResponse
    {
        $feedback = Feedback::public()
                           ->select(['id', 'name', 'title_institution', 'rating', 'feedback_text', 'submitted_at'])
                           ->orderBy('submitted_at', 'desc')
                           ->take(20) // Limit to most recent 20
                           ->get();

        return response()->json([
            'data' => $feedback,
            'message' => 'Public feedback retrieved successfully'
        ]);
    }

    /**
     * Store a newly created feedback
     */
    public function store(Request $request): JsonResponse
    {
        // Rate limiting: 3 submissions per hour per IP
        $rateLimitKey = 'feedback-submission:' . $request->ip();
        
        if (RateLimiter::tooManyAttempts($rateLimitKey, 3)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            return response()->json([
                'message' => 'Too many feedback submissions. Please try again in ' . ceil($seconds / 60) . ' minutes.',
                'retry_after' => $seconds
            ], 429);
        }

        // Validate input
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'title_institution' => 'required|string|max:255',
            'email' => 'required|email:rfc,dns|max:255',
            'rating' => 'required|integer|between:1,5',
            'feedback_text' => 'required|string|max:2000|min:10',
            'consent_given' => 'boolean'
        ], [
            'name.required' => 'Please provide your full name.',
            'title_institution.required' => 'Please provide your title and institution.',
            'email.required' => 'Please provide a valid email address.',
            'email.email' => 'Please provide a valid email address.',
            'rating.required' => 'Please provide a rating.',
            'rating.between' => 'Rating must be between 1 and 5 stars.',
            'feedback_text.required' => 'Please provide your feedback.',
            'feedback_text.max' => 'Feedback cannot exceed 2000 characters.',
            'feedback_text.min' => 'Feedback must be at least 10 characters long.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Create feedback record
            $feedback = Feedback::create([
                'name' => $request->name,
                'title_institution' => $request->title_institution,
                'email' => $request->email,
                'rating' => $request->rating,
                'feedback_text' => $request->feedback_text,
                'consent_given' => $request->boolean('consent_given', false),
                'status' => 'pending', // All feedback starts as pending
                'submitted_at' => now(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            // Increment rate limiter
            RateLimiter::hit($rateLimitKey, 3600); // 1 hour decay

            return response()->json([
                'message' => 'Thank you for your feedback! Your submission has been received and will be reviewed for publication.',
                'data' => [
                    'id' => $feedback->id,
                    'submitted_at' => $feedback->submitted_at->format('Y-m-d H:i:s')
                ]
            ], 201);

        } catch (\Exception $e) {
            \Log::error('Feedback submission error: ' . $e->getMessage(), [
                'request_data' => $request->except(['email']), // Don't log email for privacy
                'ip' => $request->ip()
            ]);

            return response()->json([
                'message' => 'An error occurred while submitting your feedback. Please try again later.'
            ], 500);
        }
    }

    /**
     * Display the specified feedback
     */
    public function show(Feedback $feedback): JsonResponse
    {
        return response()->json([
            'data' => $feedback,
            'message' => 'Feedback retrieved successfully'
        ]);
    }

    /**
     * Update feedback status (admin only)
     */
    public function updateStatus(Request $request, Feedback $feedback): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,approved,rejected',
            'admin_notes' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $feedback->update([
            'status' => $request->status,
            // You could add admin_notes field to model/migration if needed
        ]);

        return response()->json([
            'message' => 'Feedback status updated successfully',
            'data' => $feedback->fresh()
        ]);
    }

    /**
     * Remove the specified feedback
     */
    public function destroy(Feedback $feedback): JsonResponse
    {
        $feedback->delete();

        return response()->json([
            'message' => 'Feedback deleted successfully'
        ]);
    }

    /**
     * Get feedback statistics
     */
    public function stats(): JsonResponse
    {
        $stats = [
            'total' => Feedback::count(),
            'pending' => Feedback::where('status', 'pending')->count(),
            'approved' => Feedback::where('status', 'approved')->count(),
            'rejected' => Feedback::where('status', 'rejected')->count(),
            'with_consent' => Feedback::where('consent_given', true)->count(),
            'average_rating' => round(Feedback::avg('rating'), 2),
            'rating_distribution' => [
                '5_stars' => Feedback::where('rating', 5)->count(),
                '4_stars' => Feedback::where('rating', 4)->count(),
                '3_stars' => Feedback::where('rating', 3)->count(),
                '2_stars' => Feedback::where('rating', 2)->count(),
                '1_star' => Feedback::where('rating', 1)->count(),
            ]
        ];

        return response()->json([
            'data' => $stats,
            'message' => 'Feedback statistics retrieved successfully'
        ]);
    }
}