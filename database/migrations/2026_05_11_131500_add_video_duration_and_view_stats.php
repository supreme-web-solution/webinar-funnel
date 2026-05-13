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
        Schema::table('funnel_settings', function (Blueprint $table): void {
            $table->unsignedInteger('webinar_duration_seconds')->nullable()->after('video_url');
        });

        Schema::create('funnel_video_view_stats', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('funnel_id')->constrained()->cascadeOnDelete();
            $table->string('session_key', 80);
            $table->unsignedInteger('watched_seconds')->default(0);
            $table->boolean('reached_60s')->default(false);
            $table->boolean('reached_50_percent')->default(false);
            $table->boolean('reached_100_percent')->default(false);
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['funnel_id', 'session_key']);
            $table->index(['funnel_id', 'reached_60s']);
            $table->index(['funnel_id', 'reached_50_percent']);
            $table->index(['funnel_id', 'reached_100_percent']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('funnel_video_view_stats');

        Schema::table('funnel_settings', function (Blueprint $table): void {
            $table->dropColumn('webinar_duration_seconds');
        });
    }
};
