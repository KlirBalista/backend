<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PatientCharge;
use App\Models\BirthCare;

class PatientChargesSeeder extends Seeder
{
    /**
     * Run the database seeder.
     */
    public function run(): void
    {
        // Get all birthcare facilities
        $birthcares = BirthCare::all();
        
        if ($birthcares->isEmpty()) {
            $this->command->warn('No birthcare facilities found. Please create birthcare facilities first.');
            return;
        }

        $services = [
            [
                'service_name' => 'Prenatal Consultation',
                'description' => 'Comprehensive prenatal check-up including physical examination, fetal monitoring, and health assessment',
                'price' => 1500.00,
                'is_active' => true,
                'category' => 'Consultation'
            ],
            [
                'service_name' => 'Ultrasound (2D)',
                'description' => 'Basic 2D ultrasound examination for fetal development monitoring',
                'price' => 800.00,
                'is_active' => true,
                'category' => 'Diagnostic'
            ],
            [
                'service_name' => 'Ultrasound (4D)',
                'description' => 'Advanced 4D ultrasound imaging with detailed fetal visualization',
                'price' => 2500.00,
                'is_active' => true,
                'category' => 'Diagnostic'
            ],
            [
                'service_name' => 'Laboratory Tests Package',
                'description' => 'Complete blood count, urinalysis, blood sugar, and other essential lab tests',
                'price' => 1200.00,
                'is_active' => true,
                'category' => 'Laboratory'
            ],
            [
                'service_name' => 'Normal Delivery',
                'description' => 'Standard vaginal delivery including labor monitoring and post-delivery care',
                'price' => 25000.00,
                'is_active' => true,
                'category' => 'Delivery'
            ],
            [
                'service_name' => 'Cesarean Section',
                'description' => 'Surgical delivery procedure including pre-op, surgery, and post-op care',
                'price' => 45000.00,
                'is_active' => true,
                'category' => 'Surgery'
            ],
            [
                'service_name' => 'Newborn Care Package',
                'description' => 'Complete newborn examination, vaccinations, and initial care services',
                'price' => 3500.00,
                'is_active' => true,
                'category' => 'Pediatric'
            ],
            [
                'service_name' => 'Room Accommodation (Private)',
                'description' => 'Private room with air conditioning, private bathroom, and amenities (per day)',
                'price' => 2500.00,
                'is_active' => true,
                'category' => 'Accommodation'
            ],
            [
                'service_name' => 'Room Accommodation (Semi-Private)',
                'description' => 'Semi-private room with shared facilities (per day)',
                'price' => 1800.00,
                'is_active' => true,
                'category' => 'Accommodation'
            ],
            [
                'service_name' => 'Postpartum Care',
                'description' => 'Post-delivery care including wound care, breastfeeding support, and recovery monitoring',
                'price' => 2000.00,
                'is_active' => true,
                'category' => 'Care'
            ],
            [
                'service_name' => 'Emergency Consultation',
                'description' => '24/7 emergency consultation for urgent maternal and fetal concerns',
                'price' => 2000.00,
                'is_active' => true,
                'category' => 'Emergency'
            ],
            [
                'service_name' => 'Episiotomy',
                'description' => 'Surgical procedure to widen the vaginal opening during delivery',
                'price' => 3000.00,
                'is_active' => true,
                'category' => 'Surgery'
            ],
        ];

        foreach ($birthcares as $birthcare) {
            $this->command->info("Creating patient charges for {$birthcare->facility_name} (ID: {$birthcare->id})");
            
            foreach ($services as $service) {
                // Check if service already exists for this birthcare
                $existingService = PatientCharge::where('birthcare_id', $birthcare->id)
                    ->where('service_name', $service['service_name'])
                    ->first();
                    
                if (!$existingService) {
                    PatientCharge::create(array_merge($service, [
                        'birthcare_id' => $birthcare->id
                    ]));
                }
            }
            
            $this->command->info("✅ Created " . count($services) . " services for {$birthcare->facility_name}");
        }
        
        $this->command->info("🎉 Patient charges seeding completed!");
    }
}
