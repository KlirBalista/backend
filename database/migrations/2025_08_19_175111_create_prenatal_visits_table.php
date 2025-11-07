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
        Schema::create('prenatal_visits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('patient_id');
            
            // Simple visit scheduling for chart display
            $table->integer('visit_number'); // 1-8 based on WHO schedule
            $table->string('visit_name'); // e.g., "First visit (before 12 weeks)"
            $table->integer('recommended_week'); // Week of pregnancy when visit is recommended
            $table->date('scheduled_date');
            $table->enum('status', ['Scheduled', 'Completed', 'Missed'])->default('Scheduled');
            
            $table->timestamps();
            
            // Foreign key constraints
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');
            
            // Indexes for calendar queries
            $table->index('scheduled_date');
            $table->index(['patient_id', 'visit_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prenatal_visits');
    }
};
