<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('funnel_ad_campaigns', function (Blueprint $table): void {
            $table->json('launch_errors')->nullable()->after('last_error');
        });
    }

    public function down(): void
    {
        Schema::table('funnel_ad_campaigns', function (Blueprint $table): void {
            $table->dropColumn('launch_errors');
        });
    }
};
