<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_traffic_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('standalone_funnel_id')->nullable()->constrained('funnels')->nullOnDelete();
            $table->string('intensity', 24)->default('growth');
            $table->json('enabled_platforms')->nullable();
            $table->json('enabled_formats')->nullable();
            $table->json('frequency_overrides')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('content_employee_plans', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('funnel_id')->constrained()->cascadeOnDelete();
            $table->date('week_start');
            $table->string('status', 24)->default('draft')->index();
            $table->string('source', 32)->default('manual');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'week_start']);
            $table->index(['campaign_id', 'week_start']);
        });

        Schema::create('content_employee_plan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('content_employee_plans')->cascadeOnDelete();
            $table->string('format_key', 64)->index();
            $table->string('platform', 32)->index();
            $table->string('topic', 255);
            $table->string('angle', 255)->nullable();
            $table->timestamp('scheduled_for')->nullable()->index();
            $table->foreignId('promotion_post_id')->nullable()->constrained('funnel_promotion_posts')->nullOnDelete();
            $table->string('status', 24)->default('planned')->index();
            $table->json('format_spec')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['plan_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_employee_plan_items');
        Schema::dropIfExists('content_employee_plans');
        Schema::dropIfExists('user_traffic_profiles');
    }
};
