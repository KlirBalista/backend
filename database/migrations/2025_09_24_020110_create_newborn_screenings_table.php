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
        Schema::create('newborn_screenings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->onDelete('cascade');
            $table->foreignId('birthcare_id')->constrained('birth_cares')->onDelete('cascade');
            $table->string('screening_id')->unique();
            
            // Child Information
            $table->string('child_name');
            $table->date('date_of_birth');
            $table->enum('sex', ['male', 'female']);
            $table->string('birth_weight');
            $table->string('gestational_age');
            
            // Mother Information
            $table->string('mother_name');
            $table->integer('mother_age');
            $table->text('mother_address');
            $table->string('mother_phone');
            
            // Sample Collection Information
            $table->string('age_at_collection')->nullable();
            $table->enum('sample_quality', ['adequate', 'inadequate', 'contaminated'])->nullable();
            $table->enum('collection_method', ['heel-stick', 'venipuncture', 'cord-blood'])->nullable();
            $table->enum('feeding_status', ['breastfeeding', 'formula', 'mixed'])->nullable();
            $table->string('collector_name')->nullable();
            $table->string('laboratory_name')->nullable();
            
            // Screening Tests - JSON field for flexibility
            $table->json('screening_tests');
            
            // Follow-up Actions
            $table->json('followup_actions')->nullable();
            $table->text('comments')->nullable();
            
            // Status and tracking
            $table->enum('status', ['pending', 'completed', 'abnormal', 'follow-up-required'])->default('pending');
            $table->date('date_collected')->nullable();
            $table->time('time_collected')->nullable();
            $table->date('date_reported')->nullable();
            
            // Signatures
            $table->string('sample_collector_signature')->nullable();
            $table->string('lab_technician_signature')->nullable();
            $table->string('attending_physician_signature')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index(['patient_id', 'birthcare_id']);
            $table->index('screening_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('newborn_screenings');
    }
};