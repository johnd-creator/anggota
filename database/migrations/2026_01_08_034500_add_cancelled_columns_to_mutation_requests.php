<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('mutation_requests', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable()->after('approved_by');
            $table->unsignedBigInteger('cancelled_by_user_id')->nullable()->after('cancelled_at');
        });
    }

    public function down(): void
    {
        Schema::table('mutation_requests', function (Blueprint $table) {
            $table->dropColumn(['cancelled_at', 'cancelled_by_user_id']);
        });
    }
};
