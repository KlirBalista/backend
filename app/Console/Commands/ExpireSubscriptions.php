<?php

namespace App\Console\Commands;

use App\Models\BirthCareSubscription;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ExpireSubscriptions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:expire';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark expired subscriptions as inactive';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $now = Carbon::now();
        
        // Find all active subscriptions that have expired
        $expiredSubscriptions = BirthCareSubscription::where('status', 'active')
            ->where('end_date', '<=', $now)
            ->get();

        $count = $expiredSubscriptions->count();
        $activatedCount = 0;
        
        if ($count > 0) {
            foreach ($expiredSubscriptions as $expiredSubscription) {
                // Mark the subscription as expired
                $expiredSubscription->update(['status' => 'expired']);
                
                // Check if there's a pending subscription ready to activate
                $nextPendingSubscription = BirthCareSubscription::where('user_id', $expiredSubscription->user_id)
                    ->where('status', 'pending')
                    ->where('start_date', '<=', $now)
                    ->whereHas('paymentSession', function($query) {
                        $query->where('status', 'paid');
                    })
                    ->orderBy('start_date', 'asc')
                    ->first();
                
                if ($nextPendingSubscription) {
                    $nextPendingSubscription->update(['status' => 'active']);
                    $activatedCount++;
                    $this->info("Activated pending subscription #{$nextPendingSubscription->id} for user #{$expiredSubscription->user_id}");
                }
            }

            $this->info("Expired {$count} subscription(s).");
            if ($activatedCount > 0) {
                $this->info("Activated {$activatedCount} pending subscription(s).");
            }
        } else {
            $this->info("No subscriptions to expire.");
        }

        return 0;
    }
}