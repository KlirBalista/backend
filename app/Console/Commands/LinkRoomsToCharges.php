<?php

namespace App\Console\Commands;

use App\Models\Room;
use App\Models\PatientCharge;
use Illuminate\Console\Command;

class LinkRoomsToCharges extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'rooms:link-charges 
                            {--birthcare-id= : Specific birthcare facility ID}
                            {--auto-link : Automatically link rooms based on name/type matching}
                            {--dry-run : Show what would be linked without making changes}';

    /**
     * The console command description.
     */
    protected $description = 'Link rooms to their appropriate patient charges for accurate billing';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $birthcareId = $this->option('birthcare-id');
        $autoLink = $this->option('auto-link');
        $isDryRun = $this->option('dry-run');
        
        if ($isDryRun) {
            $this->info('=== DRY RUN MODE - No changes will be made ===');
        }
        
        $this->info('Starting room to charge linking process...');
        
        // Get rooms to process
        $query = Room::with('patientCharge');
        if ($birthcareId) {
            $query->where('birth_care_id', $birthcareId);
        }
        $rooms = $query->get();
        
        $this->info("Found {$rooms->count()} rooms to process");
        
        $linked = 0;
        $alreadyLinked = 0;
        $noMatch = 0;
        
        foreach ($rooms as $room) {
            $this->line("\nProcessing Room: {$room->name} (Type: {$room->room_type})");
            
            // Skip if already linked
            if ($room->patient_charge_id && $room->patientCharge) {
                $this->info("  Already linked to: {$room->patientCharge->service_name}");
                $alreadyLinked++;
                continue;
            }
            
            // Find matching charge
            $charge = $this->findMatchingCharge($room);
            
            if ($charge) {
                if ($autoLink) {
                    if (!$isDryRun) {
                        $room->patient_charge_id = $charge->id;
                        $room->save();
                    }
                    $this->info("  ✓ " . ($isDryRun ? 'Would link' : 'Linked') . " to: {$charge->service_name} (₱{$charge->price})");
                    $linked++;
                } else {
                    $this->warn("  Found potential match: {$charge->service_name} (₱{$charge->price})");
                    $this->warn("  Use --auto-link to automatically link, or update manually");
                }
            } else {
                $this->error("  No matching charge found");
                $this->error("  Consider creating a PatientCharge for '{$room->room_type}' or '{$room->name}'");
                $noMatch++;
            }
        }
        
        // Summary
        $this->info("\n=== Summary ===");
        $this->info("Already linked: {$alreadyLinked}");
        $this->info(($isDryRun ? 'Would link: ' : 'Newly linked: ') . $linked);
        $this->info("No match found: {$noMatch}");
        
        if ($noMatch > 0 && !$isDryRun) {
            $this->warn("\nConsider creating PatientCharges for unmatched room types.");
            $this->warn("Or run with --dry-run to see what would be matched first.");
        }
        
        if ($linked > 0 && !$autoLink) {
            $this->info("\nRun with --auto-link to apply the suggested linkings.");
        }
        
        $this->info('Room linking process completed!');
    }
    
    /**
     * Find a matching PatientCharge for the given room.
     */
    private function findMatchingCharge(Room $room)
    {
        $roomType = $room->room_type;
        $roomName = $room->name;
        
        // Try to match by room type first
        if ($roomType) {
            $charge = PatientCharge::where('birthcare_id', $room->birth_care_id)
                ->where('is_active', true)
                ->where(function ($query) use ($roomType) {
                    $query->where('service_name', 'like', '%' . $roomType . '%')
                          ->orWhere('description', 'like', '%' . $roomType . '%');
                })
                ->first();
                
            if ($charge) {
                return $charge;
            }
        }
        
        // Try to match by room name
        if ($roomName) {
            $charge = PatientCharge::where('birthcare_id', $room->birth_care_id)
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
                return $charge;
            }
        }
        
        return null;
    }
}