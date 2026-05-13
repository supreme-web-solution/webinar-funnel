<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('funnel_settings', function (Blueprint $table) {
            $table->json('offers')->nullable()->after('branding');
            $table->boolean('exit_popup_enabled')->default(false)->after('offers');
            $table->boolean('exit_popup_show_close')->default(true)->after('exit_popup_enabled');
            $table->string('exit_popup_title')->nullable()->after('exit_popup_show_close');
            $table->text('exit_popup_description')->nullable()->after('exit_popup_title');
            $table->string('exit_popup_cta_label')->nullable()->after('exit_popup_description');
            $table->text('exit_popup_cta_url')->nullable()->after('exit_popup_cta_label');
        });
    }

    public function down(): void
    {
        Schema::table('funnel_settings', function (Blueprint $table) {
            $table->dropColumn([
                'offers',
                'exit_popup_enabled',
                'exit_popup_show_close',
                'exit_popup_title',
                'exit_popup_description',
                'exit_popup_cta_label',
                'exit_popup_cta_url',
            ]);
        });
    }
};

