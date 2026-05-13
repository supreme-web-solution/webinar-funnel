<?php

namespace App\Observers;

use App\Jobs\Traffic\EvaluateTrafficAutoReplyJob;
use App\Models\Mention;

class MentionObserver
{
    public function created(Mention $mention): void
    {
        if (! config('traffic_ai.enabled', true) || ! config('traffic_ai.dispatch_on_mention_created', true)) {
            return;
        }

        $mention->loadMissing('keyword.funnel.settings');

        $settings = $mention->keyword?->funnel?->settings;

        if (! $settings || ! $settings->traffic_ai_reply_enabled) {
            return;
        }

        EvaluateTrafficAutoReplyJob::dispatch($mention->id);
    }
}
