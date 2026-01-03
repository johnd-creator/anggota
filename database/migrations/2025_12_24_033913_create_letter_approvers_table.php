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
        if (Schema::hasTable('letter_approvers')) {
            return; // Table already exists, skip
        }

        Schema::create('letter_approvers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_unit_id')->nullable()->index();
            $table->string('signer_type', 50)->index(); // ketua, sekretaris
            $table->unsignedBigInteger('user_id');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->foreign('organization_unit_id')
                ->references('id')
                ->on('organization_units')
                ->onDelete('cascade');

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            // One user per signer_type per unit
            $table->unique(['organization_unit_id', 'signer_type', 'user_id'], 'letter_approvers_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('letter_approvers');
    }
};
