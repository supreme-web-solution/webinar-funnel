<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('funnel_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('funnel_settings', 'webinar_ai_enabled')) {
                $table->boolean('webinar_ai_enabled')->default(false)->after('traffic_ai_social_account_ids');
            }
            if (! Schema::hasColumn('funnel_settings', 'webinar_ai_auto_reply_enabled')) {
                $table->boolean('webinar_ai_auto_reply_enabled')->default(true)->after('webinar_ai_enabled');
            }
            if (! Schema::hasColumn('funnel_settings', 'webinar_ai_assistant_name')) {
                $table->string('webinar_ai_assistant_name')->nullable()->after('webinar_ai_auto_reply_enabled');
            }
        });

        if (! Schema::hasTable('funnel_ai_sources')) {
            Schema::create('funnel_ai_sources', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->nullable()->unique();
                $table->foreignId('funnel_id')->constrained()->cascadeOnDelete();
                $table->string('type', 32);
                $table->string('title')->nullable();
                $table->text('source_url')->nullable();
                $table->string('status', 40)->default('ready')->index();
                $table->text('error_message')->nullable();
                $table->longText('content')->nullable();
                $table->unsignedInteger('chunk_count')->default(0);
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();

                $table->index(['funnel_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('funnel_ai_sources')) {
            Schema::drop('funnel_ai_sources');
        }

        Schema::table('funnel_settings', function (Blueprint $table) {
            $toDrop = [];
            foreach (['webinar_ai_enabled', 'webinar_ai_auto_reply_enabled', 'webinar_ai_assistant_name'] as $column) {
                if (Schema::hasColumn('funnel_settings', $column)) {
                    $toDrop[] = $column;
                }
            }
            if (! empty($toDrop)) {
                $table->dropColumn($toDrop);
            }
        });
    }
};

