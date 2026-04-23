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
        Schema::create('dispatch_job_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_event_id')->constrained()->cascadeOnDelete();
            $table->string('provider')->index();
            $table->string('status')->default('pending')->index();
            $table->unsignedSmallInteger('attempt')->default(1);
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['lead_event_id', 'attempt']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dispatch_job_logs');
    }
};
