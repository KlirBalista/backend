<?php

namespace App\Console\Commands;

use App\Models\PatientAdmission;
use App\Models\PatientCharge;
use App\Models\PatientBill;
use App\Models\BillItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ChargeDailyRoomAccommodation extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'billing:charge-daily-rooms {--dry-run : Run without making changes}';

    /**
     * The console command description.
     */
    protected $description = 'Automatically charge daily room accommodation for all admitted patients';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        
        if ($isDryRun) {
            $this->info('=== DRY RUN MODE - No changes will be made ===');
        }
        
        $this->info('Starting daily room accommodation charging...');
        $today = now()->toDateString();
        
        // Get all currently admitted patients
        $admittedPatients = PatientAdmission::with(['patient', 'room'])
            ->whereIn('status', ['admitted', 'active', 'in-labor', 'delivered'])
            ->whereNull('discharge_date')
            ->get();
            
        $this->info("Found {$admittedPatients->count()} admitted patients");
        
        foreach ($admittedPatients as $admission) {
            try {
                $this->processPatientDailyCharge($admission, $today, $isDryRun);
            } catch (\Exception $e) {
                $this->error("Error processing patient {$admission->patient->first_name} {$admission->patient->last_name}: " . $e->getMessage());
            }
        }
        
        $this->info('Daily room charging completed!');
    }
    
    private function processPatientDailyCharge(PatientAdmission $admission, string $today, bool $isDryRun)
    {
        $patient = $admission->patient;
        $this->line("Processing: {$patient->first_name} {$patient->last_name}");
        
        // Determine the correct room charge based on patient's actual room assignment
        $roomCharge = $this->determineRoomCharge($admission);
            
        if (!$roomCharge) {
            $this->warn("  No appropriate room charge found for this patient's room type");
            return;
        }
        
        // Check if we've already charged for today OR if there are manual charges for room accommodation
        $existingChargeToday = BillItem::whereHas('bill', function ($query) use ($admission) {
                $query->where('patient_id', $admission->patient_id)
                      ->where('birthcare_id', $admission->birth_care_id);
            })
            ->where(function ($query) use ($roomCharge, $today) {
                // Check for today's automatic charge
                $query->where('patient_charge_id', $roomCharge->id)
                      ->whereDate('created_at', $today);
            })
            ->exists();
            
        // Also check for any manual room accommodation charges to prevent conflicts
        $hasManualRoomCharge = BillItem::whereHas('bill', function ($query) use ($admission) {
                $query->where('patient_id', $admission->patient_id)
                      ->where('birthcare_id', $admission->birth_care_id);
            })
            ->where(function ($query) {
                $query->where('service_name', 'like', '%room%')
                      ->orWhere('service_name', 'like', '%accommodation%')
                      ->orWhere('service_name', 'like', '%private%');
            })
            ->where('created_at', '>', now()->subHours(24)) // Manual charges within last 24 hours
            ->exists();
            
        if ($existingChargeToday) {
            $this->line("  Already charged for today - skipping");
            return;
        }
        
        if ($hasManualRoomCharge) {
            $this->line("  Manual room accommodation charges detected - skipping automatic charge to prevent conflicts");
            return;
        }
        
        // Find or create active bill
        $patientBill = PatientBill::where('patient_id', $admission->patient_id)
            ->where('birthcare_id', $admission->birth_care_id)
            ->whereIn('status', ['draft', 'sent', 'partially_paid', 'overdue'])
            ->where('balance_amount', '>', 0)
            ->first();
            
        if (!$patientBill && !$isDryRun) {
            $patientBill = PatientBill::create([
                'patient_id' => $admission->patient_id,
                'birthcare_id' => $admission->birth_care_id,
                'bill_number' => PatientBill::generateBillNumber(),
                'bill_date' => now(),
                'due_date' => now()->addDays(30),
                'total_amount' => 0,
                'paid_amount' => 0,
                'balance_amount' => 0,
                'status' => 'draft',
                'created_by' => 1, // System user
            ]);
        }
        
        $dailyRate = $roomCharge->price;
        
        if ($isDryRun) {
            $this->info("  Would charge: {$roomCharge->service_name} - ₱{$dailyRate} for {$today}");
            return;
        }
        
        // Add bill item for today's room charge
        $billItem = BillItem::create([
            'patient_bill_id' => $patientBill->id,
            'patient_charge_id' => $roomCharge->id,
            'service_name' => $roomCharge->service_name . ' (' . $today . ')',
            'description' => "Daily room accommodation for " . $today,
            'quantity' => 1,
            'unit_price' => $dailyRate,
            'total_price' => $dailyRate,
        ]);
        
        // Update bill totals
        $patientBill->total_amount += $dailyRate;
        $patientBill->balance_amount = $patientBill->total_amount - $patientBill->paid_amount;
        $patientBill->save();
        
        $this->info("  ✓ Charged: {$roomCharge->service_name} - ₱{$dailyRate} for {$today}");
    }
    
    /**
     * Determine the correct room charge based on patient's room assignment
     */
    private function determineRoomCharge(PatientAdmission $admission)
    {
        // If no room assigned, log warning and return null
        if (!$admission->room_id || !$admission->room) {
            $this->warn("  No room assigned to patient - cannot determine room charge");
            return null;
        }
        
        // Load the room with its associated patient charge
        $admission->load('room.patientCharge');
        
        // If room has a direct patient charge association, use it
        if ($admission->room->patientCharge && $admission->room->patientCharge->is_active) {
            $this->line("  Using direct room charge: {$admission->room->patientCharge->service_name}");
            return $admission->room->patientCharge;
        }
        
        // Try to find a charge that matches the room type or name
        $room = $admission->room;
        $roomType = $room->room_type;
        $roomName = $room->name;
        
        $this->line("  Room: {$roomName} (Type: {$roomType})");
        
        // Try to match by room type first
        if ($roomType) {
            $charge = PatientCharge::where('birthcare_id', $admission->birth_care_id)
                ->where('is_active', true)
                ->where(function ($query) use ($roomType) {
                    $query->where('service_name', 'like', '%' . $roomType . '%')
                          ->orWhere('description', 'like', '%' . $roomType . '%');
                })
                ->first();
                
            if ($charge) {
                $this->line("  Found charge by room type match: {$charge->service_name}");
                return $charge;
            }
        }
        
        // Try to match by room name
        if ($roomName) {
            $charge = PatientCharge::where('birthcare_id', $admission->birth_care_id)
                ->where('is_active', true)
                ->where(function ($query) use ($roomName) {
                    // Extract key terms from room name (e.g., "Semi-Private", "Private", etc.)
                    $nameWords = explode(' ', $roomName);
                    $query->where(function ($subQuery) use ($nameWords) {
                        foreach ($nameWords as $word) {
                            if (strlen($word) > 2) { // Skip short words like "1", "A", etc.
                                $subQuery->orWhere('service_name', 'like', '%' . $word . '%')
                                         ->orWhere('description', 'like', '%' . $word . '%');
                            }
                        }
                    });
                })
                ->first();
                
            if ($charge) {
                $this->line("  Found charge by room name match: {$charge->service_name}");
                return $charge;
            }
        }
        
        // Last resort: try to find the most basic room charge
        // Look for charges that contain "room" but prioritize lower-cost options
        $charge = PatientCharge::where('birthcare_id', $admission->birth_care_id)
            ->where('is_active', true)
            ->where(function ($query) {
                $query->where('service_name', 'like', '%room%')
                      ->orWhere('category', 'like', '%room%')
                      ->orWhere('category', 'like', '%accommodation%');
            })
            ->orderBy('price', 'asc') // Prefer lower cost charges as fallback
            ->first();
            
        if ($charge) {
            $this->warn("  Using fallback room charge (lowest price): {$charge->service_name}");
            $this->warn("  Consider linking Room ID {$room->id} directly to a PatientCharge");
        } else {
            $this->error("  No room charges found for this facility!");
        }
        
        return $charge;
    }
}
