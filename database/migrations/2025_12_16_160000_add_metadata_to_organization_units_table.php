<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('organization_units', function (Blueprint $table) {
            $table->string('organization_type', 3)->default('DPD')->after('name'); // DPP|DPD
            $table->string('abbreviation', 10)->nullable()->unique()->after('organization_type'); // used for letter numbering
            $table->string('phone', 50)->nullable()->after('address');
            $table->string('email')->nullable()->after('phone');
        });

        // Backfill abbreviation from code for existing rows, then set PST as DPP with abbreviation DPP
        DB::statement("UPDATE organization_units SET abbreviation = code WHERE abbreviation IS NULL");
        DB::table('organization_units')->where('code', 'PST')->update([
            'organization_type' => 'DPP',
            'abbreviation' => 'DPP',
        ]);
    }

    public function down(): void
    {
        Schema::table('organization_units', function (Blueprint $table) {
            $table->dropUnique(['abbreviation']);
            $table->dropColumn(['organization_type', 'abbreviation', 'phone', 'email']);
        });
    }
};

