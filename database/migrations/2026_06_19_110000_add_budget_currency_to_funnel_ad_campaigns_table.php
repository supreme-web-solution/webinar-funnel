<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('funnel_ad_campaigns', function (Blueprint $table): void {
            $table->string('budget_currency', 3)->default('USD')->after('budget_type');
        });
    }

    public function down(): void
    {
        Schema::table('funnel_ad_campaigns', function (Blueprint $table): void {
            $table->dropColumn('budget_currency');
        });
    }
};
