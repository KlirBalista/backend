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
        Schema::create('patient_charts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('birth_care_id');
            $table->unsignedBigInteger('admission_id')->nullable();
            
            // Patient Information
            $table->json('patient_info')->nullable();
            
            // Medical History
            $table->json('medical_history')->nullable();
            
            // Admission Assessment
            $table->json('admission_assessment')->nullable();
            
            // Delivery Record
            $table->json('delivery_record')->nullable();
            
            // Newborn Care
            $table->json('newborn_care')->nullable();
            
            // Postpartum Notes
            $table->json('postpartum_notes')->nullable();
            
            // Discharge Summary
            $table->json('discharge_summary')->nullable();
            
            // Metadata
            $table->string('status')->default('draft'); // draft, completed, discharged
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            
            $table->timestamps();
            
            // Foreign key constraints
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');
            $table->foreign('birth_care_id')->references('id')->on('birth_cares')->onDelete('cascade');
            $table->foreign('admission_id')->references('id')->on('patient_admissions')->onDelete('set null');
            
            // Indexes
            $table->index(['patient_id', 'birth_care_id']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patient_charts');
    }
};