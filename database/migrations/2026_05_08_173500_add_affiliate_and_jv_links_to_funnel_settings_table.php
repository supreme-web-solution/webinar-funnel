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
        Schema::table('funnel_settings', function (Blueprint $table) {
            $table->string('affiliate_request_link', 2048)->nullable()->after('webinar_cta_url');
            $table->string('jv_page', 2048)->nullable()->after('affiliate_request_link');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('funnel_settings', function (Blueprint $table) {
            $table->dropColumn(['affiliate_request_link', 'jv_page']);
        });
    }
};
