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
        Schema::dropIfExists('payment_adjustments');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreate the table if needed to rollback
        Schema::create('payment_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_bill_id')->constrained()->onDelete('cascade');
            $table->string('adjustment_type', 50); // dswd, philhealth, senior_citizen, pwd, etc.
            $table->string('adjustment_category', 30); // discount, assistance, insurance, government
            $table->decimal('amount', 10, 2);
            $table->string('reference_number')->unique();
            $table->date('adjustment_date');
            $table->string('description')->nullable(); // Brief description
            $table->text('details')->nullable(); // Detailed explanation
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->text('notes')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['patient_bill_id', 'adjustment_date']);
            $table->index(['adjustment_type', 'adjustment_category']);
            $table->index('reference_number');
        });
    }
};
