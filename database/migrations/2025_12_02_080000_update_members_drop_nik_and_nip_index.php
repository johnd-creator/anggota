<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // drop indexes first (SQLite compatibility)
        try { DB::statement('DROP INDEX IF EXISTS members_nik_email_index'); } catch (\Throwable $e) {}
        try { DB::statement('DROP INDEX IF EXISTS members_nik_unique'); } catch (\Throwable $e) {}
        Schema::table('members', function (Blueprint $table) {
            if (Schema::hasColumn('members', 'nik')) {
                $table->dropColumn('nik');
            }
            try { DB::statement('DROP INDEX IF EXISTS members_nip_unique'); } catch (\Throwable $e) {}
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            // restore nik column as nullable (cannot recreate encrypted behavior automatically)
            $table->string('nik', 16)->nullable();
            // re-add unique index on nip if needed
            try {
                $table->unique('nip');
            } catch (\Throwable $e) {}
        });
    }
};
