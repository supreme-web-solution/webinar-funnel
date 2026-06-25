<?php

namespace App\Services\Funnels;

use App\Jobs\IndexFunnelAiSourceJob;
use App\Models\Funnel;
use App\Models\FunnelAiSource;
use App\Models\Template;

class FunnelAiKnowledgeProvisioner
{
    /**
     * Enable AI assistant settings and seed the pre-built knowledge source for a new funnel.
     *
     * @param  array<string, mixed>  $defaultSettings
     * @return array<string, mixed>
     */
    public function applyTemplateKnowledge(Funnel $funnel, Template $template, array $defaultSettings, bool $isScratch): array
    {
        if ($isScratch) {
            return $defaultSettings;
        }

        $knowledge = $this->resolveKnowledgeFromSettings($defaultSettings)
            ?? $this->resolveKnowledgeForTemplate($template);
        if ($knowledge === null) {
            return $defaultSettings;
        }

        $defaultSettings['webinar_ai_enabled'] = true;
        $defaultSettings['webinar_ai_auto_reply_enabled'] = true;
        $defaultSettings['webinar_ai_assistant_name'] = trim((string) ($defaultSettings['webinar_ai_assistant_name'] ?? '')) !== ''
            ? trim((string) $defaultSettings['webinar_ai_assistant_name'])
            : 'Webinar Assistant';

        $defaultSettings['_pending_ai_knowledge'] = [
            'title' => $knowledge['title'],
            'content' => $knowledge['content'],
            'source_url' => $knowledge['jv_page'] ?? null,
        ];

        return $defaultSettings;
    }

    /**
     * Create the FunnelAiSource record and queue indexing after funnel settings are saved.
     *
     * @param  array<string, mixed>  $defaultSettings
     */
    public function provisionSources(Funnel $funnel, array $defaultSettings): void
    {
        $pending = $defaultSettings['_pending_ai_knowledge'] ?? null;
        if (! is_array($pending) || trim((string) ($pending['content'] ?? '')) === '') {
            return;
        }

        $existing = FunnelAiSource::query()->where('funnel_id', $funnel->id)->count();
        if ($existing > 0) {
            return;
        }

        $source = FunnelAiSource::query()->create([
            'funnel_id' => $funnel->id,
            'type' => FunnelAiSource::TYPE_TEXT,
            'title' => trim((string) ($pending['title'] ?? '')) ?: 'Product Knowledge',
            'source_url' => filled($pending['source_url'] ?? null) ? (string) $pending['source_url'] : null,
            'status' => 'queued',
            'content' => trim((string) $pending['content']),
            'chunk_count' => 0,
        ]);

        IndexFunnelAiSourceJob::dispatch((int) $source->id);
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array{title: string, content: string, jv_page: string|null}|null
     */
    public function resolveKnowledgeFromSettings(array $settings): ?array
    {
        $content = trim((string) ($settings['webinar_ai_knowledge_content'] ?? ''));
        if ($content === '') {
            return null;
        }

        return [
            'title' => trim((string) ($settings['webinar_ai_knowledge_title'] ?? 'Product Knowledge')) ?: 'Product Knowledge',
            'content' => $content,
            'jv_page' => filled($settings['webinar_ai_knowledge_jv_page'] ?? null)
                ? (string) $settings['webinar_ai_knowledge_jv_page']
                : null,
        ];
    }

    /**
     * @return array{title: string, content: string, jv_page: string|null}|null
     */
    public function resolveKnowledgeForTemplate(Template $template): ?array
    {
        $sortOrder = (int) $template->sort_order;
        if ($sortOrder < 1) {
            return null;
        }

        $path = database_path('data/template_ai_knowledge.php');
        if (! file_exists($path)) {
            return null;
        }

        /** @var array<int, array{title: string, content: string, jv_page?: string|null}> $knowledgeByIndex */
        $knowledgeByIndex = require $path;
        $entry = $knowledgeByIndex[$sortOrder] ?? null;

        if (! is_array($entry) || trim((string) ($entry['content'] ?? '')) === '') {
            return null;
        }

        return [
            'title' => trim((string) ($entry['title'] ?? 'Product Knowledge')) ?: 'Product Knowledge',
            'content' => trim((string) $entry['content']),
            'jv_page' => filled($entry['jv_page'] ?? null) ? (string) $entry['jv_page'] : null,
        ];
    }

    public function backfillExistingFunnel(Funnel $funnel): bool
    {
        $funnel->loadMissing(['template', 'settings']);

        if (! $funnel->template || ! $funnel->settings) {
            return false;
        }

        $knowledge = $this->resolveKnowledgeForTemplate($funnel->template);
        if ($knowledge === null) {
            return false;
        }

        $settings = $funnel->settings;
        if (! $settings->webinar_ai_enabled) {
            $settings->forceFill([
                'webinar_ai_enabled' => true,
                'webinar_ai_auto_reply_enabled' => true,
                'webinar_ai_assistant_name' => trim((string) $settings->webinar_ai_assistant_name) !== ''
                    ? $settings->webinar_ai_assistant_name
                    : 'Webinar Assistant',
            ])->saveQuietly();
        }

        $this->provisionSources($funnel, [
            '_pending_ai_knowledge' => [
                'title' => $knowledge['title'],
                'content' => $knowledge['content'],
                'source_url' => $knowledge['jv_page'],
            ],
        ]);

        return FunnelAiSource::query()->where('funnel_id', $funnel->id)->exists();
    }
}
