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
        Schema::table('patients', function (Blueprint $table) {
            // Remove non-essential personal information
            $table->dropColumn('religion');
            $table->dropColumn('occupation');
            $table->dropColumn('emergency_contact_name');
            $table->dropColumn('emergency_contact_number');
            
            // Remove all husband information
            $table->dropColumn('husband_first_name');
            $table->dropColumn('husband_middle_name');
            $table->dropColumn('husband_last_name');
            $table->dropColumn('husband_date_of_birth');
            $table->dropColumn('husband_age');
            $table->dropColumn('husband_occupation');
            
            // Remove pregnancy/medical information
            $table->dropColumn('lmp');
            $table->dropColumn('edc');
            $table->dropColumn('gravida');
            $table->dropColumn('para');
            $table->dropColumn('term');
            $table->dropColumn('preterm');
            $table->dropColumn('abortion');
            $table->dropColumn('living_children');
            
            // Remove medical history information
            $table->dropColumn('medical_history');
            $table->dropColumn('allergies');
            $table->dropColumn('current_medications');
            $table->dropColumn('previous_pregnancies');
            $table->dropColumn('notes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            // Add back non-essential personal information
            $table->string('religion')->nullable();
            $table->string('occupation')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_number')->nullable();
            
            // Add back husband information
            $table->string('husband_first_name')->nullable();
            $table->string('husband_middle_name')->nullable();
            $table->string('husband_last_name')->nullable();
            $table->date('husband_date_of_birth')->nullable();
            $table->integer('husband_age')->nullable();
            $table->string('husband_occupation')->nullable();
            
            // Add back pregnancy/medical information
            $table->date('lmp')->nullable();
            $table->date('edc')->nullable();
            $table->integer('gravida')->default(0);
            $table->integer('para')->default(0);
            $table->integer('term')->default(0);
            $table->integer('preterm')->default(0);
            $table->integer('abortion')->default(0);
            $table->integer('living_children')->default(0);
            
            // Add back medical history information
            $table->text('medical_history')->nullable();
            $table->text('allergies')->nullable();
            $table->text('current_medications')->nullable();
            $table->text('previous_pregnancies')->nullable();
            $table->text('notes')->nullable();
        });
    }
};
