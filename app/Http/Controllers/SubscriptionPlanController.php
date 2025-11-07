<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SubscriptionPlanController extends Controller
{
    /**
     * Display a listing of all subscription plans.
     */
    public function index(): JsonResponse
    {
        $plans = SubscriptionPlan::all();
        return response()->json($plans);
    }

    /**
     * Display the specified subscription plan.
     */
    public function show(SubscriptionPlan $plan): JsonResponse
    {
        return response()->json($plan);
    }

    /**
     * Store a newly created subscription plan (admin only).
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function store(Request $request): JsonResponse
    {

        $validated = $request->validate([
            'plan_name' => ['required', 'string', 'max:100', 'unique:subscription_plans,plan_name'],
            'price' => ['required', 'numeric', 'min:0'],
            'duration_in_year' => ['required', 'integer', 'min:1'],
            'description' => ['nullable', 'string'],
        ]);

        $plan = SubscriptionPlan::create($validated);

        return response()->json($plan, 201);
    }

    /**
     * Update the specified subscription plan (admin only).
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function update(Request $request, SubscriptionPlan $plan): JsonResponse
    {

        $validated = $request->validate([
            'plan_name' => ['required', 'string', 'max:100', 'unique:subscription_plans,plan_name,'.$plan->id],
            'price' => ['required', 'numeric', 'min:0'],
            'duration_in_year' => ['required', 'integer', 'min:1'],
            'description' => ['nullable', 'string'],
        ]);

        $plan->update($validated);

        return response()->json($plan);
    }

    /**
     * Remove the specified subscription plan (admin only).
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function destroy(SubscriptionPlan $plan): JsonResponse
    {

        // Prevent deletion if the plan is in use
        if ($plan->subscriptions()->exists()) {
            return response()->json(['error' => 'Cannot delete plan with active subscriptions'], 422);
        }

        $plan->delete();

        return response()->json(null, 204);
    }

}