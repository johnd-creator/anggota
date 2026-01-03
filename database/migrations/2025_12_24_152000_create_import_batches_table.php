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
        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('organization_unit_id')->nullable()->constrained('organization_units')->onDelete('set null');
            $table->enum('status', ['draft', 'previewed', 'processing', 'completed', 'failed'])->default('draft');
            $table->string('original_filename', 255);
            $table->string('stored_path', 500);
            $table->string('file_hash', 64)->nullable(); // SHA256
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('valid_rows')->default(0);
            $table->unsignedInteger('invalid_rows')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['actor_user_id', 'status']);
            $table->index('organization_unit_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('import_batches');
    }
};
