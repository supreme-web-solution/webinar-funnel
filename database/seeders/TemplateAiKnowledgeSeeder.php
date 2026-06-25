<?php

namespace Database\Seeders;

use App\Models\Template;
use Illuminate\Database\Seeder;

class TemplateAiKnowledgeSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('data/template_ai_knowledge.php');
        if (! file_exists($path)) {
            throw new \RuntimeException(
                'Missing template_ai_knowledge.php — run: php artisan templates:generate-ai-knowledge'
            );
        }

        /** @var array<int, array{title: string, content: string, jv_page?: string|null}> $knowledgeByIndex */
        $knowledgeByIndex = require $path;

        if (count($knowledgeByIndex) !== 51) {
            throw new \RuntimeException('Expected 51 AI knowledge entries, got '.count($knowledgeByIndex));
        }

        for ($i = 1; $i <= 51; $i++) {
            $entry = $knowledgeByIndex[$i] ?? null;
            if (! is_array($entry) || trim((string) ($entry['content'] ?? '')) === '') {
                throw new \RuntimeException("Missing or empty AI knowledge for template #{$i}");
            }

            $template = Template::query()->where('sort_order', $i)->first();
            if (! $template) {
                $this->command?->warn("Template sort_order {$i} not found — skipping.");

                continue;
            }

            $version = $template->versions()->where('is_current', true)->first();
            if (! $version) {
                $this->command?->warn("No current version for template #{$i} — skipping.");

                continue;
            }

            $settings = (array) ($version->default_settings ?? []);
            $settings['webinar_ai_enabled'] = true;
            $settings['webinar_ai_auto_reply_enabled'] = true;
            $settings['webinar_ai_assistant_name'] = trim((string) ($settings['webinar_ai_assistant_name'] ?? '')) !== ''
                ? trim((string) $settings['webinar_ai_assistant_name'])
                : 'Webinar Assistant';
            $settings['webinar_ai_knowledge_title'] = trim((string) ($entry['title'] ?? 'Product Knowledge'));
            $settings['webinar_ai_knowledge_content'] = trim((string) $entry['content']);
            $settings['webinar_ai_knowledge_jv_page'] = filled($entry['jv_page'] ?? null)
                ? (string) $entry['jv_page']
                : null;

            $version->update(['default_settings' => $settings]);
        }
    }
}
