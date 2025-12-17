<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('letters', function (Blueprint $table) {
            $table->enum('confidentiality', ['biasa', 'terbatas', 'rahasia'])->default('biasa')->after('body');
            $table->enum('urgency', ['biasa', 'segera', 'kilat'])->default('biasa')->after('confidentiality');
        });
    }

    public function down(): void
    {
        Schema::table('letters', function (Blueprint $table) {
            $table->dropColumn(['confidentiality', 'urgency']);
        });
    }
};
