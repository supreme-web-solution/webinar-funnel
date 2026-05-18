<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->json('vendor_contact')->nullable()->after('suggested_keywords');
        });

        Schema::table('funnel_settings', function (Blueprint $table) {
            $table->json('vendor_contact')->nullable()->after('jv_page');
        });
    }

    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->dropColumn('vendor_contact');
        });

        Schema::table('funnel_settings', function (Blueprint $table) {
            $table->dropColumn('vendor_contact');
        });
    }
};
