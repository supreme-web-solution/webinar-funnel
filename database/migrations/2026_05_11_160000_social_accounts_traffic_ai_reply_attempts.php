<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 32)->index();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('platform_username')->nullable();
            $table->string('platform_user_id')->nullable();
            $table->unsignedInteger('daily_post_limit')->default(50);
            $table->unsignedInteger('posts_today')->default(0);
            $table->date('posts_today_reset_on')->nullable();
            $table->timestamp('last_post_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'platform']);
        });

        Schema::create('traffic_reply_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mention_id')->constrained()->cascadeOnDelete();
            $table->foreignId('funnel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('social_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 40)->index();
            $table->string('skip_reason', 255)->nullable();
            $table->json('gate_details')->nullable();
            $table->text('reply_text')->nullable();
            $table->string('external_comment_id', 120)->nullable();
            $table->text('last_error')->nullable();
            $table->unsignedSmallInteger('post_dispatch_count')->default(0);
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->unique('mention_id');
        });

        Schema::table('funnel_settings', function (Blueprint $table) {
            $table->boolean('traffic_ai_reply_enabled')->default(false)->after('exit_popup_cta_url');
            $table->string('traffic_ai_link_override', 2048)->nullable()->after('traffic_ai_reply_enabled');
            $table->text('traffic_ai_extra_context')->nullable()->after('traffic_ai_link_override');
            $table->unsignedInteger('traffic_ai_max_replies_per_day')->default(20)->after('traffic_ai_extra_context');
            $table->json('traffic_ai_social_account_ids')->nullable()->after('traffic_ai_max_replies_per_day');
        });
    }

    public function down(): void
    {
        Schema::table('funnel_settings', function (Blueprint $table) {
            $table->dropColumn([
                'traffic_ai_reply_enabled',
                'traffic_ai_link_override',
                'traffic_ai_extra_context',
                'traffic_ai_max_replies_per_day',
                'traffic_ai_social_account_ids',
            ]);
        });

        Schema::dropIfExists('traffic_reply_attempts');
        Schema::dropIfExists('social_accounts');
    }
};
