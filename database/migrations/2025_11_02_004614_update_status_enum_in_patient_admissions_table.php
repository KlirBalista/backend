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
        // MySQL/MariaDB: We need to modify the ENUM directly via raw SQL
        DB::statement("ALTER TABLE patient_admissions MODIFY COLUMN status ENUM('in-labor', 'delivered', 'discharged', 'admitted', 'transferred', 'active') DEFAULT 'in-labor'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to original enum values
        DB::statement("ALTER TABLE patient_admissions MODIFY COLUMN status ENUM('admitted', 'discharged', 'transferred', 'active') DEFAULT 'admitted'");
    }
};
