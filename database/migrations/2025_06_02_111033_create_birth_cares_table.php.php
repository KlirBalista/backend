<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('birth_cares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('name', 100);
            $table->decimal('longitude', 10, 7);
            $table->decimal('latitude', 10, 7);
            $table->text('description')->nullable();
            $table->boolean('is_public')->default(false);
            $table->string('status')->default("pending");
            $table->timestamps();

            // Ensure one BirthCare per owner
            $table->unique('user_id', 'uk_birth_cares_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('birth_cares');
    }
};