<?php

namespace App\Services\Promotion;

use App\Models\FunnelPromotionPost;
use App\Models\SocialAccount;
use App\Services\TrafficAi\TrafficSocialAccountResolver;
use App\Services\Zernio\ZernioClient;
use Illuminate\Support\Facades\Log;

class PromotionPublisherService
{
    /** @var list<string> */
    private const MEDIA_REQUIRED_PLATFORMS = ['instagram', 'pinterest', 'tiktok'];

    public function __construct(
        private readonly ZernioClient $zernioClient,
        private readonly TrafficSocialAccountResolver $accountResolver,
        private readonly PromotionGenerationCoordinator $generation,
    ) {}

    /**
     * @return array{success: bool, partial?: bool, published: array<int, array{platform: string, external_id: string|null, url: string|null}>, failures: array<int, array{platform: string, error: string}>, zernio_post_id?: string|null}
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

        $readiness = $this->generation->readinessErrors($post);
        if ($readiness !== []) {
            return [
                'success' => false,
                'published' => [],
                'failures' => [['platform' => 'unknown', 'error' => implode('; ', $readiness)]],
            ];
        }

        $body = $this->buildCaption($post);
        $mediaUrls = $this->mediaUrlsForPost($post);
        $mediaType = $this->mediaTypeForPost($post);
        $linkUrl = is_string($post->cta_url) && $post->cta_url !== '' ? $post->cta_url : null;

        $published = [];
        $failures = [];
        $lastZernioPostId = null;

        foreach ($platforms as $platform) {
            $account = $this->resolveAccount($post->user_id, $platform, $funnel->settings?->traffic_ai_social_account_ids);
            if (! $account) {
                $failures[] = [
                    'platform' => $platform,
                    'error' => 'No connected social account for platform',
                ];
                continue;
            }

            if ($mediaUrls === [] && in_array($platform, self::MEDIA_REQUIRED_PLATFORMS, true)) {
                $failures[] = [
                    'platform' => $platform,
                    'error' => ucfirst($platform).' requires an image or video attachment.',
                ];
                continue;
            }

            $target = [
                'platform' => $platform,
                'accountId' => (string) $account->zernio_account_id,
            ];

            if ($mediaUrls !== [] && $platform === 'instagram') {
                $target['customMedia'] = array_map(
                    fn (string $url): array => ['type' => $mediaType ?? 'image', 'url' => $url],
                    $mediaUrls
                );
            }

            $adapted = $this->adaptContentForPlatform($platform, $body, $mediaType, $post);
            if ($adapted['platformSpecificData'] !== []) {
                $target['platformSpecificData'] = $adapted['platformSpecificData'];
            }

            Log::info('[Promotion] publishing to platform', [
                'post_id' => $post->id,
                'platform' => $platform,
                'media_items' => count($mediaUrls),
                'content_length' => mb_strlen($adapted['content']),
                'adapted' => $adapted['content'] !== $body,
            ]);

            $result = $this->zernioClient->createPost(
                content: $adapted['content'],
                platforms: [$target],
                mediaUrls: $mediaUrls,
                mediaType: $mediaType,
                linkUrl: $mediaUrls === [] ? $linkUrl : null,
                publishNow: true,
            );

            if (! ($result['success'] ?? false)) {
                $failures[] = [
                    'platform' => $platform,
                    'error' => (string) ($result['error'] ?? 'Publish failed'),
                ];
                continue;
            }

            if (is_string($result['zernio_post_id'] ?? null) && $result['zernio_post_id'] !== '') {
                $lastZernioPostId = $result['zernio_post_id'];
            }

            $row = collect($result['published'] ?? [])->firstWhere('platform', $platform);
            if (! is_array($row)) {
                $row = is_array($result['published'][0] ?? null) ? $result['published'][0] : null;
            }

            $externalId = is_array($row) && is_string($row['external_id'] ?? null) ? $row['external_id'] : null;
            if ($externalId === null || $externalId === '') {
                $failures[] = [
                    'platform' => $platform,
                    'error' => (string) ($result['error'] ?? 'Platform did not confirm the post was created.'),
                ];
                continue;
            }

            $published[] = [
                'platform' => $platform,
                'external_id' => $externalId,
                'url' => is_array($row) && is_string($row['url'] ?? null) ? $row['url'] : null,
            ];
        }

        return [
            'success' => $published !== [],
            'partial' => $published !== [] && $failures !== [],
            'published' => $published,
            'failures' => $failures,
            'zernio_post_id' => $lastZernioPostId,
        ];
    }

    private function buildCaption(FunnelPromotionPost $post): string
    {
        $body = trim((string) $post->text_body);
        if ($body === '') {
            $body = trim((string) $post->email_body);
        }

        $hashtags = array_values(array_filter($post->hashtags ?? [], fn ($tag) => is_string($tag) && $tag !== ''));
        if ($hashtags !== []) {
            $body = trim($body."\n\n".implode(' ', array_map(
                fn (string $tag): string => str_starts_with($tag, '#') ? $tag : '#'.$tag,
                $hashtags
            )));
        }

        $ctaUrl = is_string($post->cta_url) && $post->cta_url !== '' ? $post->cta_url : null;
        $ctaLabel = is_string($post->cta_label) && $post->cta_label !== '' ? $post->cta_label : 'Learn more';
        if ($ctaUrl !== null && $this->mediaUrlsForPost($post) !== []) {
            $body = trim($body."\n\n{$ctaLabel}: {$ctaUrl}");
        }

        return $body;
    }

    /**
     * @return array{content: string, platformSpecificData: array<string, mixed>}
     */
    private function adaptContentForPlatform(
        string $platform,
        string $fullCaption,
        ?string $mediaType,
        FunnelPromotionPost $post,
    ): array {
        if ($platform === 'tiktok') {
            return $this->adaptTikTokContent($fullCaption, $mediaType, $post);
        }

        if ($platform === 'youtube') {
            return $this->adaptYouTubeContent($fullCaption, $post);
        }

        return ['content' => $fullCaption, 'platformSpecificData' => []];
    }

    /**
     * @return array{content: string, platformSpecificData: array<string, mixed>}
     */
    private function adaptTikTokContent(string $fullCaption, ?string $mediaType, FunnelPromotionPost $post): array
    {
        $isPhoto = ($mediaType ?? 'image') !== 'video';

        if ($isPhoto) {
            $titleLimit = (int) config('promotion.platform_content_limits.tiktok_photo', 90);
            $descriptionLimit = (int) config('promotion.platform_content_limits.tiktok_photo_description', 4000);
            $shortTitle = $this->truncateText($this->shortTitleSource($post, $fullCaption), $titleLimit);

            return [
                'content' => $shortTitle,
                'platformSpecificData' => [
                    'description' => $this->truncateText($fullCaption, $descriptionLimit),
                ],
            ];
        }

        $videoLimit = (int) config('promotion.platform_content_limits.tiktok_video', 2200);

        return [
            'content' => $this->truncateText($fullCaption, $videoLimit),
            'platformSpecificData' => [],
        ];
    }

    /**
     * @return array{content: string, platformSpecificData: array<string, mixed>}
     */
    private function adaptYouTubeContent(string $fullCaption, FunnelPromotionPost $post): array
    {
        $titleLimit = (int) config('promotion.platform_content_limits.youtube_title', 100);

        return [
            'content' => $fullCaption,
            'platformSpecificData' => [
                'title' => $this->truncateText($this->shortTitleSource($post, $fullCaption), $titleLimit),
            ],
        ];
    }

    private function shortTitleSource(FunnelPromotionPost $post, string $fullCaption): string
    {
        $title = trim((string) ($post->title ?? ''));
        if ($title !== '') {
            return $title;
        }

        $topic = trim((string) ($post->topic ?? ''));
        if ($topic !== '') {
            return (string) preg_replace('/\s*\(copy\)\s*$/i', '', $topic);
        }

        $body = trim((string) $post->text_body);
        if ($body === '') {
            $body = trim((string) $post->email_body);
        }
        if ($body !== '') {
            $firstLine = trim(strtok($body, "\n") ?: $body);

            return $firstLine;
        }

        return trim(strtok($fullCaption, "\n") ?: $fullCaption);
    }

    private function truncateText(string $text, int $maxLength): string
    {
        $text = trim($text);
        if ($maxLength <= 0 || mb_strlen($text) <= $maxLength) {
            return $text;
        }

        if ($maxLength === 1) {
            return '…';
        }

        $cut = mb_substr($text, 0, $maxLength - 1);
        $lastSpace = mb_strrpos($cut, ' ');
        if ($lastSpace !== false && $lastSpace > (int) ($maxLength * 0.6)) {
            $cut = mb_substr($cut, 0, $lastSpace);
        }

        return rtrim($cut, " \t\n\r\0\x0B.,;:!?").'…';
    }

    /**
     * @return list<string>
     */
    private function mediaUrlsForPost(FunnelPromotionPost $post): array
    {
        if (! is_string($post->primaryAsset?->url) || $post->primaryAsset->url === '') {
            return [];
        }

        return [$post->primaryAsset->url];
    }

    private function mediaTypeForPost(FunnelPromotionPost $post): ?string
    {
        $assetType = (string) ($post->primaryAsset?->asset_type ?? '');

        return match ($assetType) {
            'video' => 'video',
            'image' => 'image',
            default => $post->content_type === FunnelPromotionPost::TYPE_VIDEO ? 'video' : 'image',
        };
    }

    /**
     * @param  array<string, mixed>|null  $map
     */
    private function resolveAccount(int $userId, string $platform, ?array $map): ?SocialAccount
    {
        return $this->accountResolver->resolveForPlatform($userId, $platform, $map);
    }
}
