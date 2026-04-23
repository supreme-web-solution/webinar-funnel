<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Laravel's `encrypted:array` cast stores ciphertext as a plain string,
     * not as a JSON value. The original `json` column type causes MySQL to
     * reject the insert with "Invalid JSON text". Change both `credentials`
     * and `config` to `text` / `nullable text` so the encrypted payload can
     * be stored without any JSON validation by the DB engine.
     */
    public function up(): void
    {
        Schema::table('integration_accounts', function (Blueprint $table) {
            $table->text('credentials')->change();
            $table->text('config')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('integration_accounts', function (Blueprint $table) {
            $table->json('credentials')->change();
            $table->json('config')->nullable()->change();
        });
    }
};
