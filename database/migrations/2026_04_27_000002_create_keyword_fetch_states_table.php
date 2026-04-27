<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('keyword_fetch_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('keyword_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 20);
            $table->timestamp('last_fetch_at')->nullable();
            $table->timestamp('next_fetch_at')->nullable();
            $table->timestamp('cooldown_until')->nullable();
            $table->timestamps();

            $table->unique(['keyword_id', 'platform']);
            $table->index(['platform', 'next_fetch_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keyword_fetch_states');
    }
};
