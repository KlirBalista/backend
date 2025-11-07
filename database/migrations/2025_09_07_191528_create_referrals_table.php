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
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->onDelete('cascade');
            $table->foreignId('birth_care_id')->constrained('birth_cares')->onDelete('cascade');
            
            // Referring facility information
            $table->string('referring_facility');
            $table->string('referring_physician');
            $table->string('referring_physician_contact')->nullable();
            
            // Receiving facility information
            $table->string('receiving_facility');
            $table->string('receiving_physician')->nullable();
            $table->string('receiving_physician_contact')->nullable();
            
            // Referral details
            $table->date('referral_date');
            $table->time('referral_time');
            $table->enum('urgency_level', ['routine', 'urgent', 'emergency', 'critical'])->default('routine');
            
            // Clinical information
            $table->text('reason_for_referral');
            $table->text('clinical_summary')->nullable();
            $table->text('current_diagnosis')->nullable();
            $table->text('relevant_history')->nullable();
            $table->text('current_medications')->nullable();
            $table->text('allergies')->nullable();
            $table->text('vital_signs')->nullable();
            
            // Test results and treatment
            $table->text('laboratory_results')->nullable();
            $table->text('imaging_results')->nullable();
            $table->text('treatment_provided')->nullable();
            
            // Transfer details
            $table->string('patient_condition', 100)->nullable();
            $table->enum('transportation_mode', ['ambulance', 'private_transport', 'helicopter', 'wheelchair', 'stretcher'])->default('ambulance');
            $table->string('accompanies_patient')->nullable();
            $table->text('special_instructions')->nullable();
            $table->string('equipment_required')->nullable();
            $table->string('isolation_precautions')->nullable();
            $table->string('anticipated_care_level', 100)->nullable();
            $table->string('expected_duration')->nullable();
            
            // Contact and insurance information
            $table->text('insurance_information')->nullable();
            $table->string('family_contact_name')->nullable();
            $table->string('family_contact_phone', 50)->nullable();
            $table->string('family_contact_relationship', 100)->nullable();
            
            // Status and tracking
            $table->enum('status', ['pending', 'accepted', 'completed', 'cancelled'])->default('pending');
            $table->text('notes')->nullable();
            
            // User tracking
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            
            $table->timestamps();
            
            // Indexes for better performance
            $table->index(['birth_care_id', 'referral_date']);
            $table->index(['birth_care_id', 'status']);
            $table->index(['birth_care_id', 'urgency_level']);
            $table->index(['patient_id', 'referral_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referrals');
    }
};
