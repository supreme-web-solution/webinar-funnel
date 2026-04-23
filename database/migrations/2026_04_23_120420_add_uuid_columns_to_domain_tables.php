<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * @var array<int, string>
     */
    private array $tables = [
        'users',
        'templates',
        'template_versions',
        'funnels',
        'funnel_pages',
        'funnel_settings',
        'integration_accounts',
        'funnel_integrations',
        'leads',
        'lead_events',
        'dispatch_job_logs',
        'chat_rooms',
        'chat_messages',
        'chat_participants',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $table->uuid('uuid')->nullable()->unique()->after('id');
            });

            DB::table($tableName)
                ->whereNull('uuid')
                ->orderBy('id')
                ->select(['id'])
                ->each(function (object $row) use ($tableName): void {
                    DB::table($tableName)
                        ->where('id', $row->id)
                        ->update(['uuid' => (string) Str::uuid()]);
                });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $table->dropUnique($tableName.'_uuid_unique');
                $table->dropColumn('uuid');
            });
        }
    }
};
