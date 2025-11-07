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
        Schema::create('patient_admissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('birth_care_id')->constrained()->cascadeOnDelete();
            $table->date('admission_date');
            $table->time('admission_time');
            $table->string('admission_type'); // emergency, scheduled, transfer, etc.
            $table->string('chief_complaint')->nullable();
            $table->text('present_illness')->nullable();
            $table->text('medical_history')->nullable();
            $table->text('allergies')->nullable();
            $table->text('current_medications')->nullable();
            $table->string('vital_signs_temperature')->nullable();
            $table->string('vital_signs_blood_pressure')->nullable();
            $table->string('vital_signs_heart_rate')->nullable();
            $table->string('vital_signs_respiratory_rate')->nullable();
            $table->string('vital_signs_oxygen_saturation')->nullable();
            $table->decimal('weight', 5, 2)->nullable();
            $table->decimal('height', 5, 2)->nullable();
            $table->text('physical_examination')->nullable();
            $table->text('initial_diagnosis')->nullable();
            $table->text('treatment_plan')->nullable();
            $table->string('attending_physician')->nullable();
            $table->string('room_number')->nullable();
            $table->string('bed_number')->nullable();
            $table->enum('status', ['admitted', 'discharged', 'transferred', 'active'])->default('admitted');
            $table->text('notes')->nullable();
            $table->foreignId('admitted_by')->constrained('users');
            $table->timestamps();

            // Indexes for better performance
            $table->index(['patient_id', 'admission_date']);
            $table->index(['birth_care_id', 'status']);
            $table->index(['admission_date', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patient_admissions');
    }
};
