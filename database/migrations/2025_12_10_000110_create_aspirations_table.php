<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('aspirations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->nullable()->constrained('members')->onDelete('cascade');
            $table->foreignId('organization_unit_id')->constrained('organization_units')->onDelete('cascade');
            $table->foreignId('category_id')->constrained('aspiration_categories')->onDelete('cascade');
            $table->string('title');
            $table->text('body');
            $table->enum('status', ['new', 'in_progress', 'resolved'])->default('new');
            $table->foreignId('merged_into_id')->nullable()->constrained('aspirations')->onDelete('set null');
            $table->unsignedInteger('support_count')->default(0);
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->timestamps();

            $table->index(['organization_unit_id', 'status']);
            $table->index('support_count');
        });

        // Pivot table for aspiration tags
        Schema::create('aspiration_tag', function (Blueprint $table) {
            $table->foreignId('aspiration_id')->constrained('aspirations')->onDelete('cascade');
            $table->foreignId('tag_id')->constrained('aspiration_tags')->onDelete('cascade');
            $table->primary(['aspiration_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aspiration_tag');
        Schema::dropIfExists('aspirations');
    }
};
