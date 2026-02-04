<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('organization_units', function (Blueprint $table) {
            $table->boolean('is_pusat')->default(false)->after('abbreviation');
            $table->boolean('can_register_members')->default(true)->after('is_pusat');
            $table->boolean('can_issue_kta')->default(true)->after('can_register_members');
        });

        DB::table('organization_units')
            ->where('code', 'PST')
            ->update([
                'is_pusat' => true,
                'can_register_members' => false,
                'can_issue_kta' => false,
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organization_units', function (Blueprint $table) {
            $table->dropColumn(['is_pusat', 'can_register_members', 'can_issue_kta']);
        });
    }
};
