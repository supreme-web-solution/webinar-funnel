<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('funnel_ad_campaigns', function (Blueprint $table): void {
            $table->string('zernio_social_account_id')->nullable()->after('platform_ad_account_ids');
        });
    }

    public function down(): void
    {
        Schema::table('funnel_ad_campaigns', function (Blueprint $table): void {
            $table->dropColumn('zernio_social_account_id');
        });
    }
};
