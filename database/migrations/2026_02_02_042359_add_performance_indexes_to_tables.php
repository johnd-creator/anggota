<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Add performance indexes for common query patterns.
     * These indexes optimize:
     * - Dashboard and listing queries
     * - Filter operations
     * - Join operations
     */
    public function up(): void
    {
        // Letters table indexes for inbox/outbox/approvals queries
        if (Schema::hasTable('letters')) {
            // Composite index for status + created_at (common filter)
            Schema::table('letters', function (Blueprint $table) {
                $table->index(['status', 'created_at'], 'idx_letters_status_created');
            });

            // Composite index for creator queries (outbox)
            Schema::table('letters', function (Blueprint $table) {
                $table->index(['creator_user_id', 'status'], 'idx_letters_creator_status');
            });

            // Composite index for approval queue
            Schema::table('letters', function (Blueprint $table) {
                $table->index(['from_unit_id', 'signer_type', 'status'], 'idx_letters_approval');
            });

            // Index for submitted_at (approvals sorting)
            Schema::table('letters', function (Blueprint $table) {
                $table->index('submitted_at', 'idx_letters_submitted_at');
            });
        }

        // Dues payments indexes for period + status filters
        if (Schema::hasTable('dues_payments')) {
            Schema::table('dues_payments', function (Blueprint $table) {
                $table->index(['period', 'status', 'organization_unit_id'], 'idx_dues_period_status_unit');
            });

            // Index for member_id lookups
            Schema::table('dues_payments', function (Blueprint $table) {
                $table->index(['member_id', 'period'], 'idx_dues_member_period');
            });
        }

        // Audit logs indexes for activity queries
        if (Schema::hasTable('audit_logs')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                $table->index(['organization_unit_id', 'created_at'], 'idx_audit_unit_created');
            });

            // Index for event_category filtering
            Schema::table('audit_logs', function (Blueprint $table) {
                $table->index('event_category', 'idx_audit_event_category');
            });
        }

        // Mutation requests indexes for dashboard
        if (Schema::hasTable('mutation_requests')) {
            Schema::table('mutation_requests', function (Blueprint $table) {
                $table->index(['status', 'created_at'], 'idx_mutations_status_created');
            });

            // Composite index for unit-based queries
            Schema::table('mutation_requests', function (Blueprint $table) {
                $table->index(['from_unit_id', 'to_unit_id', 'status'], 'idx_mutations_units_status');
            });
        }

        // Members indexes for search and filters
        if (Schema::hasTable('members')) {
            // Index for status + unit filtering
            Schema::table('members', function (Blueprint $table) {
                $table->index(['status', 'organization_unit_id'], 'idx_members_status_unit');
            });
        }

        // Letter reads indexes for unread counting
        if (Schema::hasTable('letter_reads')) {
            Schema::table('letter_reads', function (Blueprint $table) {
                $table->index(['letter_id', 'user_id'], 'idx_reads_letter_user');
            });

            Schema::table('letter_reads', function (Blueprint $table) {
                $table->index('read_at', 'idx_reads_read_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Letters indexes
        if (Schema::hasTable('letters')) {
            Schema::table('letters', function (Blueprint $table) {
                $table->dropIndex('idx_letters_status_created');
            });
            Schema::table('letters', function (Blueprint $table) {
                $table->dropIndex('idx_letters_creator_status');
            });
            Schema::table('letters', function (Blueprint $table) {
                $table->dropIndex('idx_letters_approval');
            });
            Schema::table('letters', function (Blueprint $table) {
                $table->dropIndex('idx_letters_submitted_at');
            });
        }

        // Dues payments indexes
        if (Schema::hasTable('dues_payments')) {
            Schema::table('dues_payments', function (Blueprint $table) {
                $table->dropIndex('idx_dues_period_status_unit');
            });
            Schema::table('dues_payments', function (Blueprint $table) {
                $table->dropIndex('idx_dues_member_period');
            });
        }

        // Audit logs indexes
        if (Schema::hasTable('audit_logs')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                $table->dropIndex('idx_audit_unit_created');
            });
            Schema::table('audit_logs', function (Blueprint $table) {
                $table->dropIndex('idx_audit_event_category');
            });
        }

        // Mutation requests indexes
        if (Schema::hasTable('mutation_requests')) {
            Schema::table('mutation_requests', function (Blueprint $table) {
                $table->dropIndex('idx_mutations_status_created');
            });
            Schema::table('mutation_requests', function (Blueprint $table) {
                $table->dropIndex('idx_mutations_units_status');
            });
        }

        // Members indexes
        if (Schema::hasTable('members')) {
            Schema::table('members', function (Blueprint $table) {
                $table->dropIndex('idx_members_status_unit');
            });
        }

        // Letter reads indexes
        if (Schema::hasTable('letter_reads')) {
            Schema::table('letter_reads', function (Blueprint $table) {
                $table->dropIndex('idx_reads_letter_user');
            });
            Schema::table('letter_reads', function (Blueprint $table) {
                $table->dropIndex('idx_reads_read_at');
            });
        }
    }
};
