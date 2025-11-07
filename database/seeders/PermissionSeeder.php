<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            // Patient Menu Group
            ['name' => 'manage_patients'],
            ['name' => 'manage_patient_documents'],
            ['name' => 'manage_patient_chart'],
            ['name' => 'manage_patient_admission'],
            ['name' => 'manage_patient_discharge'],
            ['name' => 'manage_referrals'],
            
            // Prenatal Menu Group
            ['name' => 'manage_prenatal_schedule'],
            ['name' => 'manage_prenatal_forms'],
            ['name' => 'manage_prenatal_visits_log'],
            
            // Room Management
            ['name' => 'manage_rooms'],
            
            // Labor Monitoring
            ['name' => 'manage_labor_monitoring'],
            ['name' => 'manage_active_labor'],
            
            // Newborn Menu Group
            ['name' => 'manage_birth_details'],
            ['name' => 'manage_certificate_live_birth'],
            ['name' => 'manage_screening_results'],
            
            // Billing Menu Group
            ['name' => 'manage_billing'],
            ['name' => 'manage_patient_charges'],
            ['name' => 'manage_payments'],
            ['name' => 'manage_soa_bill'],
            
            // Map
            ['name' => 'manage_map'],
            
            // Staff Menu Group
            ['name' => 'manage_staff'],
            ['name' => 'manage_role'],
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $permission['name']],
                [
                    'name' => $permission['name'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        $this->command->info('Permissions seeded successfully!');
    }
}
