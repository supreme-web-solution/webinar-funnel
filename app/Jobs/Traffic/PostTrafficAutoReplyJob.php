<?php

namespace App\Jobs\Traffic;

use App\Models\SocialAccount;
use App\Models\TrafficReplyAttempt;
use App\Services\TrafficAi\SocialAccountPostingLimiter;
use App\Services\TrafficAi\TrafficReplyPoster;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use App\Support\TrafficAiLogger;

class PostTrafficAutoReplyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 15;

    public int $timeout = 120;

    public function __construct(
        public int $trafficReplyAttemptId,
        public int $socialAccountId,
    ) {
        $this->onQueue((string) config('traffic_ai.queues.post', 'traffic-post'));
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('traffic-social-'.$this->socialAccountId))
                ->releaseAfter(120)
                ->expireAfter(300),
        ];
    }

    public function handle(
        TrafficReplyPoster $poster,
        SocialAccountPostingLimiter $limiter,
    ): void {
        $attempt = TrafficReplyAttempt::query()
            ->with(['mention', 'socialAccount'])
            ->find($this->trafficReplyAttemptId);

        if (! $attempt || $attempt->status !== TrafficReplyAttempt::STATUS_QUEUED_POST) {
            TrafficAiLogger::info('PostTrafficAutoReplyJob skipped — wrong or missing attempt', [
                'attempt_id' => $this->trafficReplyAttemptId,
                'status' => $attempt?->status,
            ]);

            return;
        }

        TrafficAiLogger::info('PostTrafficAutoReplyJob started', [
            'attempt_id' => $attempt->id,
            'mention_id' => $attempt->mention_id,
        ]);

        $social = $attempt->socialAccount;
        $mention = $attempt->mention;

        if (! $social instanceof SocialAccount || ! $mention) {
            $attempt->update([
                'status' => TrafficReplyAttempt::STATUS_FAILED,
                'last_error' => 'Missing social account or mention',
            ]);

            return;
        }

        $maxDispatches = (int) config('traffic_ai.max_post_dispatches', 50);
        if ($attempt->post_dispatch_count >= $maxDispatches) {
            $attempt->update([
                'status' => TrafficReplyAttempt::STATUS_FAILED,
                'last_error' => 'Exceeded maximum post scheduling attempts',
            ]);

            return;
        }

        $wait = $limiter->secondsUntilCanPost($social);
        if ($wait > 0) {
            $attempt->increment('post_dispatch_count');
            self::dispatch($this->trafficReplyAttemptId, $this->socialAccountId)->delay(now()->addSeconds($wait));

            TrafficAiLogger::info('PostTrafficAutoReplyJob deferred for spacing', [
                'attempt_id' => $attempt->id,
                'wait_seconds' => $wait,
            ]);

            return;
        }

        $reply = (string) $attempt->reply_text;
        $result = $poster->post($social, $mention, $reply);

        if (($result['rate_limited'] ?? false) === true) {
            $attempt->increment('post_dispatch_count');
            self::dispatch($this->trafficReplyAttemptId, $this->socialAccountId)->delay(now()->addSeconds(3600));

            return;
        }

        if (! ($result['success'] ?? false)) {
            $attempt->update([
                'status' => TrafficReplyAttempt::STATUS_FAILED,
                'last_error' => (string) ($result['error'] ?? 'Post failed'),
            ]);

            TrafficAiLogger::warning('PostTrafficAutoReplyJob failed', [
                'attempt_id' => $attempt->id,
                'error' => $result['error'] ?? 'Post failed',
            ]);

            return;
        }

        $limiter->recordSuccessfulPost($social);

        $attempt->update([
            'status' => TrafficReplyAttempt::STATUS_POSTED,
            'external_comment_id' => $result['external_id'] ?? null,
            'posted_at' => now(),
            'last_error' => null,
        ]);

        $mention->update(['status' => 'replied']);

        TrafficAiLogger::info('PostTrafficAutoReplyJob posted successfully', [
            'attempt_id' => $attempt->id,
            'mention_id' => $mention->id,
            'external_comment_id' => $result['external_id'] ?? null,
        ]);
    }
}
