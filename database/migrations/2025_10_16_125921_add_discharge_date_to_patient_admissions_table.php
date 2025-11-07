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
            if (!Schema::hasColumn('patient_admissions', 'discharge_date')) {
                $table->date('discharge_date')->nullable()->after('admission_date');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patient_admissions', function (Blueprint $table) {
            if (Schema::hasColumn('patient_admissions', 'discharge_date')) {
                $table->dropColumn('discharge_date');
            }
        });
    }
};
