<?php

namespace Database\Seeders;

use App\Models\BirthCare;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class BirthcareSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create test owners for birthcare facilities in Davao City only
        $owners = [
            [
                'firstname' => 'Maria',
                'middlename' => 'Santos',
                'lastname' => 'Cruz',
                'contact_number' => '09171234567',
                'address' => 'Bangkal, Davao City, Philippines',
                'email' => 'maria.santos.cruz@davaohealthcenter.com',
                'password' => Hash::make('password123'),
                'status' => 'active',
                'system_role_id' => 2,
                'email_verified_at' => now(),
            ],
            [
                'firstname' => 'Ana',
                'middlename' => 'Reyes',
                'lastname' => 'Gonzales',
                'contact_number' => '09181234567',
                'address' => 'Poblacion, Davao City, Philippines',
                'email' => 'ana.reyes.gonzales@davaoclinic.com',
                'password' => Hash::make('password123'),
                'status' => 'active',
                'system_role_id' => 2,
                'email_verified_at' => now(),
            ],
            [
                'firstname' => 'Carmen',
                'middlename' => 'Torres',
                'lastname' => 'Dela Rosa',
                'contact_number' => '09191234567',
                'address' => 'Buhangin, Davao City, Philippines',
                'email' => 'carmen.torres.delarosa@davaobirthcare.com',
                'password' => Hash::make('password123'),
                'status' => 'active',
                'system_role_id' => 2,
                'email_verified_at' => now(),
            ],
        ];

        $birthcares = [
            [
                'name' => 'Sacred Heart Birthing Center',
                'description' => 'A comprehensive birthing center offering prenatal care, natural birth services, and postnatal support in Bangkal, Davao City.',
                'latitude' => 7.0731,
                'longitude' => 125.6128,
                'is_public' => true,
                'status' => 'approved',
            ],
            [
                'name' => 'Davao Maternal Health Clinic',
                'description' => 'Specialized maternal and newborn healthcare facility providing quality birthing services in Poblacion, Davao City.',
                'latitude' => 7.0644,
                'longitude' => 125.6081,
                'is_public' => true,
                'status' => 'approved',
            ],
            [
                'name' => 'Buhangin Family Birthing Home',
                'description' => 'Family-centered birthing facility offering personalized care and support throughout pregnancy and delivery in Buhangin, Davao City.',
                'latitude' => 7.0997,
                'longitude' => 125.6147,
                'is_public' => false,
                'status' => 'approved',
            ],
        ];

        foreach ($owners as $index => $ownerData) {
            // Create or get the owner
            $owner = User::updateOrCreate(
                ['email' => $ownerData['email']],
                $ownerData
            );

            // Create the birthcare facility
            BirthCare::updateOrCreate(
                [
                    'user_id' => $owner->id,
                    'name' => $birthcares[$index]['name'],
                ],
                [
                    'description' => $birthcares[$index]['description'],
                    'latitude' => $birthcares[$index]['latitude'],
                    'longitude' => $birthcares[$index]['longitude'],
                    'is_public' => $birthcares[$index]['is_public'],
                    'status' => $birthcares[$index]['status'],
                ]
            );
        }
    }
}