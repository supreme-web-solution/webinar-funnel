<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('keywords', function (Blueprint $table) {
            $table->foreignId('funnel_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->index(['funnel_id', 'is_active']);
        });

        Schema::table('keywords', function (Blueprint $table) {
            $table->dropUnique('keywords_user_id_name_unique');
            $table->unique(['user_id', 'funnel_id', 'name'], 'keywords_user_funnel_name_unique');
        });
    }

    public function down(): void
    {
        Schema::table('keywords', function (Blueprint $table) {
            $table->dropUnique('keywords_user_funnel_name_unique');
            $table->unique(['user_id', 'name']);
            $table->dropConstrainedForeignId('funnel_id');
        });
    }
};

