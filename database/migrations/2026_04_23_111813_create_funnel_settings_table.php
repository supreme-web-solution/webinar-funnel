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
        Schema::create('funnel_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('funnel_id')->constrained()->cascadeOnDelete();
            $table->string('webinar_title')->nullable();
            $table->text('webinar_description')->nullable();
            $table->string('video_url')->nullable();
            $table->string('chat_mode')->default('simulated');
            $table->unsignedInteger('countdown_seconds')->nullable();
            $table->boolean('allow_replay')->default(true);
            $table->boolean('double_opt_in')->default(false);
            $table->json('chat_seed_messages')->nullable();
            $table->json('branding')->nullable();
            $table->timestamps();

            $table->unique('funnel_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('funnel_settings');
    }
};
