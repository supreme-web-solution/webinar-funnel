<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('funnel_ai_source_chunks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->foreignId('funnel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('funnel_ai_source_id')->constrained('funnel_ai_sources')->cascadeOnDelete();
            $table->unsignedInteger('chunk_index');
            $table->text('content');
            $table->json('embedding')->nullable();
            $table->unsignedInteger('embedding_dimensions')->default(0);
            $table->timestamps();

            $table->index(['funnel_id', 'funnel_ai_source_id']);
            $table->index(['funnel_id', 'chunk_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funnel_ai_source_chunks');
    }
};

