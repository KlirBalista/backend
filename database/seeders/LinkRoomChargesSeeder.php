<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Room;
use App\Models\PatientCharge;
use App\Models\BirthCare;

class LinkRoomChargesSeeder extends Seeder
{
    /**
     * Run the database seeder to link rooms with their appropriate charges.
     * 
     * This seeder ensures that each room is linked to the correct room charge
     * based on its room_type (Private or Semi-Private).
     */
    public function run(): void
    {
        $this->command->info('Starting to link rooms with their charges...');
        
        // Get all birth care facilities
        $birthCares = BirthCare::all();
        
        if ($birthCares->isEmpty()) {
            $this->command->warn('No birth care facilities found.');
            return;
        }
        
        foreach ($birthCares as $birthCare) {
            $this->command->info("\nProcessing facility: {$birthCare->facility_name}");
            
            // Get all rooms for this facility
            $rooms = Room::where('birth_care_id', $birthCare->id)->get();
            
            if ($rooms->isEmpty()) {
                $this->command->warn("  No rooms found for this facility.");
                continue;
            }
            
            // Get the room charges for this facility
            $privateRoomCharge = PatientCharge::where('birthcare_id', $birthCare->id)
                ->where('service_name', 'LIKE', '%Private%')
                ->where('service_name', 'NOT LIKE', '%Semi%')
                ->where('is_active', true)
                ->first();
                
            $semiPrivateRoomCharge = PatientCharge::where('birthcare_id', $birthCare->id)
                ->where('service_name', 'LIKE', '%Semi-Private%')
                ->where('is_active', true)
                ->first();
            
            if (!$privateRoomCharge) {
                $this->command->warn("  Private room charge not found!");
            } else {
                $this->command->info("  Found: {$privateRoomCharge->service_name} - ₱{$privateRoomCharge->price}");
            }
            
            if (!$semiPrivateRoomCharge) {
                $this->command->warn("  Semi-private room charge not found!");
            } else {
                $this->command->info("  Found: {$semiPrivateRoomCharge->service_name} - ₱{$semiPrivateRoomCharge->price}");
            }
            
            // Link each room to the appropriate charge based on room_type
            foreach ($rooms as $room) {
                $oldChargeId = $room->patient_charge_id;
                $newChargeId = null;
                $chargeType = 'Unknown';
                
                if ($room->room_type) {
                    $roomTypeLower = strtolower($room->room_type);
                    
                    // Check if it's a private room (not semi-private)
                    if (str_contains($roomTypeLower, 'private') && !str_contains($roomTypeLower, 'semi')) {
                        if ($privateRoomCharge) {
                            $newChargeId = $privateRoomCharge->id;
                            $chargeType = "Private (₱{$privateRoomCharge->price})";
                        }
                    }
                    // Check if it's a semi-private room
                    elseif (str_contains($roomTypeLower, 'semi')) {
                        if ($semiPrivateRoomCharge) {
                            $newChargeId = $semiPrivateRoomCharge->id;
                            $chargeType = "Semi-Private (₱{$semiPrivateRoomCharge->price})";
                        }
                    }
                }
                
                if ($newChargeId) {
                    $room->update(['patient_charge_id' => $newChargeId]);
                    
                    if ($oldChargeId !== $newChargeId) {
                        $this->command->info("  ✓ Updated Room {$room->name} ({$room->room_type}) → {$chargeType}");
                    } else {
                        $this->command->line("  - Room {$room->name} already linked to {$chargeType}");
                    }
                } else {
                    $this->command->warn("  ✗ Could not link Room {$room->name} ({$room->room_type}) - no matching charge");
                }
            }
        }
        
        $this->command->info("\n✅ Room charge linking completed!");
    }
}
