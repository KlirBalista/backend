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
            // Drop the legacy room_number and bed_number columns
            // These have been replaced by room_id and bed_id foreign keys
            $table->dropColumn(['room_number', 'bed_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patient_admissions', function (Blueprint $table) {
            // Re-add the legacy columns if rollback is needed
            $table->string('room_number')->nullable()->after('attending_physician');
            $table->string('bed_number')->nullable()->after('room_number');
        });
    }
};
