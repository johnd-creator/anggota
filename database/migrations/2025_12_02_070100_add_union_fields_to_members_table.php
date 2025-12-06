<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->string('kta_number')->nullable()->unique()->after('nra');
            $table->string('nip')->nullable()->unique()->after('kta_number');
            $table->string('union_position')->nullable()->after('nip');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn(['kta_number','nip','union_position']);
        });
    }
};

