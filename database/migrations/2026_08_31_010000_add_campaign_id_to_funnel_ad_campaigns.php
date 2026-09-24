<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('funnels', 'campaign_id')) {
            Schema::table('funnels', function (Blueprint $table) {
                $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            });
        }

        Schema::table('funnel_ad_campaigns', function (Blueprint $table) {
            $table->foreignId('campaign_id')->nullable()->after('funnel_id')->constrained()->nullOnDelete();
            $table->index(['campaign_id', 'status']);
        });

        if (Schema::getConnection()->getDriverName() !== 'sqlite' && Schema::hasColumn('funnels', 'campaign_id')) {
            DB::table('funnel_ad_campaigns')
                ->join('funnels', 'funnels.id', '=', 'funnel_ad_campaigns.funnel_id')
                ->whereNotNull('funnels.campaign_id')
                ->update(['funnel_ad_campaigns.campaign_id' => DB::raw('funnels.campaign_id')]);
        }
    }

    public function down(): void
    {
        Schema::table('funnel_ad_campaigns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('campaign_id');
        });
    }
};
