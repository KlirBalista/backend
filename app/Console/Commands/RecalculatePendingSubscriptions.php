<?php

namespace App\Console\Commands;

use App\Models\BirthCareSubscription;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RecalculatePendingSubscriptions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:recalculate-pending';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculate start and end dates for all pending subscriptions to chain them properly';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Recalculating pending subscription dates...');
        
        // Get all users with pending subscriptions
        $usersWithPending = BirthCareSubscription::where('status', 'pending')
            ->distinct('user_id')
            ->pluck('user_id');
        
        $totalFixed = 0;
        
        foreach ($usersWithPending as $userId) {
            $this->info("Processing user ID: {$userId}");
            
            // Get active subscription for this user
            $activeSubscription = BirthCareSubscription::where('user_id', $userId)
                ->where('status', 'active')
                ->where('end_date', '>=', now())
                ->first();
            
            // Get all pending subscriptions for this user, ordered by created_at
            $pendingSubscriptions = BirthCareSubscription::where('user_id', $userId)
                ->where('status', 'pending')
                ->orderBy('created_at', 'asc')
                ->get();
            
            if ($pendingSubscriptions->isEmpty()) {
                continue;
            }
            
            // Start from active subscription's end date, or now if no active
            $previousEndDate = $activeSubscription 
                ? Carbon::parse($activeSubscription->end_date) 
                : now();
            
            foreach ($pendingSubscriptions as $subscription) {
                $plan = $subscription->plan;
                if (!$plan) {
                    $this->warn("Subscription #{$subscription->id} has no plan, skipping...");
                    continue;
                }
                
                // Calculate new start and end dates
                $newStartDate = $previousEndDate->copy()->addDay();
                $newEndDate = $newStartDate->copy()->addYears($plan->duration_in_year);
                
                // Update subscription
                $subscription->update([
                    'start_date' => $newStartDate,
                    'end_date' => $newEndDate,
                ]);
                
                $this->info("  Fixed subscription #{$subscription->id}: {$subscription->plan->plan_name} ({$plan->duration_in_year} years)");
                $this->info("    New dates: {$newStartDate->format('Y-m-d')} to {$newEndDate->format('Y-m-d')}");
                
                // Update for next iteration
                $previousEndDate = $newEndDate;
                $totalFixed++;
            }
        }
        
        if ($totalFixed > 0) {
            $this->info("Successfully recalculated {$totalFixed} pending subscription(s).");
        } else {
            $this->info("No pending subscriptions to recalculate.");
        }
        
        return 0;
    }
}
