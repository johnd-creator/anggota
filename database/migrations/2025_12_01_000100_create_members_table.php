<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('members')) {
            return;
        }

        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('full_name');
            $table->string('nik', 16)->unique();
            $table->string('employee_id')->nullable();
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('birth_place')->nullable();
            $table->date('birth_date')->nullable();
            $table->text('address')->nullable();
            $table->string('emergency_contact')->nullable();
            $table->string('job_title')->nullable();
            $table->enum('employment_type', ['organik','tkwt'])->default('organik');
            $table->enum('status', ['aktif','cuti','suspended','resign','pensiun'])->default('aktif');
            $table->date('join_date');
            $table->foreignId('organization_unit_id')->constrained('organization_units');
            $table->string('nra')->unique();
            $table->unsignedSmallInteger('join_year');
            $table->unsignedInteger('sequence_number');
            $table->string('photo_path')->nullable();
            $table->json('documents')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_unit_id', 'join_year', 'sequence_number']);
            $table->index(['nik', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
