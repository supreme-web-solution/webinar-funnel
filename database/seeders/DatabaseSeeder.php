<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            ProductTableSeeder::class,
        ]);

        User::factory()->create([
            'name' => 'Test User',
            'username' => 'testuser',
            'email' => 'test@example.com',
        ])->assignRole('Bundle');

        // Legacy DFY catalog (~51 offers + AI knowledge + Drive links) is frozen by default.
        // Enable with SEED_DFY_TEMPLATES=true when you explicitly need the old template pack.
        if (filter_var(env('SEED_DFY_TEMPLATES', false), FILTER_VALIDATE_BOOLEAN)) {
            $this->call([
                TemplateSeeder::class,
                TemplatePaidTrafficDriveLinksSeeder::class,
                TemplateAiKnowledgeSeeder::class,
            ]);
        }
    }
}
