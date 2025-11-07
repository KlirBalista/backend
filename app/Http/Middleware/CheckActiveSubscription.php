<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\BirthCareSubscription;
use Carbon\Carbon;

class CheckActiveSubscription
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Check if user is authenticated
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Only apply this middleware to owners (system_role_id = 2)
        if ($user->system_role_id !== 2) {
            return $next($request);
        }

        // Check for active subscription
        $subscription = BirthCareSubscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->where('end_date', '>', Carbon::now())
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$subscription) {
            return response()->json([
                'message' => 'Active subscription required',
                'error' => 'subscription_required',
                'details' => 'You need an active subscription to access this feature. Please subscribe to continue.',
'redirect_to' => '/subscription'
            ], 403);
        }

        // Check if subscription is about to expire (handle both seconds and days)
        $expiryDate = Carbon::parse($subscription->end_date);
        $now = Carbon::now();
        
        // For very short trials (like 30 seconds), calculate remaining seconds
        $secondsUntilExpiry = $now->diffInSeconds($expiryDate, false);
        $daysUntilExpiry = $now->diffInDays($expiryDate, false);
        
        if ($secondsUntilExpiry > 0) {
            // For trials less than 1 hour, show seconds countdown
            if ($secondsUntilExpiry <= 3600) {
                return $next($request)->header('X-Subscription-Warning', "Trial expires in {$secondsUntilExpiry} seconds");
            }
            // For regular subscriptions, show days warning
            elseif ($daysUntilExpiry <= 7) {
                return $next($request)->header('X-Subscription-Warning', "Your subscription expires in {$daysUntilExpiry} days");
            }
        }

        return $next($request);
    }
}