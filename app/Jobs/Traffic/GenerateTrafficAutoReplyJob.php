<?php

namespace App\Jobs\Traffic;

use App\Models\TrafficReplyAttempt;
use App\Services\TrafficAi\TrafficReplyGenerator;
use App\Support\TrafficAiPlatform;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateTrafficAutoReplyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        public int $trafficReplyAttemptId,
    ) {
        $this->onQueue((string) config('traffic_ai.queues.generate', 'traffic-generate'));
    }

    public function handle(TrafficReplyGenerator $generator): void
    {
        $attempt = TrafficReplyAttempt::query()
            ->with(['mention', 'funnel.settings', 'socialAccount'])
            ->find($this->trafficReplyAttemptId);

        if (! $attempt || $attempt->status !== TrafficReplyAttempt::STATUS_GENERATING) {
            return;
        }

        $mention = $attempt->mention;
        $settings = $attempt->funnel?->settings;
        $social = $attempt->socialAccount;

        if (! $mention || ! $settings || ! $social) {
            $attempt->update([
                'status' => TrafficReplyAttempt::STATUS_FAILED,
                'last_error' => 'Missing mention, settings, or social account',
            ]);

            return;
        }

        $platform = TrafficAiPlatform::fromMentionSource($mention->source_type) ?? 'reddit';
        $link = $settings->effectiveTrafficAffiliateLink();

        if ($link === null || $link === '') {
            $attempt->update([
                'status' => TrafficReplyAttempt::STATUS_FAILED,
                'last_error' => 'No affiliate / override link configured for this funnel',
            ]);

            return;
        }

        $text = $generator->generate($mention, $settings, $link, $platform);

        if ($text === null || $text === '') {
            $attempt->update([
                'status' => TrafficReplyAttempt::STATUS_FAILED,
                'last_error' => 'Empty generated reply',
            ]);

            return;
        }

        $attempt->update([
            'reply_text' => $text,
            'status' => TrafficReplyAttempt::STATUS_QUEUED_POST,
        ]);

        $delay = random_int(5, 25);
        PostTrafficAutoReplyJob::dispatch($attempt->id, (int) $social->id)->delay(now()->addSeconds($delay));

        Log::info('GenerateTrafficAutoReplyJob: reply queued for posting', [
            'attempt_id' => $attempt->id,
            'delay' => $delay,
        ]);
    }
}
