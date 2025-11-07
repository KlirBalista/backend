<?php

namespace App\Console\Commands;

use App\Models\PatientAdmission;
use App\Models\PatientCharge;
use App\Models\PatientBill;
use App\Models\BillItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BackfillRoomCharges extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'billing:backfill-room-charges 
                            {--patient-id= : Specific patient ID to backfill}
                            {--from-date= : Start date for backfill (YYYY-MM-DD)}
                            {--to-date= : End date for backfill (YYYY-MM-DD)}
                            {--dry-run : Run without making changes}';

    /**
     * The console command description.
     */
    protected $description = 'Backfill missing daily room charges for admitted patients';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $patientId = $this->option('patient-id');
        $fromDate = $this->option('from-date');
        $toDate = $this->option('to-date') ?? now()->toDateString();
        
        if ($isDryRun) {
            $this->info('=== DRY RUN MODE - No changes will be made ===');
        }
        
        $this->info('Starting room charges backfill...');
        
        // Build admission query
        $query = PatientAdmission::with(['patient', 'room']);
        
        if ($patientId) {
            $query->where('patient_id', $patientId);
        }
        
        // Get all admissions that need backfilling
        $admissions = $query->get();
        
        $this->info("Found {$admissions->count()} admissions to process");
        
        foreach ($admissions as $admission) {
            try {
                $this->backfillPatientCharges($admission, $fromDate, $toDate, $isDryRun);
            } catch (\Exception $e) {
                $this->error("Error processing patient {$admission->patient->first_name} {$admission->patient->last_name}: " . $e->getMessage());
            }
        }
        
        $this->info('Backfill completed!');
    }
    
    private function backfillPatientCharges(PatientAdmission $admission, ?string $fromDate, string $toDate, bool $isDryRun)
    {
        $patient = $admission->patient;
        $this->line("Processing: {$patient->first_name} {$patient->last_name}");
        
        // Determine date range for this patient
        $startDate = $fromDate ?? $admission->admission_date->toDateString();
        $endDate = $admission->discharge_date ? 
            min($admission->discharge_date->toDateString(), $toDate) : 
            $toDate;
            
        $this->line("  Date range: {$startDate} to {$endDate}");
        
        // Find room accommodation charge using improved logic
        $roomCharge = $this->determineRoomCharge($admission);
            
        if (!$roomCharge) {
            $this->warn("  No room charge found for birthcare facility");
            return;
        }
        
        // Generate date range
        $currentDate = Carbon::parse($startDate);
        $endDateCarbon = Carbon::parse($endDate);
        
        $missingCharges = 0;
        $totalAmount = 0;
        
        while ($currentDate <= $endDateCarbon) {
            $dateString = $currentDate->toDateString();
            
            // Check if we've already charged for this date
            $existingCharge = BillItem::whereHas('bill', function ($query) use ($admission) {
                    $query->where('patient_id', $admission->patient_id)
                          ->where('birthcare_id', $admission->birth_care_id);
                })
                ->where('patient_charge_id', $roomCharge->id)
                ->whereDate('created_at', $dateString)
                ->exists();
                
            if (!$existingCharge) {
                $missingCharges++;
                $totalAmount += $roomCharge->price;
                
                if ($isDryRun) {
                    $this->line("    Missing charge for {$dateString}: ₱{$roomCharge->price}");
                } else {
                    $this->addDailyCharge($admission, $roomCharge, $dateString);
                    $this->line("    ✓ Added charge for {$dateString}: ₱{$roomCharge->price}");
                }
            }
            
            $currentDate->addDay();
        }
        
        if ($missingCharges > 0) {
            if ($isDryRun) {
                $this->info("  Would add {$missingCharges} missing charges totaling ₱{$totalAmount}");
            } else {
                $this->info("  ✓ Added {$missingCharges} missing charges totaling ₱{$totalAmount}");
            }
        } else {
            $this->line("  No missing charges found");
        }
    }
    
    private function addDailyCharge(PatientAdmission $admission, PatientCharge $roomCharge, string $date)
    {
        // Find or create active bill
        $patientBill = PatientBill::where('patient_id', $admission->patient_id)
            ->where('birthcare_id', $admission->birth_care_id)
            ->whereIn('status', ['draft', 'sent', 'partially_paid', 'overdue'])
            ->where('balance_amount', '>', 0)
            ->first();
            
        if (!$patientBill) {
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
        
        // Add bill item for this date's room charge
        $billItem = BillItem::create([
            'patient_bill_id' => $patientBill->id,
            'patient_charge_id' => $roomCharge->id,
            'service_name' => $roomCharge->service_name . ' (' . $date . ')',
            'description' => "Daily room accommodation for " . $date,
            'quantity' => 1,
            'unit_price' => $roomCharge->price,
            'total_price' => $roomCharge->price,
            'created_at' => Carbon::parse($date)->endOfDay(), // Set created_at to the charge date
            'updated_at' => now(),
        ]);
        
        // Update bill totals
        $patientBill->total_amount += $roomCharge->price;
        $patientBill->balance_amount = $patientBill->total_amount - $patientBill->paid_amount;
        $patientBill->save();
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
