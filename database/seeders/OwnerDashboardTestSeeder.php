<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\BirthCare;
use App\Models\BirthCareStaff;
use App\Models\BirthCareRole;
use App\Models\UserBirthRole;
use App\Models\Patient;
use App\Models\PatientAdmission;
use App\Models\Room;
use App\Models\Bed;
use App\Models\PrenatalVisit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class OwnerDashboardTestSeeder extends Seeder
{
    public function run(): void
    {
        // Find or create the owner user
        $owner = User::where('email', 'owner@example.com')->first();
        
        if (!$owner) {
            $owner = User::create([
                'firstname' => 'John',
                'middlename' => 'A',
                'lastname' => 'Owner',
                'contact_number' => '09123456789',
                'address' => '123 Facility Street, Davao City',
                'email' => 'owner@example.com',
                'password' => Hash::make('password123'),
                'status' => 'active',
                'system_role_id' => 2, // owner role
                'email_verified_at' => now(),
            ]);
        }

        // Create or update the birthcare facility
        $birthcare = BirthCare::updateOrCreate(
            ['user_id' => $owner->id],
            [
                'name' => 'Buhangin Medical Center',
                'description' => 'Door 2, Grantess Building, Kilometer 5, Buhangin, Davao City, Davao del Sur, Philippines',
                'latitude' => 7.125,
                'longitude' => 125.22,
                'is_public' => true,
                'status' => 'approved',
            ]
        );

        // Create some rooms and beds
        $rooms = [
            ['name' => 'Labor Room 1'],
            ['name' => 'Labor Room 2'],
            ['name' => 'Delivery Room'],
            ['name' => 'Recovery Room A'],
            ['name' => 'Recovery Room B'],
        ];

        foreach ($rooms as $roomData) {
            $room = Room::updateOrCreate(
                ['name' => $roomData['name'], 'birth_care_id' => $birthcare->id],
                ['name' => $roomData['name'], 'birth_care_id' => $birthcare->id]
            );

            // Create 2-4 beds per room
            $bedCount = rand(2, 4);
            for ($i = 1; $i <= $bedCount; $i++) {
                Bed::updateOrCreate(
                    ['room_id' => $room->id, 'bed_no' => $i],
                    ['room_id' => $room->id, 'bed_no' => $i]
                );
            }
        }

        // Create some staff roles (simplified)
        $roles = [
            ['name' => 'Head Nurse'],
            ['name' => 'Nurse'], 
            ['name' => 'Midwife'],
            ['name' => 'Doctor'],
        ];

        $createdRoles = [];
        foreach ($roles as $roleData) {
            $role = BirthCareRole::updateOrCreate(
                ['role_name' => $roleData['name'], 'birth_care_id' => $birthcare->id],
                ['role_name' => $roleData['name'], 'birth_care_id' => $birthcare->id, 'timestamp' => now()]
            );
            $createdRoles[$roleData['name']] = $role;
        }

        // Create some staff members
        $staffMembers = [
            [
                'firstname' => 'Maria',
                'lastname' => 'Santos',
                'email' => 'maria.santos@buhangin.com',
                'role' => 'Head Nurse'
            ],
            [
                'firstname' => 'Ana',
                'lastname' => 'Cruz',
                'email' => 'ana.cruz@buhangin.com',
                'role' => 'Nurse'
            ],
            [
                'firstname' => 'Rosa',
                'lastname' => 'Garcia',
                'email' => 'rosa.garcia@buhangin.com',
                'role' => 'Midwife'
            ],
            [
                'firstname' => 'Dr. Juan',
                'lastname' => 'Dela Cruz',
                'email' => 'juan.delacruz@buhangin.com',
                'role' => 'Doctor'
            ],
        ];

        foreach ($staffMembers as $staffData) {
            $staffUser = User::updateOrCreate(
                ['email' => $staffData['email']],
                [
                    'firstname' => $staffData['firstname'],
                    'lastname' => $staffData['lastname'],
                    'email' => $staffData['email'],
                    'password' => Hash::make('password123'),
                    'contact_number' => '091' . rand(10000000, 99999999),
                    'address' => 'Davao City',
                    'status' => 'active',
                    'system_role_id' => 3, // staff role
                    'email_verified_at' => now(),
                ]
            );

            // Create staff record
            BirthCareStaff::updateOrCreate(
                ['user_id' => $staffUser->id, 'birth_care_id' => $birthcare->id],
                [
                    'user_id' => $staffUser->id,
                    'birth_care_id' => $birthcare->id,
                ]
            );
            
            // Optionally create role assignment
            if (isset($createdRoles[$staffData['role']])) {
                UserBirthRole::updateOrCreate(
                    ['user_id' => $staffUser->id, 'birth_care_id' => $birthcare->id],
                    [
                        'user_id' => $staffUser->id,
                        'birth_care_id' => $birthcare->id,
                        'role_id' => $createdRoles[$staffData['role']]->id,
                    ]
                );
            }
        }

        // Create some patients
        $patients = [
            [
                'first_name' => 'Emma',
                'last_name' => 'Johnson',
                'date_of_birth' => '1995-03-15',
                'age' => 28,
            ],
            [
                'first_name' => 'Sophia',
                'last_name' => 'Williams',
                'date_of_birth' => '1992-07-22',
                'age' => 31,
            ],
            [
                'first_name' => 'Isabella',
                'last_name' => 'Brown',
                'date_of_birth' => '1990-11-08',
                'age' => 33,
            ],
            [
                'first_name' => 'Olivia',
                'last_name' => 'Davis',
                'date_of_birth' => '1993-05-12',
                'age' => 30,
            ],
            [
                'first_name' => 'Ava',
                'last_name' => 'Miller',
                'date_of_birth' => '1994-09-25',
                'age' => 29,
            ],
        ];

        $createdPatients = [];
        foreach ($patients as $patientData) {
            $patient = Patient::updateOrCreate(
                [
                    'first_name' => $patientData['first_name'],
                    'last_name' => $patientData['last_name'],
                    'birth_care_id' => $birthcare->id
                ],
                [
                    'first_name' => $patientData['first_name'],
                    'last_name' => $patientData['last_name'],
                    'date_of_birth' => $patientData['date_of_birth'],
                    'age' => $patientData['age'],
                    'civil_status' => 'married',
                    'address' => 'Davao City',
                    'contact_number' => '091' . rand(10000000, 99999999),
                    'philhealth_number' => rand(100000000000, 999999999999),
                    'philhealth_category' => 'member',
                    'birth_care_id' => $birthcare->id,
                    'status' => 'active',
                ]
            );
            $createdPatients[] = $patient;
        }

        // Create some patient admissions with varied dates and statuses
        $rooms = Room::where('birth_care_id', $birthcare->id)->get();
        $beds = Bed::whereIn('room_id', $rooms->pluck('id'))->get();
        
        $admissionStatuses = ['in-labor', 'delivered', 'discharged'];
        
        foreach ($createdPatients as $index => $patient) {
            $room = $rooms->random();
            $bed = $beds->where('room_id', $room->id)->random();
            $status = $admissionStatuses[$index % 3];
            
            // Create admissions with different dates for variety
            $admissionDate = Carbon::now()->subDays(rand(1, 30));
            
            PatientAdmission::updateOrCreate(
                [
                    'patient_id' => $patient->id,
                    'birth_care_id' => $birthcare->id,
                    'admission_date' => $admissionDate->format('Y-m-d'),
                ],
                [
                    'patient_id' => $patient->id,
                    'birth_care_id' => $birthcare->id,
                    'admission_date' => $admissionDate->format('Y-m-d'),
                    'admission_time' => $admissionDate->format('H:i:s'),
                    'admission_type' => 'regular',
                    'chief_complaint' => 'Labor pains',
                    'reason_for_admission' => 'Normal delivery',
                    'status' => $status,
                    'room_id' => $room->id,
                    'bed_id' => $bed->id,
                    'admitted_by' => $owner->id,
                    'updated_at' => $status === 'delivered' ? $admissionDate->addHours(rand(2, 24)) : now(),
                ]
            );
        }

        // Skip prenatal visits for now to focus on basic dashboard data

        echo "✅ Sample data created successfully!\n";
        echo "👤 Owner: {$owner->email}\n";
        echo "🏥 Facility: {$birthcare->name}\n";
        echo "👥 Staff: " . BirthCareStaff::where('birth_care_id', $birthcare->id)->count() . "\n";
        echo "🤱 Patients: " . Patient::where('birth_care_id', $birthcare->id)->count() . "\n";
        echo "🛏️ Rooms: " . Room::where('birth_care_id', $birthcare->id)->count() . "\n";
        echo "🪑 Beds: " . Bed::whereIn('room_id', Room::where('birth_care_id', $birthcare->id)->pluck('id'))->count() . "\n";
        echo "📋 Admissions: " . PatientAdmission::where('birth_care_id', $birthcare->id)->count() . "\n";
    }
}