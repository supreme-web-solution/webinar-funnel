<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mentions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('keyword_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('post_id')->nullable();
            $table->string('title', 500)->nullable();
            $table->text('content')->nullable();
            $table->string('source')->nullable();
            $table->string('source_type', 30)->nullable()->index();
            $table->string('author_id')->nullable();
            $table->string('username')->nullable();
            $table->integer('like_count')->default(0);
            $table->integer('retweet_count')->default(0);
            $table->integer('comments_count')->default(0);
            $table->integer('favourite_count')->default(0);
            $table->bigInteger('views')->nullable();
            $table->integer('votes')->nullable();
            $table->string('category')->nullable();
            $table->string('status', 30)->nullable();
            $table->text('permalink')->nullable();
            $table->timestamp('posted_at')->nullable()->index();
            $table->timestamps();

            $table->index(['keyword_id', 'source_type']);
            $table->index(['user_id', 'source_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mentions');
    }
};
