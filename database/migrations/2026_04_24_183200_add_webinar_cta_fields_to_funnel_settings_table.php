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
            $table->string('webinar_cta_label')->nullable()->after('video_url');
            $table->string('webinar_cta_url', 2048)->nullable()->after('webinar_cta_label');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('funnel_settings', function (Blueprint $table) {
            $table->dropColumn(['webinar_cta_label', 'webinar_cta_url']);
        });
    }
};
