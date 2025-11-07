<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_birth_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained('birth_care_roles')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('birth_care_id')->constrained('birth_cares')->onDelete('cascade');
            $table->timestamps();

            // Ensure a user can only have one role per BirthCare
            $table->unique(['user_id', 'birth_care_id'], 'uk_user_birth_roles_user_birthcare');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_birth_roles');
    }
};