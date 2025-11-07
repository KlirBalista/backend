<?php

namespace Database\Seeders;

use App\Models\BirthCareSubscription;
use App\Models\Permission;
use App\Models\SubscriptionPlan;
use App\Models\SystemRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed System Roles
        $roles = [
            ['id' => 1, 'name' => 'admin'],
            ['id' => 2, 'name' => 'owner'],
            ['id' => 3, 'name' => 'staff'],
        ];

        foreach ($roles as $role) {
            SystemRole::updateOrCreate(
                ['id' => $role['id']],
                ['name' => $role['name'], 'created_at' => now(), 'updated_at' => now()]
            );
        }

        // Seed Subscription Plans
        $plans = [
            [
                'plan_name' => 'Free Trial',
                'price' => 0.00,
                'duration_in_year' => 0, // Special case: 30 seconds trial
                'description' => 'Quick 30-second demo to explore all features. Experience the complete system with full access to patient management, prenatal care, labor monitoring, and more. Subscribe now to continue using all features!',
            ],
            [
                'plan_name' => 'Basic',
                'price' => 99.99,
                'duration_in_year' => 1,
                'description' => 'Perfect for small to medium healthcare facilities. 1 Year duration with Full Access to All Features including Complete patient management system, Prenatal care scheduling & tracking, Advanced labor monitoring, Room & bed management, Newborn screening & documentation, Birth certificate generation, and 4 more features.',
            ],
            [
                'plan_name' => 'Standard',
                'price' => 249.99,
                'duration_in_year' => 3,
                'description' => 'Great value for growing healthcare practices. 3 Years duration with Full Access to All Features including Complete patient management system, Prenatal care scheduling & tracking, Advanced labor monitoring, Room & bed management, Newborn screening & documentation, Birth certificate generation, and 4 more features. Save 25%!',
            ],
            [
                'plan_name' => 'Premium',
                'price' => 399.99,
                'duration_in_year' => 5,
                'description' => 'Best value for established healthcare facilities. 5 Years duration with Full Access to All Features including Complete patient management system, Prenatal care scheduling & tracking, Advanced labor monitoring, Room & bed management, Newborn screening & documentation, Birth certificate generation, and 4 more features. Save 35%!',
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::updateOrCreate(
                ['plan_name' => $plan['plan_name']],
                [
                    'price' => $plan['price'],
                    'duration_in_year' => $plan['duration_in_year'],
                    'description' => $plan['description'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        // Call PermissionSeeder
        $this->call(PermissionSeeder::class);

        // Seed Users and Subscriptions
        $users = [
            [
                'firstname' => 'Admin',
                'middlename' => null,
                'lastname' => 'User',
                'contact_number' => '1234567890',
                'address' => '123 Admin Street',
                'email' => 'admin@example.com',
                'password' => Hash::make('123123123'),
                'status' => 'active',
                'system_role_id' => 1,
                'email_verified_at' => now(),
            ],
            [
                'firstname' => 'Owner',
                'middlename' => 'A',
                'lastname' => 'Doe',
                'contact_number' => '0987654321',
                'address' => '456 User Road',
                'email' => 'owner@example.com',
                'password' => Hash::make('123123123'),
                'status' => 'active',
                'system_role_id' => 2,
                'email_verified_at' => now(),
            ],
        ];

        foreach ($users as $userData) {
            // Create or update the user
            $user = User::updateOrCreate(
                ['email' => $userData['email']],
                $userData
            );

            // Create a FREE TRIAL subscription for owner (system_role_id = 2)
            if ($user->system_role_id === 2) {
                $freeTrialPlan = SubscriptionPlan::where('plan_name', 'Free Trial')->first();
                if ($freeTrialPlan) {
                    BirthCareSubscription::updateOrCreate(
                        [
                            'user_id' => $user->id,
                            'plan_id' => $freeTrialPlan->id,
                        ],
                        [
                            'start_date' => now(),
                            'end_date' => now()->addSeconds(30), // 30 seconds trial
                            'status' => 'active',
                        ]
                    );
                } 
            }
        }
    }
}