<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PayMongoService
{
    protected $secretKey;
    protected $publicKey;
    protected $baseUrl = 'https://api.paymongo.com/v1';

    public function __construct()
    {
        $this->secretKey = config('services.paymongo.secret_key');
        $this->publicKey = config('services.paymongo.public_key');
    }

    /**
     * Create a checkout session for subscription payment
     */
    public function createCheckoutSession($data)
    {
        try {
            $response = Http::withBasicAuth($this->secretKey, '')
                ->post("{$this->baseUrl}/checkout_sessions", [
                    'data' => [
                        'attributes' => [
                            'send_email_receipt' => true,
                            'show_description' => true,
                            'show_line_items' => true,
                            'line_items' => [
                                [
                                    'currency' => $data['currency'] ?? 'PHP',
                                    'amount' => $data['amount'] * 100, // Convert to centavos
                                    'name' => $data['description'],
                                    'quantity' => 1,
                                ]
                            ],
                            'payment_method_types' => $data['payment_methods'] ?? ['gcash', 'card', 'paymaya'],
                            'success_url' => $data['success_url'],
                            'cancel_url' => $data['cancel_url'],
                            'description' => $data['description'],
                            'reference_number' => $data['reference_number'] ?? null,
                            'metadata' => $data['metadata'] ?? [],
                        ]
                    ]
                ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()['data']
                ];
            }

            Log::error('PayMongo checkout session creation failed', [
                'status' => $response->status(),
                'response' => $response->json()
            ]);

            return [
                'success' => false,
                'error' => $response->json()['errors'][0]['detail'] ?? 'Failed to create checkout session'
            ];
        } catch (\Exception $e) {
            Log::error('PayMongo API error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Retrieve a checkout session by ID
     */
    public function retrieveCheckoutSession($checkoutId)
    {
        try {
            $response = Http::withBasicAuth($this->secretKey, '')
                ->get("{$this->baseUrl}/checkout_sessions/{$checkoutId}");

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()['data']
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to retrieve checkout session'
            ];
        } catch (\Exception $e) {
            Log::error('PayMongo retrieve session error', [
                'message' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Verify webhook signature
     */
    public function verifyWebhookSignature($payload, $signature)
    {
        $webhookSecret = config('services.paymongo.webhook_secret');
        
        if (!$webhookSecret) {
            Log::warning('PayMongo webhook secret not configured');
            return false;
        }

        $computedSignature = hash_hmac('sha256', $payload, $webhookSecret);
        
        return hash_equals($computedSignature, $signature);
    }

    /**
     * Process webhook event
     */
    public function processWebhook($event)
    {
        $eventType = $event['data']['attributes']['type'] ?? null;
        
        Log::info('Processing PayMongo webhook', [
            'event_type' => $eventType,
            'event_id' => $event['data']['id'] ?? null
        ]);

        switch ($eventType) {
            case 'checkout_session.payment.paid':
                return $this->handlePaymentPaid($event);
            
            case 'payment.paid':
                return $this->handlePaymentPaid($event);
            
            case 'payment.failed':
                return $this->handlePaymentFailed($event);
            
            default:
                Log::info('Unhandled webhook event type', ['type' => $eventType]);
                return ['success' => true, 'message' => 'Event type not handled'];
        }
    }

    /**
     * Handle payment paid event
     */
    protected function handlePaymentPaid($event)
    {
        return [
            'success' => true,
            'action' => 'payment_paid',
            'data' => $event['data']
        ];
    }

    /**
     * Handle payment failed event
     */
    protected function handlePaymentFailed($event)
    {
        return [
            'success' => true,
            'action' => 'payment_failed',
            'data' => $event['data']
        ];
    }

    /**
     * Expire a checkout session
     */
    public function expireCheckoutSession($checkoutId)
    {
        try {
            $response = Http::withBasicAuth($this->secretKey, '')
                ->post("{$this->baseUrl}/checkout_sessions/{$checkoutId}/expire");

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()['data']
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to expire checkout session'
            ];
        } catch (\Exception $e) {
            Log::error('PayMongo expire session error', [
                'message' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
