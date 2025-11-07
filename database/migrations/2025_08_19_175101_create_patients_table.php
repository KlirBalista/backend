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
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('birth_care_id');
            
            // Patient Information
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->date('date_of_birth');
            $table->integer('age');
            $table->enum('civil_status', ['Single', 'Married', 'Widowed', 'Separated', 'Divorced']);
            $table->string('religion')->nullable();
            $table->string('occupation')->nullable();
            $table->string('address');
            $table->string('contact_number');
            $table->string('emergency_contact_name');
            $table->string('emergency_contact_number');
            
            // Husband Information
            $table->string('husband_first_name')->nullable();
            $table->string('husband_middle_name')->nullable();
            $table->string('husband_last_name')->nullable();
            $table->date('husband_date_of_birth')->nullable();
            $table->integer('husband_age')->nullable();
            $table->string('husband_occupation')->nullable();
            
            // Medical Information
            $table->date('lmp')->nullable(); // Last Menstrual Period
            $table->date('edc')->nullable(); // Expected Date of Confinement
            $table->integer('gravida')->default(0); // Total pregnancies
            $table->integer('para')->default(0); // Births after 20 weeks
            $table->integer('term')->default(0); // Full term births
            $table->integer('preterm')->default(0); // Preterm births
            $table->integer('abortion')->default(0); // Abortions/miscarriages
            $table->integer('living_children')->default(0);
            
            // PhilHealth Information
            $table->string('philhealth_number')->nullable();
            $table->enum('philhealth_category', ['Direct', 'Indirect'])->nullable();
            $table->string('philhealth_dependent_name')->nullable();
            $table->string('philhealth_dependent_relation')->nullable();
            $table->string('philhealth_dependent_id')->nullable();
            
            // Medical History
            $table->text('medical_history')->nullable();
            $table->text('allergies')->nullable();
            $table->text('current_medications')->nullable();
            $table->text('previous_pregnancies')->nullable();
            
            // Status
            $table->enum('status', ['Active', 'Completed', 'Transferred', 'Inactive'])->default('Active');
            $table->text('notes')->nullable();
            
            $table->timestamps();
            
            // Foreign key constraint
            $table->foreign('birth_care_id')->references('id')->on('birth_cares')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
