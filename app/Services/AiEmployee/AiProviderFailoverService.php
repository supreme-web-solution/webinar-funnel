<?php

namespace App\Services\AiEmployee;

class AiProviderFailoverService
{
    /**
     * @return array<string, string>
     */
    public function providers(): array
    {
        $chain = [];
        $configured = [
            'openrouter' => (string) config('services.openrouter.api_key', ''),
            'openai' => (string) config('services.openai.api_key', ''),
            'gemini' => (string) config('ai.providers.gemini.key', ''),
            'anthropic' => (string) config('ai.providers.anthropic.key', ''),
        ];

        $models = config('ai_employee.failover', []);
        if (! is_array($models)) {
            $models = [];
        }

        foreach (['openrouter', 'openai', 'gemini', 'anthropic'] as $provider) {
            if (($configured[$provider] ?? '') === '') {
                continue;
            }
            $model = is_string($models[$provider] ?? null) ? $models[$provider] : null;
            if ($model === null || $model === '') {
                continue;
            }
            $chain[$provider] = $model;
        }

        return $chain;
    }

    public function hasAny(): bool
    {
        return $this->providers() !== [];
    }
}
