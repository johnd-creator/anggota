<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('mutation_requests', function (Blueprint $table) {
            $table->string('sla_status')->nullable()->index();
            $table->timestamp('sla_marked_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('mutation_requests', function (Blueprint $table) {
            $table->dropColumn(['sla_status','sla_marked_at']);
        });
    }
};

