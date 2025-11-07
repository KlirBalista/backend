<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('patient_admissions', function (Blueprint $table) {
            // Check and add missing fields from the admission form
            if (!Schema::hasColumn('patient_admissions', 'reason_for_admission')) {
                $table->text('reason_for_admission')->nullable()->after('chief_complaint');
            }
            if (!Schema::hasColumn('patient_admissions', 'primary_nurse')) {
                $table->string('primary_nurse')->nullable()->after('attending_physician');
            }
            if (!Schema::hasColumn('patient_admissions', 'ward_section')) {
                $table->string('ward_section')->nullable()->after('primary_nurse');
            }
            if (!Schema::hasColumn('patient_admissions', 'admission_source')) {
                $table->string('admission_source')->nullable()->after('ward_section');
            }
            if (!Schema::hasColumn('patient_admissions', 'insurance_information')) {
                $table->text('insurance_information')->nullable()->after('admission_source');
            }
            if (!Schema::hasColumn('patient_admissions', 'emergency_contact_name')) {
                $table->string('emergency_contact_name')->nullable()->after('insurance_information');
            }
            if (!Schema::hasColumn('patient_admissions', 'emergency_contact_relationship')) {
                $table->string('emergency_contact_relationship')->nullable()->after('emergency_contact_name');
            }
            if (!Schema::hasColumn('patient_admissions', 'emergency_contact_phone')) {
                $table->string('emergency_contact_phone')->nullable()->after('emergency_contact_relationship');
            }
            if (!Schema::hasColumn('patient_admissions', 'patient_belongings')) {
                $table->text('patient_belongings')->nullable()->after('emergency_contact_phone');
            }
            if (!Schema::hasColumn('patient_admissions', 'special_dietary_requirements')) {
                $table->text('special_dietary_requirements')->nullable()->after('patient_belongings');
            }
            if (!Schema::hasColumn('patient_admissions', 'mobility_assistance_needed')) {
                $table->boolean('mobility_assistance_needed')->default(false)->after('special_dietary_requirements');
            }
            if (!Schema::hasColumn('patient_admissions', 'fall_risk_assessment')) {
                $table->enum('fall_risk_assessment', ['low', 'moderate', 'high'])->default('low')->after('mobility_assistance_needed');
            }
            if (!Schema::hasColumn('patient_admissions', 'isolation_precautions')) {
                $table->string('isolation_precautions')->nullable()->after('fall_risk_assessment');
            }
            if (!Schema::hasColumn('patient_admissions', 'patient_orientation_completed')) {
                $table->boolean('patient_orientation_completed')->default(false)->after('isolation_precautions');
            }
            if (!Schema::hasColumn('patient_admissions', 'family_notification_completed')) {
                $table->boolean('family_notification_completed')->default(false)->after('patient_orientation_completed');
            }
            if (!Schema::hasColumn('patient_admissions', 'advance_directives')) {
                $table->text('advance_directives')->nullable()->after('family_notification_completed');
            }
            if (!Schema::hasColumn('patient_admissions', 'discharge_planning_needs')) {
                $table->text('discharge_planning_needs')->nullable()->after('advance_directives');
            }
        });

        // Note: SQLite doesn't support MODIFY COLUMN, so we'll handle status validation in the application layer
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patient_admissions', function (Blueprint $table) {
            // Remove the added fields
            $table->dropColumn([
                'reason_for_admission',
                'primary_nurse', 
                'ward_section',
                'admission_source',
                'insurance_information',
                'emergency_contact_name',
                'emergency_contact_relationship',
                'emergency_contact_phone',
                'patient_belongings',
                'special_dietary_requirements',
                'mobility_assistance_needed',
                'fall_risk_assessment',
                'isolation_precautions',
                'patient_orientation_completed',
                'family_notification_completed',
                'advance_directives',
                'discharge_planning_needs',
                'room_id',
                'bed_id'
            ]);
        });
        
        // Note: SQLite doesn't support MODIFY COLUMN
    }
};