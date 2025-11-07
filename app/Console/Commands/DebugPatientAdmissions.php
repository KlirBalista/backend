<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PatientAdmission;
use App\Models\Patient;
use App\Models\BirthCare;
use App\Models\Room;
use App\Models\User;

class DebugPatientAdmissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'debug:patient-admissions {birthcare_id?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Debug patient admissions data for a specific birthcare facility';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $birthcareId = $this->argument('birthcare_id');
        
        if (!$birthcareId) {
            // Show all birthcare facilities
            $birthcares = BirthCare::all();
            if ($birthcares->isEmpty()) {
                $this->error('No birthcare facilities found in the database.');
                return;
            }
            
            $this->info('Available Birthcare Facilities:');
            $this->table(['ID', 'Name', 'Email'], $birthcares->map(function($bc) {
                return [$bc->id, $bc->facility_name ?? 'N/A', $bc->email ?? 'N/A'];
            }));
            
            $birthcareId = $this->ask('Please enter the birthcare_id to debug');
        }

        $birthcare = BirthCare::find($birthcareId);
        if (!$birthcare) {
            $this->error("Birthcare facility with ID {$birthcareId} not found.");
            return;
        }

        $this->info("Debugging Patient Admissions for: {$birthcare->facility_name} (ID: {$birthcareId})");
        $this->line('');

        // Check patients
        $patients = Patient::where('birth_care_id', $birthcareId)->get();
        $this->info("Total Patients: {$patients->count()}");
        if ($patients->count() > 0) {
            $this->table(['ID', 'Name', 'Age', 'Created'], $patients->take(5)->map(function($p) {
                return [
                    $p->id, 
                    "{$p->first_name} {$p->last_name}", 
                    $p->age ?? 'N/A', 
                    $p->created_at->format('Y-m-d H:i')
                ];
            }));
            if ($patients->count() > 5) {
                $this->info("... and " . ($patients->count() - 5) . " more patients");
            }
        }
        $this->line('');

        // Check rooms
        $rooms = Room::where('birth_care_id', $birthcareId)->get();
        $this->info("Total Rooms: {$rooms->count()}");
        if ($rooms->count() > 0) {
            $this->table(['ID', 'Name', 'Type'], $rooms->take(5)->map(function($r) {
                return [$r->id, $r->name ?? 'N/A', $r->room_type ?? 'N/A'];
            }));
        }
        $this->line('');

        // Check patient admissions
        $admissions = PatientAdmission::where('birth_care_id', $birthcareId)->get();
        $this->info("Total Patient Admissions: {$admissions->count()}");
        
        if ($admissions->count() > 0) {
            // Group by status
            $statusGroups = $admissions->groupBy('status');
            $this->info("Admissions by Status:");
            foreach ($statusGroups as $status => $group) {
                $this->info("  - {$status}: {$group->count()} admissions");
            }
            $this->line('');
            
            // Show recent admissions
            $this->info("Recent Admissions:");
            $recentAdmissions = $admissions->sortByDesc('created_at')->take(5);
            $this->table(['ID', 'Patient', 'Status', 'Admission Date', 'Room'], 
                $recentAdmissions->map(function($a) {
                    $patientName = $a->patient ? "{$a->patient->first_name} {$a->patient->last_name}" : 'N/A';
                    $roomName = $a->room ? $a->room->name : ($a->room_number ?? 'N/A');
                    return [
                        $a->id,
                        $patientName,
                        $a->status,
                        $a->admission_date->format('Y-m-d'),
                        $roomName
                    ];
                })
            );
            
            // Check specifically for admitted/active patients
            $admittedPatients = $admissions->whereIn('status', ['admitted', 'active']);
            $this->line('');
            $this->info("Patients with 'admitted' or 'active' status: {$admittedPatients->count()}");
            if ($admittedPatients->count() > 0) {
                $this->table(['ID', 'Patient', 'Status', 'Admission Date', 'Room'], 
                    $admittedPatients->map(function($a) {
                        $patientName = $a->patient ? "{$a->patient->first_name} {$a->patient->last_name}" : 'N/A';
                        $roomName = $a->room ? $a->room->name : ($a->room_number ?? 'N/A');
                        return [
                            $a->id,
                            $patientName,
                            $a->status,
                            $a->admission_date->format('Y-m-d'),
                            $roomName
                        ];
                    })
                );
            }
            
        } else {
            $this->warn("No patient admissions found for this birthcare facility.");
            
            // Offer to create sample data
            if ($this->confirm('Would you like to create sample patient admission data?')) {
                $this->createSampleData($birthcareId);
            }
        }
    }
    
    private function createSampleData($birthcareId)
    {
        $this->info('Creating sample data...');
        
        // Check if we have a user to assign as admitter
        $user = User::first();
        if (!$user) {
            $this->error('No users found. Please create a user first.');
            return;
        }
        
        // Create sample patients if none exist
        $patients = Patient::where('birth_care_id', $birthcareId)->get();
        if ($patients->count() === 0) {
            $this->info('Creating sample patients...');
            $samplePatients = [
                [
                    'first_name' => 'Maria',
                    'middle_name' => 'Cruz',
                    'last_name' => 'Santos',
                    'age' => 28,
                    'birth_care_id' => $birthcareId,
                    'date_of_birth' => '1996-03-15',
                    'phone_number' => '09123456789',
                    'address' => 'Quezon City, Metro Manila',
                ],
                [
                    'first_name' => 'Anna',
                    'middle_name' => 'Lopez',
                    'last_name' => 'Garcia',
                    'age' => 25,
                    'birth_care_id' => $birthcareId,
                    'date_of_birth' => '1999-07-22',
                    'phone_number' => '09234567890',
                    'address' => 'Makati City, Metro Manila',
                ],
                [
                    'first_name' => 'Carmen',
                    'middle_name' => 'Torres',
                    'last_name' => 'Rodriguez',
                    'age' => 32,
                    'birth_care_id' => $birthcareId,
                    'date_of_birth' => '1992-11-08',
                    'phone_number' => '09345678901',
                    'address' => 'Pasig City, Metro Manila',
                ],
            ];
            
            foreach ($samplePatients as $patientData) {
                $patients[] = Patient::create($patientData);
            }
            $this->info('Created ' . count($samplePatients) . ' sample patients.');
        }
        
        // Create sample rooms if none exist
        $rooms = Room::where('birth_care_id', $birthcareId)->get();
        if ($rooms->count() === 0) {
            $this->info('Creating sample rooms...');
            $sampleRooms = [
                ['name' => '201', 'room_type' => 'Private', 'birth_care_id' => $birthcareId],
                ['name' => '102', 'room_type' => 'Semi-Private', 'birth_care_id' => $birthcareId],
                ['name' => '301', 'room_type' => 'Private', 'birth_care_id' => $birthcareId],
            ];
            
            foreach ($sampleRooms as $roomData) {
                $rooms[] = Room::create($roomData);
            }
            $this->info('Created ' . count($sampleRooms) . ' sample rooms.');
        }
        
        // Create sample patient admissions
        $this->info('Creating sample patient admissions...');
        $admissionData = [
            [
                'patient_id' => $patients[0]->id,
                'birth_care_id' => $birthcareId,
                'room_id' => $rooms[0]->id,
                'admission_date' => now()->subDays(2),
                'admission_time' => '08:30:00',
                'admission_type' => 'scheduled',
                'chief_complaint' => 'Regular prenatal check-up',
                'status' => 'admitted',
                'admitted_by' => $user->id,
            ],
            [
                'patient_id' => $patients[1]->id,
                'birth_care_id' => $birthcareId,
                'room_id' => $rooms[1]->id,
                'admission_date' => now()->subDays(1),
                'admission_time' => '14:15:00',
                'admission_type' => 'emergency',
                'chief_complaint' => 'Labor pains',
                'status' => 'active',
                'admitted_by' => $user->id,
            ],
            [
                'patient_id' => $patients[2]->id,
                'birth_care_id' => $birthcareId,
                'room_id' => $rooms[2]->id,
                'admission_date' => now(),
                'admission_time' => '10:45:00',
                'admission_type' => 'scheduled',
                'chief_complaint' => 'Scheduled cesarean section',
                'status' => 'admitted',
                'admitted_by' => $user->id,
            ],
        ];
        
        foreach ($admissionData as $admission) {
            PatientAdmission::create($admission);
        }
        
        $this->info('Created ' . count($admissionData) . ' sample patient admissions.');
        $this->line('');
        $this->info('✅ Sample data created successfully!');
        $this->info('You can now refresh the Patient Charges page to see the admitted patients.');
    }
}
