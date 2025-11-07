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
        Schema::create('patient_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('birth_care_id')->constrained()->cascadeOnDelete();
            $table->string('title'); // Document title
            $table->string('file_name'); // Original filename
            $table->string('file_path'); // Storage path
            $table->string('document_type'); // prenatal_form, medical_report, test_result, etc.
            $table->integer('file_size'); // File size in bytes
            $table->string('mime_type'); // File MIME type
            $table->json('metadata')->nullable(); // Additional metadata like form data
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            // Indexes for better performance
            $table->index(['patient_id', 'document_type']);
            $table->index(['birth_care_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patient_documents');
    }
};
