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
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->enum('scope_type', ['global_all', 'global_officers', 'unit']);
            $table->foreignId('organization_unit_id')->nullable()->constrained('organization_units');
            $table->boolean('is_active')->default(true);
            $table->boolean('pin_to_dashboard')->default(false);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['scope_type', 'organization_unit_id', 'is_active', 'pin_to_dashboard'], 'idx_announcements_scope_pin');
            $table->index('created_at');
        });

        Schema::create('announcement_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained('announcements')->cascadeOnDelete();
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->timestamps();

            $table->index('uploaded_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_attachments');
        Schema::dropIfExists('announcements');
    }
};
