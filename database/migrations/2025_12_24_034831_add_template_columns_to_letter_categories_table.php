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
        Schema::table('letter_categories', function (Blueprint $table) {
            $table->text('template_subject')->nullable()->after('description');
            $table->longText('template_body')->nullable()->after('template_subject');
            $table->text('template_cc_text')->nullable()->after('template_body');
            $table->string('default_confidentiality', 20)->nullable()->after('template_cc_text');
            $table->string('default_urgency', 20)->nullable()->after('default_confidentiality');
            $table->string('default_signer_type', 20)->nullable()->after('default_urgency');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('letter_categories', function (Blueprint $table) {
            $table->dropColumn([
                'template_subject',
                'template_body',
                'template_cc_text',
                'default_confidentiality',
                'default_urgency',
                'default_signer_type',
            ]);
        });
    }
};
