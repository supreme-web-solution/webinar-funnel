<?php

namespace App\Observers;

use App\Jobs\Traffic\EvaluateTrafficAutoReplyJob;
use App\Models\Mention;
use App\Support\TrafficAiLogger;

class MentionObserver
{
    public function created(Mention $mention): void
    {
        if (! config('traffic_ai.enabled', true)) {
            TrafficAiLogger::info('mention created — auto-reply disabled globally', [
                'mention_id' => $mention->id,
            ]);

            return;
        }

        if (! config('traffic_ai.dispatch_on_mention_created', true)) {
            TrafficAiLogger::info('mention created — dispatch_on_mention_created is off', [
                'mention_id' => $mention->id,
            ]);

            return;
        }

        $mention->loadMissing('keyword.funnel.settings');

        $settings = $mention->keyword?->funnel?->settings;

        if (! $settings) {
            TrafficAiLogger::info('mention created — skipped, no funnel settings', [
                'mention_id' => $mention->id,
                'keyword_id' => $mention->keyword_id,
            ]);

            return;
        }

        if (! $settings->traffic_ai_reply_enabled) {
            TrafficAiLogger::info('mention created — skipped, traffic_ai_reply_enabled is off', [
                'mention_id' => $mention->id,
                'funnel_id' => $mention->keyword?->funnel_id,
            ]);

            return;
        }

        EvaluateTrafficAutoReplyJob::dispatch($mention->id);

        TrafficAiLogger::info('mention created — dispatched EvaluateTrafficAutoReplyJob', [
            'mention_id' => $mention->id,
            'funnel_id' => $mention->keyword?->funnel_id,
            'source_type' => $mention->source_type,
            'queue' => config('traffic_ai.queues.evaluate', 'traffic-evaluate'),
        ]);
    }
}
