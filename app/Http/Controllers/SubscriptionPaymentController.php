<?php

namespace App\Http\Controllers;

use App\Models\BirthCareSubscription;
use App\Models\PaymentSession;
use App\Models\SubscriptionPlan;
use App\Services\PayMongoService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SubscriptionPaymentController extends Controller
{
    protected $payMongoService;

    public function __construct(PayMongoService $payMongoService)
    {
        $this->payMongoService = $payMongoService;
    }

    /**
     * Create a checkout session for subscription payment
     */
    public function createCheckout(Request $request)
    {
        try {
            $validated = $request->validate([
                'plan_id' => 'required|exists:subscription_plans,id'
            ]);

            $user = Auth::user();
            $plan = SubscriptionPlan::findOrFail($validated['plan_id']);

            // Check if user already has an active subscription
            $activeSubscription = BirthCareSubscription::where('user_id', $user->id)
                ->where('status', 'active')
                ->where('end_date', '>=', now())
                ->first();

            // Check if user has a pending subscription (unpaid)
            $pendingSubscription = BirthCareSubscription::where('user_id', $user->id)
                ->where('status', 'pending')
                ->whereHas('paymentSession', function($query) {
                    $query->where('status', 'pending');
                })
                ->first();

            if ($pendingSubscription) {
                return response()->json([
                    'success' => false,
                    'message' => 'You already have a pending subscription payment. Please complete or cancel it first.'
                ], 422);
            }

            DB::beginTransaction();

            // Prepare reference number first (need user ID)
            $tempRef = "SUB{$user->id}-TEMP";
            
            // Create payment session
            $paymentSession = PaymentSession::create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'amount' => $plan->price,
                'currency' => 'PHP',
                'status' => 'pending',
                'expires_at' => now()->addHours(24), // Checkout expires in 24 hours
                'metadata' => [
                    'user_email' => $user->email,
                    'user_name' => $user->name,
                    'plan_name' => $plan->plan_name,
                ]
            ]);

            // Now generate actual reference number with payment session ID
            $frontendUrl = env('FRONTEND_URL', config('app.frontend_url', 'http://localhost:3000'));
            $referenceNumber = "SUB{$user->id}-{$paymentSession->id}";
            $description = "{$plan->plan_name} Subscription - {$plan->duration_in_year} Year(s) | Ref {$referenceNumber}";
            
            // Update payment session with reference number
            $paymentSession->update([
                'reference_number' => $referenceNumber,
            ]);

            $checkoutData = [
                'amount' => $plan->price,
                'currency' => 'PHP',
                'description' => $description, // Include ref in description so it's searchable in dashboard
                'payment_methods' => ['gcash', 'card', 'paymaya'],
                'success_url' => "{$frontendUrl}/subscription/success?session_id={$paymentSession->id}",
                'cancel_url' => "{$frontendUrl}/subscription/cancel?session_id={$paymentSession->id}",
                'reference_number' => $referenceNumber,
                'metadata' => [
                    'payment_session_id' => $paymentSession->id,
                    'user_id' => $user->id,
                    'plan_id' => $plan->id,
                    'reference_number' => $referenceNumber,
                ]
            ];

            // Update metadata with reference number (already saved in reference_number column)
            $paymentSession->update([
                'metadata' => array_merge($paymentSession->metadata ?? [], [
                    'reference_number' => $referenceNumber,
                ])
            ]);

            // Create PayMongo checkout session
            $result = $this->payMongoService->createCheckoutSession($checkoutData);

            if (!$result['success']) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create checkout session',
                    'error' => $result['error']
                ], 500);
            }

            // Update payment session with PayMongo data
            $checkoutSession = $result['data'];
            $paymentSession->update([
                'paymongo_checkout_id' => $checkoutSession['id'],
                'checkout_url' => $checkoutSession['attributes']['checkout_url'],
                'paymongo_payment_intent_id' => $checkoutSession['attributes']['payment_intent']['id'] ?? null,
            ]);

            // Store plan info in metadata for creating subscription after payment
            $paymentSession->update([
                'metadata' => array_merge($paymentSession->metadata ?? [], [
                    'plan_duration' => $plan->duration_in_year,
                ])
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => [
                    'checkout_url' => $paymentSession->checkout_url,
                    'payment_session_id' => $paymentSession->id,
                    'expires_at' => $paymentSession->expires_at,
                ],
                'message' => 'Checkout session created successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create subscription checkout', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create checkout session',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle PayMongo webhook
     */
    public function webhook(Request $request)
    {
        try {
            $payload = $request->getContent();
            $signature = $request->header('Paymongo-Signature');

            // Verify webhook signature
            if (!$this->payMongoService->verifyWebhookSignature($payload, $signature)) {
                Log::warning('Invalid PayMongo webhook signature');
                return response()->json(['error' => 'Invalid signature'], 401);
            }

            $event = json_decode($payload, true);
            $result = $this->payMongoService->processWebhook($event);

            if ($result['action'] === 'payment_paid') {
                $this->handleSuccessfulPayment($event);
            } elseif ($result['action'] === 'payment_failed') {
                $this->handleFailedPayment($event);
            }

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            Log::error('Webhook processing error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Webhook processing failed'], 500);
        }
    }

    /**
     * Handle successful payment
     */
    protected function handleSuccessfulPayment($event)
    {
        try {
            DB::beginTransaction();

            $payload = $event['data']['attributes']['data'] ?? [];
            $attributes = $payload['attributes'] ?? [];

            $checkoutSessionId = $attributes['checkout_session_id'] ?? null;
            
            if (!$checkoutSessionId) {
                // Try to get from payment intent
                $paymentIntentId = $payload['id'] ?? null;
                $paymentSession = PaymentSession::where('paymongo_payment_intent_id', $paymentIntentId)->first();
            } else {
                $paymentSession = PaymentSession::where('paymongo_checkout_id', $checkoutSessionId)->first();
            }

            if (!$paymentSession) {
                Log::warning('Payment session not found for webhook', [
                    'checkout_session_id' => $checkoutSessionId
                ]);
                return;
            }

            // Extract payment method from various possible fields
            $method = $attributes['payment_method_used'] 
                ?? ($attributes['source']['type'] ?? null) 
                ?? ($attributes['payment_method']['type'] ?? null) 
                ?? ($attributes['payment_method'] ?? null);

            if (is_array($method) && isset($method['type'])) {
                $method = $method['type'];
            }

            // Normalize known method names
            $normalized = match(strtolower((string)$method)) {
                'gcash' => 'gcash',
                'card' => 'card',
                'paymaya', 'pay_maya', 'maya' => 'paymaya',
                default => $method,
            };

            // Update payment session
            $paymentSession->update([
                'status' => 'paid',
                'paid_at' => now(),
                'payment_method' => $normalized,
            ]);

            // Create subscription NOW that payment is confirmed
            $subscription = BirthCareSubscription::where('payment_session_id', $paymentSession->id)->first();
            
            if (!$subscription) {
                // Calculate start date
                $lastSubscription = BirthCareSubscription::where('user_id', $paymentSession->user_id)
                    ->whereIn('status', ['active', 'pending'])
                    ->orderBy('end_date', 'desc')
                    ->first();
                
                $startDate = $lastSubscription ? Carbon::parse($lastSubscription->end_date)->addDay() : now();
                $plan = $paymentSession->plan;
                $endDate = $startDate->copy()->addYears($plan->duration_in_year);

                // Check if there's an active subscription
                $activeSubscription = BirthCareSubscription::where('user_id', $paymentSession->user_id)
                    ->where('status', 'active')
                    ->where('end_date', '>=', now())
                    ->first();

                $subscription = BirthCareSubscription::create([
                    'user_id' => $paymentSession->user_id,
                    'plan_id' => $paymentSession->plan_id,
                    'payment_session_id' => $paymentSession->id,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'status' => $activeSubscription ? 'pending' : 'active',
                ]);
            }

            DB::commit();

            Log::info('Subscription payment processed successfully', [
                'payment_session_id' => $paymentSession->id,
                'subscription_id' => $subscription->id ?? null
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to process successful payment', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Handle failed payment
     */
    protected function handleFailedPayment($event)
    {
        try {
            DB::beginTransaction();

            $checkoutSessionId = $event['data']['attributes']['data']['attributes']['checkout_session_id'] ?? null;
            $paymentSession = PaymentSession::where('paymongo_checkout_id', $checkoutSessionId)->first();

            if ($paymentSession) {
                $paymentSession->update(['status' => 'failed']);

                // Update subscription status
                $subscription = BirthCareSubscription::where('payment_session_id', $paymentSession->id)->first();
                if ($subscription) {
                    $subscription->update(['status' => 'failed']);
                }
            }

            DB::commit();

            Log::info('Payment failed processed', [
                'payment_session_id' => $paymentSession->id ?? null
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to process payment failure', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Check payment session status
     */
    public function checkStatus(Request $request, $sessionId)
    {
        try {
            $paymentSession = PaymentSession::with(['plan', 'subscription'])->findOrFail($sessionId);

            // Verify user owns this session
            if ($paymentSession->user_id !== Auth::id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            // If user reached success page, PayMongo only redirects on successful payment
            // Mark payment as PAID (PayMongo redirect = payment successful)
            if ($paymentSession->status === 'pending') {
                DB::beginTransaction();
                
                Log::info('Success page reached - marking payment as PAID', [
                    'payment_session_id' => $paymentSession->id
                ]);
                
                // Fetch payment details from PayMongo
                $paymentMethod = 'online_payment';
                if ($paymentSession->paymongo_checkout_id) {
                    $result = $this->payMongoService->retrieveCheckoutSession($paymentSession->paymongo_checkout_id);
                    if ($result['success']) {
                        $attributes = $result['data']['attributes'] ?? [];
                        $method = $attributes['payment_method_used'] 
                            ?? ($attributes['payments'][0]['attributes']['source']['type'] ?? null)
                            ?? null;
                        if ($method) {
                            $paymentMethod = $method;
                        }
                    }
                }
                
                $paymentSession->update([
                    'status' => 'paid',
                    'paid_at' => now(),
                    'payment_method' => $paymentMethod
                ]);

                // Create subscription if it doesn't exist
                $subscription = $paymentSession->subscription;
                if (!$subscription) {
                    $lastSubscription = BirthCareSubscription::where('user_id', $paymentSession->user_id)
                        ->whereIn('status', ['active', 'pending'])
                        ->orderBy('end_date', 'desc')
                        ->first();
                    
                    $startDate = $lastSubscription ? Carbon::parse($lastSubscription->end_date)->addDay() : now();
                    $plan = $paymentSession->plan;
                    $endDate = $startDate->copy()->addYears($plan->duration_in_year);

                    $activeSubscription = BirthCareSubscription::where('user_id', $paymentSession->user_id)
                        ->where('status', 'active')
                        ->where('end_date', '>=', now())
                        ->first();

                    $subscription = BirthCareSubscription::create([
                        'user_id' => $paymentSession->user_id,
                        'plan_id' => $paymentSession->plan_id,
                        'payment_session_id' => $paymentSession->id,
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'status' => $activeSubscription ? 'pending' : 'active',
                    ]);
                    
                    Log::info('Payment PAID, subscription created', [
                        'payment_session_id' => $paymentSession->id,
                        'subscription_id' => $subscription->id,
                        'subscription_status' => $subscription->status
                    ]);
                }
                
                DB::commit();
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'payment_session' => $paymentSession,
                    'subscription' => $paymentSession->subscription,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to check payment status', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to check payment status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel a pending payment session
     */
    public function cancelSession(Request $request, $sessionId)
    {
        try {
            $paymentSession = PaymentSession::findOrFail($sessionId);

            // Verify user owns this session
            if ($paymentSession->user_id !== Auth::id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            if ($paymentSession->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Can only cancel pending sessions'
                ], 422);
            }

            DB::beginTransaction();

            // Expire PayMongo checkout session if exists
            if ($paymentSession->paymongo_checkout_id) {
                $this->payMongoService->expireCheckoutSession($paymentSession->paymongo_checkout_id);
            }

            $paymentSession->update(['status' => 'cancelled']);

            // Cancel associated subscription
            $subscription = BirthCareSubscription::where('payment_session_id', $paymentSession->id)->first();
            if ($subscription) {
                $subscription->update(['status' => 'cancelled']);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payment session cancelled successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to cancel payment session', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel payment session',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
