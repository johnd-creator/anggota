<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            if (!Schema::hasColumn('members', 'union_position_id')) {
                $table->unsignedBigInteger('union_position_id')->nullable()->after('nip');
            }
        });
        // drop old string column if exists
        Schema::table('members', function (Blueprint $table) {
            if (Schema::hasColumn('members', 'union_position')) {
                $table->dropColumn('union_position');
            }
        });
        // add foreign key
        Schema::table('members', function (Blueprint $table) {
            $table->foreign('union_position_id')->references('id')->on('union_positions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropForeign(['union_position_id']);
            $table->dropColumn('union_position_id');
            $table->string('union_position')->nullable();
        });
    }
};

