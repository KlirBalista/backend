<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\BirthCareSubscription;
use App\Models\SubscriptionPlan;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class RegisteredUserController extends Controller
{
    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): Response
    {
        $request->validate([
            'firstname' => ['required', 'string', 'max:100'],
            'lastname' => ['required', 'string', 'max:100'],
            'middlename' => ['nullable', 'string', 'max:100'],
            'contact_number' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:100', 'unique:users,email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'plan_id' => ['nullable', 'exists:subscription_plans,id'],
        ]);

        $user = User::create([
            'firstname' => $request->firstname,
            'middlename' => $request->middlename,
            'lastname' => $request->lastname,
            'contact_number' => $request->contact_number,
            'address' => $request->address,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'status' => 'active',
            'system_role_id' => 2, // Owner role
        ]);

        // Determine which plan to assign
        $planId = $request->plan_id;
        
        // If no plan specified, automatically assign free trial
        if (!$planId) {
            $freeTrialPlan = SubscriptionPlan::where('plan_name', 'Free Trial')->first();
            if ($freeTrialPlan) {
                $planId = $freeTrialPlan->id;
            }
        }
        
        // Create subscription (either specified plan or free trial)
        if ($planId) {
            $plan = SubscriptionPlan::find($planId);
            
            // For free trial (duration_in_year = 0), set end_date to 30 seconds from now
            $endDate = $plan->duration_in_year > 0 
                ? now()->addYears($plan->duration_in_year)
                : now()->addSeconds(30); // 30 seconds free trial
                
            BirthCareSubscription::create([
                'user_id' => $user->id,
                'plan_id' => $planId,
                'start_date' => now(),
                'end_date' => $endDate,
                'status' => 'active',
            ]);
        }

        event(new Registered($user));

        Auth::login($user);

        return response()->noContent();
    }
}