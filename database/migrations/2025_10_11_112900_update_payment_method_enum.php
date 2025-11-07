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
        // For SQLite, we need to recreate the table with new enum values
        Schema::table('bill_payments', function (Blueprint $table) {
            $table->string('payment_method_temp')->nullable();
        });
        
        // Copy existing data to temp column
        DB::statement('UPDATE bill_payments SET payment_method_temp = payment_method');
        
        // Drop the old column and rename temp column
        Schema::table('bill_payments', function (Blueprint $table) {
            $table->dropColumn('payment_method');
        });
        
        Schema::table('bill_payments', function (Blueprint $table) {
            $table->string('payment_method')->default('cash');
        });
        
        // Copy data back from temp column
        DB::statement('UPDATE bill_payments SET payment_method = payment_method_temp');
        
        // Drop temp column
        Schema::table('bill_payments', function (Blueprint $table) {
            $table->dropColumn('payment_method_temp');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // For rollback, we just keep the string column
        // Since SQLite doesn't have enum constraints anyway
    }
};