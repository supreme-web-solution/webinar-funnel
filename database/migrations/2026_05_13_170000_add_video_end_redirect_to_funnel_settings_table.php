<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('funnel_settings', function (Blueprint $table) {
            $table->boolean('redirect_enabled')->default(false)->after('exit_popup_cta_url');
            $table->text('redirect_url')->nullable()->after('redirect_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('funnel_settings', function (Blueprint $table) {
            $table->dropColumn([
                'redirect_enabled',
                'redirect_url',
            ]);
        });
    }
};

