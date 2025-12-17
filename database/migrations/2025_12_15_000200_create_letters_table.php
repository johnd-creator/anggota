<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('letters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creator_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('from_unit_id')->nullable()->constrained('organization_units')->nullOnDelete();
            $table->foreignId('letter_category_id')->constrained('letter_categories')->restrictOnDelete();
            $table->enum('signer_type', ['ketua', 'sekretaris']);
            $table->enum('to_type', ['unit', 'member', 'admin_pusat']);
            $table->foreignId('to_unit_id')->nullable()->constrained('organization_units')->nullOnDelete();
            $table->foreignId('to_member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->string('subject');
            $table->longText('body');
            $table->enum('status', ['draft', 'submitted', 'revision', 'approved', 'sent', 'archived', 'rejected'])->default('draft');
            $table->tinyInteger('month')->unsigned()->nullable();
            $table->smallInteger('year')->unsigned()->nullable();
            $table->unsignedInteger('sequence')->nullable();
            $table->string('letter_number')->nullable()->unique();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('letters');
    }
};
