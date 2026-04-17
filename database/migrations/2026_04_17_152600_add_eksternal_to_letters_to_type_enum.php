<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (!in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::statement("ALTER TABLE `letters` MODIFY `to_type` ENUM('unit', 'member', 'admin_pusat', 'eksternal') NOT NULL");
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (!in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        $hasExternalLetters = DB::table('letters')
            ->where('to_type', 'eksternal')
            ->exists();

        if ($hasExternalLetters) {
            throw new RuntimeException("Cannot remove 'eksternal' from letters.to_type while external letters exist.");
        }

        DB::statement("ALTER TABLE `letters` MODIFY `to_type` ENUM('unit', 'member', 'admin_pusat') NOT NULL");
    }
};
