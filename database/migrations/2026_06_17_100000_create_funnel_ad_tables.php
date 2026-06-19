<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('funnel_ad_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('funnel_id')->index();
            $table->unsignedBigInteger('user_id')->index();

            // Identity
            $table->string('name');
            $table->string('goal')->default('traffic'); // traffic|awareness|engagement|lead_generation|conversions
            $table->json('platforms')->nullable();       // ['facebook','instagram','tiktok',...]
            $table->string('status')->default('draft'); // draft|generating|ready|launching|active|paused|completed|failed

            // Product context (feeds AI research)
            $table->string('product_url', 2048)->nullable();
            $table->string('industry', 120)->nullable();
            $table->text('goal_description')->nullable();

            // AI-generated research blob (hooks, personas, angles, value props)
            $table->json('ai_research')->nullable();

            // Budget & schedule
            $table->decimal('budget_amount', 10, 2)->default(10.00);
            $table->string('budget_type', 20)->default('daily'); // daily|lifetime
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            // Audience targeting
            $table->json('targeting')->nullable(); // { age_min, age_max, countries, interests, genders }

            // Zernio references (filled after launch)
            $table->string('zernio_ad_account_id')->nullable();
            $table->string('zernio_campaign_id')->nullable();

            // Cached performance metrics
            $table->json('performance')->nullable();
            $table->timestamp('last_synced_at')->nullable();

            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->foreign('funnel_id')->references('id')->on('funnels')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('funnel_ad_creatives', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('campaign_id')->index();
            $table->unsignedBigInteger('funnel_id')->index();
            $table->unsignedBigInteger('user_id')->index();

            // Copy
            $table->string('headline', 255)->nullable();
            $table->text('primary_text')->nullable();
            $table->string('description', 255)->nullable();
            $table->string('cta_button', 60)->default('LEARN_MORE');

            // Asset
            $table->string('asset_url', 2048)->nullable();
            $table->string('asset_type', 20)->nullable(); // image|video
            $table->string('format', 30)->default('square'); // square|story|landscape|reel

            // Lifecycle
            $table->string('status', 30)->default('draft'); // draft|active|paused|winner|loser
            $table->boolean('is_winner')->default(false);

            // Zernio
            $table->string('zernio_post_id')->nullable();
            $table->string('zernio_ad_id')->nullable();

            // Cached per-creative performance
            $table->json('performance')->nullable();

            $table->timestamps();

            $table->foreign('campaign_id')->references('id')->on('funnel_ad_campaigns')->cascadeOnDelete();
            $table->foreign('funnel_id')->references('id')->on('funnels')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funnel_ad_creatives');
        Schema::dropIfExists('funnel_ad_campaigns');
    }
};
