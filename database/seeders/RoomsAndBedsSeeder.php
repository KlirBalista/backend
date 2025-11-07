<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Room;
use App\Models\Bed;
use App\Models\BirthCare;

class RoomsAndBedsSeeder extends Seeder
{
    public function run(): void
    {
        // Find the first birth care facility
        $birthCare = BirthCare::first();
        
        if (!$birthCare) {
            $this->command->info('No birth care facility found. Please create one first.');
            return;
        }

        // Create sample rooms
        $rooms = [
            ['name' => '101', 'beds' => 5],
            ['name' => '102', 'beds' => 4],
            ['name' => '103', 'beds' => 3],
            ['name' => '104', 'beds' => 2],
            ['name' => '105', 'beds' => 1],
        ];

        foreach ($rooms as $roomData) {
            // Create the room
            $room = Room::create([
                'name' => $roomData['name'],
                'birth_care_id' => $birthCare->id,
            ]);

            // Create beds for the room
            for ($i = 1; $i <= $roomData['beds']; $i++) {
                Bed::create([
                    'bed_no' => $i,
                    'room_id' => $room->id,
                ]);
            }

            $this->command->info("Created room {$roomData['name']} with {$roomData['beds']} beds");
        }

        $this->command->info('Sample rooms and beds created successfully!');
    }
}