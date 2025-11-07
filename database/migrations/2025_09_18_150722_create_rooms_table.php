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
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('birth_care_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            // Indexes for better performance
            $table->index(['birth_care_id']);
            $table->unique(['birth_care_id', 'name']); // Room names should be unique per facility
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
