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
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->string('conversation_key')->nullable()->index()->after('chat_room_id');
            $table->string('attendee_name')->nullable()->after('participant_role');
            $table->string('attendee_email')->nullable()->index()->after('attendee_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropColumn([
                'conversation_key',
                'attendee_name',
                'attendee_email',
            ]);
        });
    }
};
