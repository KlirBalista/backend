<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('birth_care_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('birth_care_id')->constrained('birth_cares')->onDelete('cascade');
            $table->string('document_type', 100);
            $table->string('document_path', 255);
            $table->timestamp('timestamp')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('birth_care_documents');
    }
};