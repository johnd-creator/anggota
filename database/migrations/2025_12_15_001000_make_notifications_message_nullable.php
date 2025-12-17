<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('notifications')) {
            return;
        }

        $driver = DB::getDriverName();

        // Laravel DatabaseNotification doesn't fill `message`, so this column must be nullable.
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE `notifications` MODIFY `message` TEXT NULL');
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE notifications ALTER COLUMN message DROP NOT NULL');
            return;
        }

        // SQLite alter column is limited; prefer migrate:fresh for dev/test.
    }

    public function down(): void
    {
        // Non-destructive: keep nullable for compatibility.
    }
};

