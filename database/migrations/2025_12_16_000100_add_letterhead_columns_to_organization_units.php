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
        Schema::table('organization_units', function (Blueprint $table) {
            $table->string('letterhead_name')->nullable()->after('address');
            $table->text('letterhead_address')->nullable()->after('letterhead_name');
            $table->string('letterhead_city', 100)->nullable()->after('letterhead_address');
            $table->string('letterhead_postal_code', 20)->nullable()->after('letterhead_city');
            $table->string('letterhead_phone', 50)->nullable()->after('letterhead_postal_code');
            $table->string('letterhead_email')->nullable()->after('letterhead_phone');
            $table->string('letterhead_website')->nullable()->after('letterhead_email');
            $table->string('letterhead_fax', 50)->nullable()->after('letterhead_website');
            $table->string('letterhead_whatsapp', 50)->nullable()->after('letterhead_fax');
            $table->text('letterhead_footer_text')->nullable()->after('letterhead_whatsapp');
            $table->string('letterhead_logo_path')->nullable()->after('letterhead_footer_text');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organization_units', function (Blueprint $table) {
            $table->dropColumn([
                'letterhead_name',
                'letterhead_address',
                'letterhead_city',
                'letterhead_postal_code',
                'letterhead_phone',
                'letterhead_email',
                'letterhead_website',
                'letterhead_fax',
                'letterhead_whatsapp',
                'letterhead_footer_text',
                'letterhead_logo_path',
            ]);
        });
    }
};
