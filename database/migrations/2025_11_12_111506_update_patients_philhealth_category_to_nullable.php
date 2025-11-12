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
        // Ensure philhealth_category null values are normalized (safe no-op for most DBs)
        DB::statement("UPDATE patients SET philhealth_category = NULL WHERE philhealth_category IS NULL OR philhealth_category = ''");

        // Add new columns only if they don't already exist. Avoid column order directives for cross-DB compatibility.
        if (!Schema::hasColumn('patients', 'facility_name') ||
            !Schema::hasColumn('patients', 'principal_philhealth_number') ||
            !Schema::hasColumn('patients', 'principal_name') ||
            !Schema::hasColumn('patients', 'relationship_to_principal') ||
            !Schema::hasColumn('patients', 'principal_date_of_birth')) {
            Schema::table('patients', function (Blueprint $table) {
                if (!Schema::hasColumn('patients', 'facility_name')) {
                    $table->string('facility_name')->nullable();
                }
                if (!Schema::hasColumn('patients', 'principal_philhealth_number')) {
                    $table->string('principal_philhealth_number')->nullable();
                }
                if (!Schema::hasColumn('patients', 'principal_name')) {
                    $table->string('principal_name')->nullable();
                }
                if (!Schema::hasColumn('patients', 'relationship_to_principal')) {
                    $table->string('relationship_to_principal')->nullable();
                }
                if (!Schema::hasColumn('patients', 'principal_date_of_birth')) {
                    $table->date('principal_date_of_birth')->nullable();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop columns only if they exist; do not alter philhealth_category type
        Schema::table('patients', function (Blueprint $table) {
            if (Schema::hasColumn('patients', 'facility_name')) {
                $table->dropColumn('facility_name');
            }
            if (Schema::hasColumn('patients', 'principal_philhealth_number')) {
                $table->dropColumn('principal_philhealth_number');
            }
            if (Schema::hasColumn('patients', 'principal_name')) {
                $table->dropColumn('principal_name');
            }
            if (Schema::hasColumn('patients', 'relationship_to_principal')) {
                $table->dropColumn('relationship_to_principal');
            }
            if (Schema::hasColumn('patients', 'principal_date_of_birth')) {
                $table->dropColumn('principal_date_of_birth');
            }
        });
    }
};
