<?php

namespace App\Services\Campaigns;

use App\Models\Campaign;

class CampaignGenerationProgressService
{
    /**
     * @param  array<string, mixed>  $extra
     */
    public function start(Campaign $campaign, string $step, string $message, array $extra = []): void
    {
        $this->write($campaign, [
            'status' => 'running',
            'step' => $step,
            'phase' => 'queued',
            'message' => $message,
            'detail' => $extra['detail'] ?? null,
            'progress' => (int) ($extra['progress'] ?? 0),
            'started_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
            'error' => null,
            'events' => [[
                'at' => now()->toIso8601String(),
                'phase' => 'queued',
                'message' => $message,
                'detail' => $extra['detail'] ?? null,
            ]],
            ...$extra,
        ]);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public function update(Campaign $campaign, string $phase, string $message, int $progress = 0, array $extra = []): void
    {
        $current = $this->get($campaign) ?? [];
        $events = $current['events'] ?? [];
        $events[] = [
            'at' => now()->toIso8601String(),
            'phase' => $phase,
            'message' => $message,
            'detail' => $extra['detail'] ?? null,
        ];
        if (count($events) > 25) {
            $events = array_slice($events, -25);
        }

        $this->write($campaign, [
            ...$current,
            'status' => 'running',
            'phase' => $phase,
            'message' => $message,
            'detail' => $extra['detail'] ?? ($current['detail'] ?? null),
            'progress' => max($progress, (int) ($current['progress'] ?? 0)),
            'updated_at' => now()->toIso8601String(),
            'error' => null,
            'events' => $events,
            ...$extra,
        ]);
    }

    public function complete(Campaign $campaign, string $message, array $extra = []): void
    {
        $current = $this->get($campaign) ?? [];
        $events = $current['events'] ?? [];
        $events[] = [
            'at' => now()->toIso8601String(),
            'phase' => 'completed',
            'message' => $message,
            'detail' => $extra['detail'] ?? null,
        ];

        $this->write($campaign, [
            ...$current,
            'status' => 'completed',
            'phase' => 'completed',
            'message' => $message,
            'progress' => 100,
            'updated_at' => now()->toIso8601String(),
            'completed_at' => now()->toIso8601String(),
            'error' => null,
            'events' => $events,
            ...$extra,
        ]);
    }

    public function fail(Campaign $campaign, string $error): void
    {
        $current = $this->get($campaign) ?? [];
        $events = $current['events'] ?? [];
        $events[] = [
            'at' => now()->toIso8601String(),
            'phase' => 'failed',
            'message' => 'Generation failed',
            'detail' => $error,
        ];

        $this->write($campaign, [
            ...$current,
            'status' => 'failed',
            'phase' => 'failed',
            'message' => 'Generation failed',
            'detail' => $error,
            'error' => $error,
            'updated_at' => now()->toIso8601String(),
            'events' => $events,
        ]);
    }

    public function idle(Campaign $campaign): void
    {
        $meta = $campaign->meta ?? [];
        unset($meta['generation']);
        $campaign->update(['meta' => $meta]);
        $campaign->refresh();
    }

    public function isRunning(Campaign $campaign): bool
    {
        return ($this->get($campaign)['status'] ?? '') === 'running';
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(Campaign $campaign): ?array
    {
        $gen = $campaign->fresh()?->meta['generation'] ?? null;

        return is_array($gen) ? $gen : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function write(Campaign $campaign, array $data): void
    {
        $meta = $campaign->meta ?? [];
        $meta['generation'] = $data;
        $campaign->update(['meta' => $meta]);
    }
}
