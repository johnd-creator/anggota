<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('letter_categories', function (Blueprint $table) {
            $table->string('color', 20)->nullable()->after('description');
            $table->unsignedInteger('sort_order')->default(0)->after('color');
        });
    }

    public function down(): void
    {
        Schema::table('letter_categories', function (Blueprint $table) {
            $table->dropColumn(['color', 'sort_order']);
        });
    }
};
