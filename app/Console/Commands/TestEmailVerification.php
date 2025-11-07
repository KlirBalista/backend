<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Console\Command;

class TestEmailVerification extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:email-verification';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test email verification functionality';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Use the real email
        $email = 'carolclarebalista@gmail.com';
        
        // Find or create user
        $user = User::where('email', $email)->first();
        if ($user) {
            // Reset email verification for testing
            $user->update(['email_verified_at' => null]);
            $this->info("Using existing user with ID: " . $user->id);
        } else {
            // Create new user
            $user = User::create([
                'firstname' => 'Carol',
                'lastname' => 'Balista', 
                'email' => $email,
                'password' => bcrypt('password'),
                'status' => 'active',
                'system_role_id' => 2
            ]);
            $this->info("Created new user with ID: " . $user->id);
        }

        // Trigger the registered event which sends verification email
        event(new Registered($user));

        $this->info("Email verification sent to: " . $user->email);
        $this->info("User email_verified_at: " . ($user->email_verified_at ? $user->email_verified_at : 'null'));
        
        // Extract verification URL from log
        $logContent = file_get_contents(storage_path('logs/laravel.log'));
        if (preg_match('/verify_url=([^"&%]+)/', $logContent, $matches)) {
            $verifyUrl = urldecode($matches[1]);
            $this->info("\nTo test verification, visit this URL:");
            $this->info($verifyUrl);
        }
        
        return 0;
    }
}
