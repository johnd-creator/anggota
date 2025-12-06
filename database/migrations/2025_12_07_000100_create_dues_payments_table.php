<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('dues_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->foreignId('organization_unit_id')->constrained('organization_units');
            $table->char('period', 7); // Format: YYYY-MM
            $table->string('status', 20)->default('unpaid'); // unpaid, paid
            $table->decimal('amount', 15, 2)->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Unique constraint: one payment record per member per period
            $table->unique(['member_id', 'period']);

            // Index for querying by period and status
            $table->index(['organization_unit_id', 'period', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dues_payments');
    }
};
