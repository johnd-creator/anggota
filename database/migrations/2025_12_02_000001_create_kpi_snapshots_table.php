<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('kpi_snapshots', function (Blueprint $table) {
            $table->id();
            $table->decimal('completeness_pct', 5, 2)->default(0);
            $table->decimal('mutation_sla_breach_pct', 5, 2)->default(0);
            $table->unsignedBigInteger('card_downloads')->default(0);
            $table->timestamp('calculated_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_snapshots');
    }
};

