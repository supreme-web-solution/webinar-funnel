<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('type')->default('sales')->index(); // sales|webinar
            $table->string('status')->default('draft')->index(); // draft|published|archived
            $table->unsignedTinyInteger('wizard_step')->default(1);
            $table->string('offer_url')->nullable();
            $table->string('affiliate_link')->nullable();
            $table->string('marketplace')->nullable(); // jvzoo|warriorplus|clickbank|manual
            $table->json('offer_data')->nullable();
            $table->json('analysis')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'slug']);
            $table->index(['user_id', 'status', 'created_at']);
        });

        Schema::create('campaign_pages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->string('page_type'); // squeeze|thankyou|bonus|lead_magnet
            $table->string('slug')->nullable();
            $table->json('content')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->unique(['campaign_id', 'page_type']);
        });

        Schema::create('campaign_bonuses', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('bonus_type')->default('ebook'); // ebook|checklist|mini_course|micro_app|other
            $table->longText('content')->nullable();
            $table->json('meta')->nullable();
            $table->string('status')->default('draft');
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        Schema::create('campaign_emails', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->string('sequence_key')->nullable();
            $table->string('subject');
            $table->longText('body')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['campaign_id', 'sort_order']);
        });

        Schema::create('tracked_links', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('funnel_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 32)->unique();
            $table->string('label')->nullable();
            $table->text('destination_url');
            $table->json('geo_rules')->nullable();
            $table->json('device_rules')->nullable();
            $table->unsignedBigInteger('click_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        Schema::create('tracked_link_clicks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tracked_link_id')->constrained()->cascadeOnDelete();
            $table->string('ip', 45)->nullable();
            $table->string('country', 2)->nullable();
            $table->string('device')->nullable(); // desktop|mobile|tablet|bot
            $table->string('user_agent', 512)->nullable();
            $table->string('referrer', 512)->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tracked_link_id', 'created_at']);
        });

        Schema::create('custom_domains', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->string('domain')->unique();
            $table->string('status')->default('pending'); // pending|active|failed
            $table->timestamp('verified_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::table('funnels', function (Blueprint $table) {
            $table->foreignId('campaign_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('funnels', function (Blueprint $table) {
            $table->dropConstrainedForeignId('campaign_id');
        });

        Schema::dropIfExists('custom_domains');
        Schema::dropIfExists('tracked_link_clicks');
        Schema::dropIfExists('tracked_links');
        Schema::dropIfExists('campaign_emails');
        Schema::dropIfExists('campaign_bonuses');
        Schema::dropIfExists('campaign_pages');
        Schema::dropIfExists('campaigns');
    }
};
