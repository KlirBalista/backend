<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('birth_care_staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('birth_care_id')->constrained('birth_cares')->onDelete('cascade');
            $table->timestamps();

            // Ensure a staff user can only work in one BirthCare
            $table->unique('user_id', 'uk_birth_care_staff_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('birth_care_staff');
    }
};