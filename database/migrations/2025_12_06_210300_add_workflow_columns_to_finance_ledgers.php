<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('finance_ledgers', function (Blueprint $table) {
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->text('rejected_reason')->nullable()->after('approved_at');
            $table->timestamp('submitted_at')->nullable()->after('rejected_reason');
        });
    }

    public function down(): void
    {
        Schema::table('finance_ledgers', function (Blueprint $table) {
            $table->dropColumn(['approved_at', 'rejected_reason', 'submitted_at']);
        });
    }
};
