<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('letters', function (Blueprint $table) {
            // Secondary approver type (nullable = single approval, 'bendahara' = dual approval)
            $table->string('signer_type_secondary')->nullable()->after('signer_type');

            // Primary approval timestamp (for dual approval flow tracking)
            $table->timestamp('approved_primary_at')->nullable()->after('approved_at');

            // Secondary approver user
            $table->foreignId('approved_secondary_by_user_id')
                ->nullable()
                ->after('approved_primary_at')
                ->constrained('users')
                ->nullOnDelete();

            // Secondary approval timestamp
            $table->timestamp('approved_secondary_at')->nullable()->after('approved_secondary_by_user_id');

            // Index for efficient queries
            $table->index('approved_secondary_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('letters', function (Blueprint $table) {
            $table->dropForeign(['approved_secondary_by_user_id']);
            $table->dropIndex(['approved_secondary_by_user_id']);
            $table->dropColumn([
                'signer_type_secondary',
                'approved_primary_at',
                'approved_secondary_by_user_id',
                'approved_secondary_at',
            ]);
        });
    }
};
