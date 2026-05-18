<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('zernio_profile_id', 64)->nullable()->after('email');
        });

        Schema::table('social_accounts', function (Blueprint $table) {
            $table->string('zernio_account_id', 64)->nullable()->after('platform_user_id');
        });

        Schema::table('mentions', function (Blueprint $table) {
            $table->string('reply_target_id', 120)->nullable()->after('post_id');
        });
    }

    public function down(): void
    {
        Schema::table('mentions', function (Blueprint $table) {
            $table->dropColumn('reply_target_id');
        });

        Schema::table('social_accounts', function (Blueprint $table) {
            $table->dropColumn('zernio_account_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('zernio_profile_id');
        });
    }
};
