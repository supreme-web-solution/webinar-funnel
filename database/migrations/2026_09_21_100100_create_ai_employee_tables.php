<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_employee_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('killed')->default(false);
            $table->string('autonomy', 32)->default('assisted');
            $table->json('execute_allowlist')->nullable();
            $table->string('whatsapp_phone', 32)->nullable()->index();
            $table->string('pairing_code', 32)->nullable()->index();
            $table->timestamp('pairing_expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_employee_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('conversation_id', 36)->nullable()->index();
            $table->string('channel', 32)->default('web');
            $table->string('zernio_conversation_id')->nullable();
            $table->string('zernio_account_id')->nullable();
            $table->timestamp('processing_at')->nullable();
            $table->string('progress')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_action_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('conversation_id', 36)->nullable()->index();
            $table->string('tool_name', 80);
            $table->string('permission', 32)->default('execute');
            $table->string('summary', 500);
            $table->json('payload')->nullable();
            $table->string('status', 32)->default('pending');
            $table->text('result')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::create('ai_action_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('conversation_id', 36)->nullable()->index();
            $table->string('tool_name', 80);
            $table->string('permission', 32);
            $table->string('channel', 32)->default('web');
            $table->string('status', 32)->default('ok');
            $table->json('arguments')->nullable();
            $table->text('result')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_action_logs');
        Schema::dropIfExists('ai_action_approvals');
        Schema::dropIfExists('ai_employee_sessions');
        Schema::dropIfExists('ai_employee_settings');
    }
};
