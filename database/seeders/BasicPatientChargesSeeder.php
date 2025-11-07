<?php

namespace Database\Seeders;

use App\Models\PatientCharge;
use App\Models\BirthCare;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BasicPatientChargesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all birth care centers to add basic services
        $birthCares = BirthCare::all();
        
        $basicServices = [
            [
                'service_name' => 'Prenatal Checkup',
                'description' => 'Regular prenatal examination and consultation',
                'price' => 500.00
            ],
            [
                'service_name' => 'Ultrasound',
                'description' => '2D ultrasound examination',
                'price' => 800.00
            ],
            [
                'service_name' => 'Laboratory Test',
                'description' => 'Basic laboratory tests for pregnancy',
                'price' => 300.00
            ],
            [
                'service_name' => 'Delivery Package',
                'description' => 'Normal delivery package',
                'price' => 15000.00
            ],
            [
                'service_name' => 'Cesarean Delivery',
                'description' => 'Cesarean section delivery package',
                'price' => 25000.00
            ]
        ];
        
        foreach ($birthCares as $birthCare) {
            foreach ($basicServices as $service) {
                PatientCharge::create([
                    'birthcare_id' => $birthCare->id,
                    'service_name' => $service['service_name'],
                    'description' => $service['description'],
                    'price' => $service['price'],
                    'is_active' => true
                ]);
            }
        }
    }
}