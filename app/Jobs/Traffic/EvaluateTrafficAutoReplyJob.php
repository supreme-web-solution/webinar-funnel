<?php

namespace App\Jobs\Traffic;

use App\Models\FunnelSetting;
use App\Models\Mention;
use App\Models\TrafficReplyAttempt;
use App\Services\TrafficAi\TrafficReplyGate;
use App\Services\TrafficAi\TrafficSocialAccountResolver;
use App\Support\TrafficAiPlatform;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class EvaluateTrafficAutoReplyJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 300;

    public int $tries = 2;

    public int $timeout = 90;

    public function __construct(
        public int $mentionId,
    ) {
        $this->onQueue((string) config('traffic_ai.queues.evaluate', 'traffic-evaluate'));
    }

    public function uniqueId(): string
    {
        return 'traffic-ai-eval:'.$this->mentionId;
    }

    public function handle(TrafficReplyGate $gate, TrafficSocialAccountResolver $accounts): void
    {
        $mention = Mention::query()
            ->with(['keyword.funnel.settings'])
            ->find($this->mentionId);

        if (! $mention || ! $mention->keyword || ! $mention->keyword->funnel) {
            return;
        }

        /** @var FunnelSetting|null $settings */
        $settings = $mention->keyword->funnel->settings;

        if (! $settings instanceof FunnelSetting || ! $settings->traffic_ai_reply_enabled) {
            return;
        }

        $attempt = TrafficReplyAttempt::query()->firstOrCreate(
            ['mention_id' => $mention->id],
            [
                'funnel_id' => $mention->keyword->funnel_id,
                'user_id' => $mention->user_id,
                'status' => TrafficReplyAttempt::STATUS_PENDING_EVALUATION,
            ]
        );

        if ($attempt->status !== TrafficReplyAttempt::STATUS_PENDING_EVALUATION) {
            return;
        }

        $platform = TrafficAiPlatform::fromMentionSource($mention->source_type);

        if ($platform === 'news' || $platform === null) {
            $attempt->update([
                'status' => TrafficReplyAttempt::STATUS_SKIPPED_UNSUPPORTED,
                'skip_reason' => 'platform_not_supported_for_replies',
            ]);

            return;
        }

        $map = is_array($settings->traffic_ai_social_account_ids)
            ? $settings->traffic_ai_social_account_ids
            : [];

        $social = $accounts->resolveForPlatform($mention->user_id, $platform, $map);

        if ($social === null) {
            $attempt->update([
                'status' => TrafficReplyAttempt::STATUS_SKIPPED_NO_ACCOUNT,
                'skip_reason' => 'no_connected_social_account_for_platform',
            ]);

            return;
        }

        $dailyCap = (int) config('traffic_ai.max_replies_per_day_per_account', 20);

        $todayCount = TrafficReplyAttempt::query()
            ->where('social_account_id', $social->id)
            ->where('status', TrafficReplyAttempt::STATUS_POSTED)
            ->whereDate('posted_at', now()->toDateString())
            ->count();

        if ($todayCount >= $dailyCap) {
            $attempt->update([
                'status' => TrafficReplyAttempt::STATUS_SKIPPED_DAILY_CAP,
                'skip_reason' => 'account_daily_reply_cap',
                'gate_details' => [
                    'cap' => $dailyCap,
                    'posted_today' => $todayCount,
                    'social_account_id' => $social->id,
                ],
            ]);

            return;
        }

        $decision = $gate->evaluate($mention, $settings);

        if (! $decision['reply']) {
            $attempt->update([
                'status' => TrafficReplyAttempt::STATUS_SKIPPED_GATE,
                'skip_reason' => 'gate_rejected',
                'gate_details' => $decision['details'],
            ]);

            return;
        }

        $attempt->update([
            'social_account_id' => $social->id,
            'status' => TrafficReplyAttempt::STATUS_GENERATING,
            'gate_details' => $decision['details'],
        ]);

        $delay = random_int(3, 18);
        GenerateTrafficAutoReplyJob::dispatch($attempt->id)->delay(now()->addSeconds($delay));

        Log::info('EvaluateTrafficAutoReplyJob: passed gate, generating', [
            'mention_id' => $mention->id,
            'attempt_id' => $attempt->id,
        ]);
    }
}
