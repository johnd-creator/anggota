<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('letters', function (Blueprint $table) {
            $table->timestamp('sla_due_at')->nullable()->after('status')->index();
            $table->string('sla_status', 20)->nullable()->after('sla_due_at')->index();
            $table->timestamp('sla_marked_at')->nullable()->after('sla_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('letters', function (Blueprint $table) {
            $table->dropColumn(['sla_due_at', 'sla_status', 'sla_marked_at']);
        });
    }
};
