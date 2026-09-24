<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_integrations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('integration_account_id')->constrained()->cascadeOnDelete();
            $table->json('provider_list_config')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique(['campaign_id', 'integration_account_id'], 'campaign_integration_unique');
        });

        Schema::create('campaign_email_sends', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_email_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_lead_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending'); // pending|sent|failed|skipped
            $table->timestamp('scheduled_at');
            $table->timestamp('sent_at')->nullable();
            $table->text('error_message')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['campaign_email_id', 'campaign_lead_id'], 'campaign_email_lead_unique');
            $table->index(['status', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_email_sends');
        Schema::dropIfExists('campaign_integrations');
    }
};
