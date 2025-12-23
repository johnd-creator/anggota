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
        Schema::table('audit_logs', function (Blueprint $table) {
            // Request context columns
            $table->uuid('request_id')->nullable()->after('id');
            $table->string('session_id', 40)->nullable()->after('request_id');

            // Organization unit for multi-tenant filtering
            $table->foreignId('organization_unit_id')->nullable()->after('user_id')
                ->constrained('organization_units')->nullOnDelete();

            // Event categorization
            $table->string('event_category', 30)->nullable()->after('event');

            // HTTP request details
            $table->string('route_name', 100)->nullable()->after('event_category');
            $table->string('http_method', 10)->nullable()->after('route_name');
            $table->string('url_path', 255)->nullable()->after('http_method');
            $table->unsignedSmallInteger('status_code')->nullable()->after('url_path');
            $table->unsignedInteger('duration_ms')->nullable()->after('status_code');

            // Subject (entity) tracking
            $table->string('subject_type', 100)->nullable()->after('duration_ms');
            $table->unsignedBigInteger('subject_id')->nullable()->after('subject_type');
        });

        // Add indexes in a separate call for better compatibility
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index('request_id', 'audit_logs_request_id_index');
            $table->index('session_id', 'audit_logs_session_id_index');
            $table->index('organization_unit_id', 'audit_logs_org_unit_id_index');
            $table->index(['event', 'created_at'], 'audit_logs_event_created_at_index');
            $table->index(['subject_type', 'subject_id'], 'audit_logs_subject_index');
            $table->index('created_at', 'audit_logs_created_at_index');
            $table->index('event_category', 'audit_logs_event_category_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            // Drop indexes first
            $table->dropIndex('audit_logs_request_id_index');
            $table->dropIndex('audit_logs_session_id_index');
            $table->dropIndex('audit_logs_org_unit_id_index');
            $table->dropIndex('audit_logs_event_created_at_index');
            $table->dropIndex('audit_logs_subject_index');
            $table->dropIndex('audit_logs_created_at_index');
            $table->dropIndex('audit_logs_event_category_index');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            // Drop foreign key first
            $table->dropForeign(['organization_unit_id']);

            // Drop columns
            $table->dropColumn([
                'request_id',
                'session_id',
                'organization_unit_id',
                'event_category',
                'route_name',
                'http_method',
                'url_path',
                'status_code',
                'duration_ms',
                'subject_type',
                'subject_id',
            ]);
        });
    }
};
