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
        Schema::create('labor_monitoring', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->onDelete('cascade');
            $table->foreignId('birth_care_id')->constrained('birth_cares')->onDelete('cascade');
            $table->date('monitoring_date');
            $table->time('monitoring_time');
            $table->string('temperature', 10)->nullable();
            $table->string('pulse', 10)->nullable();
            $table->string('respiration', 10)->nullable();
            $table->string('blood_pressure', 20)->nullable();
            $table->string('fht_location', 100)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index(['patient_id', 'monitoring_date', 'monitoring_time'], 'labor_monitoring_patient_date_time_idx');
            $table->index(['birth_care_id', 'monitoring_date'], 'labor_monitoring_birthcare_date_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('labor_monitoring');
    }
};