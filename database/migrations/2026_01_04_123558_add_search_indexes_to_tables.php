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
        Schema::table('announcements', function (Blueprint $table) {
            $table->index(['title', 'created_at']);
        });

        Schema::table('letters', function (Blueprint $table) {
            $table->index(['subject', 'created_at']);
            $table->index(['status', 'created_at']);
        });

        Schema::table('aspirations', function (Blueprint $table) {
            // member_id usually has FK index, adding compound if useful or status/created_at
            $table->index(['status', 'created_at']);
        });

        Schema::table('members', function (Blueprint $table) {
            $table->index(['full_name']);
            $table->index(['kta_number']);
            $table->index(['organization_unit_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropIndex(['title', 'created_at']);
        });

        Schema::table('letters', function (Blueprint $table) {
            $table->dropIndex(['subject', 'created_at']);
            $table->dropIndex(['status', 'created_at']);
        });

        Schema::table('aspirations', function (Blueprint $table) {
            $table->dropIndex(['status', 'created_at']);
        });

        Schema::table('members', function (Blueprint $table) {
            $table->dropIndex(['full_name']);
            $table->dropIndex(['kta_number']);
            $table->dropIndex(['organization_unit_id']);
        });
    }
};
