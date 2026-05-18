<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('funnel_page_views', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('funnel_id')->constrained()->cascadeOnDelete();
            $table->string('page_type', 20);
            $table->string('session_key', 80);
            $table->timestamp('viewed_at');

            $table->index(['funnel_id', 'viewed_at']);
            $table->index(['funnel_id', 'page_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funnel_page_views');
    }
};
