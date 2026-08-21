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
        Schema::create('billing_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creator_id')->constrained('users')->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('max_amount', 15, 2);
            $table->string('status')->default('pending_approval'); // pending_approval, approved, pending_cancellation, cancelled
            $table->timestamps();
        });

        Schema::create('billing_period_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('billing_period_id')->constrained('billing_periods')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('billing_period_id')->nullable()->constrained('billing_periods')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['billing_period_id']);
            $table->dropColumn('billing_period_id');
        });

        Schema::dropIfExists('billing_period_user');
        Schema::dropIfExists('billing_periods');
    }
};
