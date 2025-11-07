<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('birth_care_subscriptions', function (Blueprint $table) {
            $table->foreignId('payment_session_id')->nullable()->after('plan_id')->constrained('payment_sessions')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('birth_care_subscriptions', function (Blueprint $table) {
            $table->dropForeign(['payment_session_id']);
            $table->dropColumn('payment_session_id');
        });
    }
};
