<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('mutation_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->foreignId('from_unit_id')->constrained('organization_units');
            $table->foreignId('to_unit_id')->constrained('organization_units');
            $table->date('effective_date')->nullable();
            $table->text('reason')->nullable();
            $table->string('document_path')->nullable();
            $table->string('status')->default('pending'); // pending/approved/rejected
            $table->unsignedBigInteger('submitted_by');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mutation_requests');
    }
};

