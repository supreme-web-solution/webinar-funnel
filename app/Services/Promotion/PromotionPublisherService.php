<?php

namespace App\Services\Promotion;

use App\Models\FunnelPromotionPost;
use App\Models\SocialAccount;
use App\Services\TrafficAi\TrafficSocialAccountResolver;
use App\Services\Zernio\ZernioClient;

class PromotionPublisherService
{
    public function __construct(
        private readonly ZernioClient $zernioClient,
        private readonly TrafficSocialAccountResolver $accountResolver,
    ) {}

    /**
     * @return array{success: bool, published: array<int, array{platform: string, external_id: string|null}>, failures: array<int, array{platform: string, error: string}>}
     */
    public function publish(FunnelPromotionPost $post): array
    {
        $funnel = $post->funnel()->with('settings')->first();
        if (! $funnel) {
            return [
                'success' => false,
                'published' => [],
                'failures' => [['platform' => 'unknown', 'error' => 'Funnel not found for post']],
            ];
        }

        $platforms = array_values(array_filter($post->platforms ?? []));
        if ($platforms === []) {
            return [
                'success' => false,
                'published' => [],
                'failures' => [['platform' => 'unknown', 'error' => 'No platforms selected']],
            ];
        }

        $published = [];
        $failures = [];
        $body = trim((string) $post->text_body);
        if ($body === '') {
            $body = trim((string) $post->email_body);
        }

        foreach ($platforms as $platform) {
            $account = $this->resolveAccount($post->user_id, $platform, $funnel->settings?->traffic_ai_social_account_ids);
            if (! $account) {
                $failures[] = [
                    'platform' => $platform,
                    'error' => 'No connected social account for platform',
                ];
                continue;
            }

            $result = $this->zernioClient->createPost(
                accountId: (string) $account->zernio_account_id,
                platform: $platform,
                text: $body,
                mediaUrl: $post->primaryAsset?->url,
                scheduledFor: null
            );

            if (! ($result['success'] ?? false)) {
                $failures[] = [
                    'platform' => $platform,
                    'error' => (string) ($result['error'] ?? 'Publish failed'),
                ];
                continue;
            }

            $published[] = [
                'platform' => $platform,
                'external_id' => is_string($result['external_id'] ?? null) ? $result['external_id'] : null,
            ];
        }

        return [
            'success' => $failures === [],
            'published' => $published,
            'failures' => $failures,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $map
     */
    private function resolveAccount(int $userId, string $platform, ?array $map): ?SocialAccount
    {
        return $this->accountResolver->resolveForPlatform($userId, $platform, $map);
    }
}
