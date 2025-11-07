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
        Schema::table('patient_admissions', function (Blueprint $table) {
            // Add foreign key columns
            $table->foreignId('room_id')->nullable()->after('attending_physician');
            $table->foreignId('bed_id')->nullable()->after('room_id');
            
            // Add foreign key constraints
            $table->foreign('room_id')->references('id')->on('rooms')->nullOnDelete();
            $table->foreign('bed_id')->references('id')->on('beds')->nullOnDelete();
            
            // Add indexes for better performance
            $table->index(['room_id']);
            $table->index(['bed_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patient_admissions', function (Blueprint $table) {
            // Drop foreign key constraints first
            $table->dropForeign(['room_id']);
            $table->dropForeign(['bed_id']);
            
            // Drop indexes
            $table->dropIndex(['room_id']);
            $table->dropIndex(['bed_id']);
            
            // Drop columns
            $table->dropColumn(['room_id', 'bed_id']);
        });
    }
};
