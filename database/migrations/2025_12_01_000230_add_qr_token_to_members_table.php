<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->string('qr_token')->nullable()->unique()->after('nra');
            $table->date('card_valid_until')->nullable()->after('qr_token');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn(['qr_token','card_valid_until']);
        });
    }
};

