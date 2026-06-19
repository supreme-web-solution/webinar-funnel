<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('funnel_ad_campaigns', function (Blueprint $table): void {
            $table->string('meta_pixel_id', 120)->nullable()->after('zernio_ad_account_id');
            $table->string('meta_conversion_event', 60)->nullable()->after('meta_pixel_id');
        });
    }

    public function down(): void
    {
        Schema::table('funnel_ad_campaigns', function (Blueprint $table): void {
            $table->dropColumn(['meta_pixel_id', 'meta_conversion_event']);
        });
    }
};
