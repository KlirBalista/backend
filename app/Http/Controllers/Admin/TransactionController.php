<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentSession;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    /**
     * Display a listing of subscription transactions
     */
    public function index(Request $request)
    {
        try {
            $query = PaymentSession::with(['user', 'plan', 'subscription'])
                ->orderBy('created_at', 'desc');

            // Apply filters
            if ($request->has('status') && $request->status !== 'all' && $request->status !== '') {
                $query->where('status', $request->status);
            }

            if ($request->has('payment_method') && $request->payment_method !== 'all' && $request->payment_method !== '') {
                $query->where('payment_method', $request->payment_method);
            }

            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('reference_number', 'like', "%{$search}%")
                      ->orWhere('paymongo_checkout_id', 'like', "%{$search}%")
                      ->orWhereHas('user', function ($userQuery) use ($search) {
                          $userQuery->where('email', 'like', "%{$search}%")
                                    ->orWhere('firstname', 'like', "%{$search}%")
                                    ->orWhere('lastname', 'like', "%{$search}%");
                      })
                      ->orWhereHas('plan', function ($planQuery) use ($search) {
                          $planQuery->where('plan_name', 'like', "%{$search}%");
                      });
                });
            }

            if ($request->has('date_from') && $request->date_from) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }

            if ($request->has('date_to') && $request->date_to) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            $transactions = $query->paginate($request->get('per_page', 15));

            // Calculate summary statistics
            $summary = [
                'total_transactions' => PaymentSession::count(),
                'total_revenue' => PaymentSession::where('status', 'paid')->sum('amount'),
                'pending_amount' => PaymentSession::where('status', 'pending')->sum('amount'),
                'paid_count' => PaymentSession::where('status', 'paid')->count(),
                'pending_count' => PaymentSession::where('status', 'pending')->count(),
                'failed_count' => PaymentSession::where('status', 'failed')->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => $transactions,
                'summary' => $summary
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch transactions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified transaction
     */
    public function show($id)
    {
        try {
            $transaction = PaymentSession::with(['user', 'plan', 'subscription.birthCare'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $transaction
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }
}
