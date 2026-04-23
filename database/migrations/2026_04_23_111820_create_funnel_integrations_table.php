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
        Schema::create('funnel_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('funnel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('integration_account_id')->constrained()->cascadeOnDelete();
            $table->json('provider_list_config')->nullable();
            $table->boolean('enabled')->default(true)->index();
            $table->timestamps();

            $table->unique(['funnel_id', 'integration_account_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('funnel_integrations');
    }
};
