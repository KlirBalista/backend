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
        // First, update any existing null values to 'None'
        DB::statement("UPDATE patients SET philhealth_category = NULL WHERE philhealth_category IS NULL OR philhealth_category = ''");
        
        // Change the column type to varchar to allow 'None' value
        Schema::table('patients', function (Blueprint $table) {
            $table->string('philhealth_category')->nullable()->change();
            $table->string('facility_name')->nullable()->after('birth_care_id');
            $table->string('principal_philhealth_number')->nullable()->after('philhealth_category');
            $table->string('principal_name')->nullable()->after('principal_philhealth_number');
            $table->string('relationship_to_principal')->nullable()->after('principal_name');
            $table->date('principal_date_of_birth')->nullable()->after('relationship_to_principal');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn(['facility_name', 'principal_philhealth_number', 'principal_name', 'relationship_to_principal', 'principal_date_of_birth']);
            $table->enum('philhealth_category', ['Direct', 'Indirect'])->nullable()->change();
        });
    }
};
