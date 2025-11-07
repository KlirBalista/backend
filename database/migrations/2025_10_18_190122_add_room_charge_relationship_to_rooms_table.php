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
        Schema::table('rooms', function (Blueprint $table) {
            $table->foreignId('patient_charge_id')->nullable()->after('birth_care_id')->constrained()->nullOnDelete();
            $table->string('room_type')->nullable()->after('name'); // e.g., 'private', 'semi-private', 'shared'
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropForeign(['patient_charge_id']);
            $table->dropColumn(['patient_charge_id', 'room_type']);
        });
    }
};
