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
        Schema::table('import_batches', function (Blueprint $table) {
            $table->timestamp('committed_at')->nullable()->after('finished_at');
            $table->unsignedInteger('created_count')->default(0)->after('invalid_rows');
            $table->unsignedInteger('updated_count')->default(0)->after('created_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('import_batches', function (Blueprint $table) {
            $table->dropColumn(['committed_at', 'created_count', 'updated_count']);
        });
    }
};
