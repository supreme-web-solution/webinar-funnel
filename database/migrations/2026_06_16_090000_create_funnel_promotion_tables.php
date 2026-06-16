<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('funnel_promotion_posts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('funnel_id')->constrained()->cascadeOnDelete();
            $table->string('title', 200)->nullable();
            $table->string('topic', 255)->nullable();
            $table->string('content_type', 24)->index();
            $table->json('platforms')->nullable();
            $table->string('publish_mode', 24)->default('approve_first')->index();
            $table->string('status', 24)->default('draft')->index();
            $table->string('cta_url', 2048)->nullable();
            $table->string('cta_label', 120)->nullable();
            $table->longText('text_body')->nullable();
            $table->string('email_subject', 255)->nullable();
            $table->longText('email_body')->nullable();
            $table->json('hashtags')->nullable();
            $table->foreignId('primary_asset_id')->nullable()->index();
            $table->timestamp('scheduled_for')->nullable()->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->string('timezone', 64)->default('UTC');
            $table->text('last_error')->nullable();
            $table->json('generation_context')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['funnel_id', 'status']);
            $table->index(['funnel_id', 'scheduled_for']);
        });

        Schema::create('funnel_promotion_assets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('promotion_post_id')->constrained('funnel_promotion_posts')->cascadeOnDelete();
            $table->string('asset_type', 24)->index();
            $table->string('provider', 32)->nullable();
            $table->string('status', 24)->default('pending')->index();
            $table->text('source_prompt')->nullable();
            $table->string('remote_id', 191)->nullable()->index();
            $table->string('url', 2048)->nullable();
            $table->string('thumbnail_url', 2048)->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['promotion_post_id', 'status']);
        });

        Schema::table('funnel_promotion_posts', function (Blueprint $table) {
            $table->foreign('primary_asset_id')
                ->references('id')
                ->on('funnel_promotion_assets')
                ->nullOnDelete();
        });

        Schema::create('funnel_promotion_topic_suggestions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('funnel_id')->constrained()->cascadeOnDelete();
            $table->string('topic', 255);
            $table->string('angle', 255)->nullable();
            $table->string('status', 24)->default('suggested')->index();
            $table->unsignedSmallInteger('score')->default(0);
            $table->timestamps();

            $table->index(['funnel_id', 'status']);
        });

        Schema::create('funnel_promotion_schedule_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('post_id')->constrained('funnel_promotion_posts')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('from_time')->nullable();
            $table->timestamp('to_time')->nullable();
            $table->string('action', 24)->index();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['post_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funnel_promotion_schedule_events');
        Schema::dropIfExists('funnel_promotion_topic_suggestions');

        Schema::table('funnel_promotion_posts', function (Blueprint $table) {
            $table->dropForeign(['primary_asset_id']);
        });

        Schema::dropIfExists('funnel_promotion_assets');
        Schema::dropIfExists('funnel_promotion_posts');
    }
};
