<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('finance_categories', function (Blueprint $table) {
            $table->boolean('is_recurring')->default(false)->after('description');
            $table->decimal('default_amount', 15, 2)->nullable()->after('is_recurring');
            $table->boolean('is_system')->default(false)->after('default_amount');
        });
    }

    public function down(): void
    {
        Schema::table('finance_categories', function (Blueprint $table) {
            $table->dropColumn(['is_recurring', 'default_amount', 'is_system']);
        });
    }
};
