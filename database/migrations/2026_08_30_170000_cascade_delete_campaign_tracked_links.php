<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Links left behind when campaigns were deleted (campaign_id nulled by FK).
        DB::table('tracked_links')
            ->whereNull('campaign_id')
            ->where(function ($query): void {
                $query->whereNotNull('funnel_id')
                    ->orWhereIn('label', ['Lead magnet footer CTA', 'Affiliate offer', 'Bonus CTA'])
                    ->orWhere('label', 'like', '% affiliate')
                    ->orWhere('label', 'like', '% — Front End affiliate')
                    ->orWhere('label', 'like', '% — Deep Dive affiliate');
            })
            ->delete();

        Schema::table('tracked_links', function (Blueprint $table): void {
            $table->dropForeign(['campaign_id']);
            $table->foreign('campaign_id')
                ->references('id')
                ->on('campaigns')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tracked_links', function (Blueprint $table): void {
            $table->dropForeign(['campaign_id']);
            $table->foreign('campaign_id')
                ->references('id')
                ->on('campaigns')
                ->nullOnDelete();
        });
    }
};
