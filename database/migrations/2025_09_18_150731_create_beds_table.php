<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('beds', function (Blueprint $table) {
            $table->id();
            $table->integer('bed_no');
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            // Indexes for better performance
            $table->index(['room_id']);
            $table->unique(['room_id', 'bed_no']); // Bed numbers should be unique per room
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('beds');
    }
};
