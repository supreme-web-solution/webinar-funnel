<?php

namespace App\Services\Promotion;

use App\Models\FunnelPromotionPost;
use App\Models\SocialAccount;
use App\Services\Content\PlatformFormatCatalog;
use App\Services\Social\TwitterTextLimiter;
use App\Services\TrafficAi\TrafficSocialAccountResolver;
use App\Services\Zernio\ZernioClient;
use Illuminate\Support\Facades\Log;

class PromotionPublisherService
{
    /** @var list<string> */
    private const MEDIA_REQUIRED_PLATFORMS = ['instagram', 'tiktok', 'pinterest'];

    public function __construct(
        private readonly ZernioClient $zernioClient,
        private readonly TrafficSocialAccountResolver $accountResolver,
        private readonly PromotionGenerationCoordinator $generation,
        private readonly PlatformFormatCatalog $formatCatalog,
        private readonly TwitterTextLimiter $twitterTextLimiter,
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

            if ($mediaUrls !== [] && in_array($platform, ['instagram', 'tiktok', 'facebook', 'linkedin', 'pinterest'], true)) {
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
                'thread_items' => count($adapted['platformSpecificData']['threadItems'] ?? []),
            ]);

            $threadItems = $adapted['platformSpecificData']['threadItems'] ?? null;
            $isTwitterThread = $platform === 'twitter' && is_array($threadItems) && count($threadItems) >= 2;
            $threadMode = (string) config('promotion.zernio.twitter_thread_publish_mode', 'chain');

            if ($isTwitterThread && $threadMode === 'chain') {
                $result = $this->publishTwitterThreadChain(
                    post: $post,
                    account: $account,
                    threadItems: $threadItems,
                    mediaUrls: $mediaUrls,
                    mediaType: $mediaType,
                );
            } else {
                $result = $this->zernioClient->createPost(
                    content: $adapted['content'],
                    platforms: [$target],
                    mediaUrls: $mediaUrls,
                    mediaType: $mediaType,
                    linkUrl: $mediaUrls === [] && ! isset($adapted['platformSpecificData']['threadItems']) ? $linkUrl : null,
                    publishNow: true,
                );

                if ($isTwitterThread && ($result['success'] ?? false) && is_string($result['zernio_post_id'] ?? null)) {
                    $result = $this->verifyTwitterThreadResult($result, count($threadItems), $post->id);
                }
            }

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

            foreach (is_array($result['failures'] ?? null) ? $result['failures'] : [] as $failure) {
                if (is_array($failure)) {
                    $failures[] = $failure;
                }
            }
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

        if ($platform === 'twitter') {
            return $this->adaptTwitterContent($fullCaption, $post);
        }

        if ($platform === 'reddit') {
            return $this->adaptRedditContent($fullCaption, $post);
        }

        if ($platform === 'pinterest') {
            return $this->adaptPinterestContent($fullCaption, $post);
        }

        if ($platform === 'linkedin') {
            return $this->adaptLinkedInContent($fullCaption, $post);
        }

        if ($platform === 'threads') {
            return $this->adaptThreadsContent($fullCaption, $post);
        }

        if ($platform === 'facebook') {
            return $this->adaptFacebookContent($fullCaption, $post);
        }

        if ($platform === 'instagram') {
            return $this->adaptInstagramContent($fullCaption, $post);
        }

        return ['content' => $fullCaption, 'platformSpecificData' => []];
    }

    /**
     * @return array{content: string, platformSpecificData: array<string, mixed>}
     */
    private function adaptInstagramContent(string $fullCaption, FunnelPromotionPost $post): array
    {
        return ['content' => $fullCaption, 'platformSpecificData' => []];
    }

    /**
     * @return array{content: string, platformSpecificData: array<string, mixed>}
     */
    private function adaptFacebookContent(string $fullCaption, FunnelPromotionPost $post): array
    {
        return ['content' => $fullCaption, 'platformSpecificData' => []];
    }

    /**
     * @return array{content: string, platformSpecificData: array<string, mixed>}
     */
    private function adaptLinkedInContent(string $fullCaption, FunnelPromotionPost $post): array
    {
        $payload = $this->formatPayload($post);
        if (($payload['generator'] ?? '') === 'poll' && is_string($payload['question'] ?? null)) {
            return [
                'content' => (string) $payload['question'],
                'platformSpecificData' => [
                    'pollOptions' => is_array($payload['options'] ?? null) ? $payload['options'] : [],
                ],
            ];
        }

        return ['content' => $fullCaption, 'platformSpecificData' => []];
    }

    /**
     * @return array{content: string, platformSpecificData: array<string, mixed>}
     */
    private function adaptThreadsContent(string $fullCaption, FunnelPromotionPost $post): array
    {
        $payload = $this->formatPayload($post);
        if (($payload['generator'] ?? '') === 'poll' && is_string($payload['question'] ?? null)) {
            return [
                'content' => (string) $payload['question'],
                'platformSpecificData' => [
                    'pollOptions' => is_array($payload['options'] ?? null) ? $payload['options'] : [],
                ],
            ];
        }

        if (($payload['generator'] ?? '') === 'thread') {
            $parts = $this->threadPartsFromPayload($payload, $fullCaption);
            if (count($parts) >= 2) {
                $threadItems = array_map(
                    fn (string $part): array => ['content' => $this->truncateText($part, 500)],
                    $parts,
                );

                return [
                    'content' => $threadItems[0]['content'],
                    'platformSpecificData' => [
                        'threadItems' => $threadItems,
                    ],
                ];
            }
        }

        return ['content' => $fullCaption, 'platformSpecificData' => []];
    }

    /**
     * @return array{content: string, platformSpecificData: array<string, mixed>}
     */
    private function adaptPinterestContent(string $fullCaption, FunnelPromotionPost $post): array
    {
        $payload = $this->formatPayload($post);
        $pinTitle = trim((string) ($payload['pin_title'] ?? $post->title ?? $post->topic ?? ''));
        $pinDescription = trim((string) ($payload['pin_description'] ?? $fullCaption));
        $link = is_string($post->cta_url) && $post->cta_url !== '' ? $post->cta_url : null;

        if ($pinTitle === '') {
            $pinTitle = $this->truncateText($this->shortTitleSource($post, $fullCaption), 100);
        }

        return [
            'content' => $pinDescription !== '' ? $pinDescription : $fullCaption,
            'platformSpecificData' => array_filter([
                'title' => $pinTitle,
                'description' => $pinDescription !== '' ? $pinDescription : null,
                'link' => $link,
                'board' => is_string($payload['board_suggestion'] ?? null) ? $payload['board_suggestion'] : null,
            ], fn ($v) => $v !== null && $v !== ''),
        ];
    }

    /**
     * @return array{content: string, platformSpecificData: array<string, mixed>}
     */
    private function adaptTwitterContent(string $fullCaption, FunnelPromotionPost $post): array
    {
        $threadAdaptation = $this->adaptTwitterThreadItems($fullCaption, $post);
        if ($threadAdaptation !== null) {
            return $threadAdaptation;
        }

        $limit = $this->twitterTextLimiter->limit();

        return [
            'content' => $this->twitterTextLimiter->clamp($fullCaption, $limit),
            'platformSpecificData' => [],
        ];
    }

    /**
     * Zernio expects platformSpecificData.threadItems — not threadParts.
     * When threadItems is set, only those tweets are published (top-level content is display-only).
     *
     * @return array{content: string, platformSpecificData: array<string, mixed>}|null
     */
    private function adaptTwitterThreadItems(string $fullCaption, FunnelPromotionPost $post): ?array
    {
        $payload = $this->formatPayload($post);
        if (($payload['generator'] ?? '') !== 'thread') {
            return null;
        }

        $parts = $this->threadPartsFromPayload($payload, $fullCaption);
        if (count($parts) < 2) {
            return null;
        }

        $limit = $this->twitterTextLimiter->limit();
        $threadItems = [];
        foreach ($parts as $part) {
            $sanitized = $this->twitterTextLimiter->sanitizeForPublish($part);
            if ($sanitized === '') {
                continue;
            }

            $threadItems[] = [
                'content' => $this->twitterTextLimiter->clamp($sanitized, $limit),
            ];
        }

        if (count($threadItems) < 2) {
            return null;
        }

        $linkReply = trim((string) ($payload['link_reply'] ?? ''));
        if ($linkReply !== '') {
            $sanitizedLink = $this->twitterTextLimiter->sanitizeForPublish($linkReply);
            if ($sanitizedLink !== '') {
                $threadItems[] = [
                    'content' => $this->twitterTextLimiter->clamp($sanitizedLink, $limit),
                ];
            }
        }

        return [
            'content' => $threadItems[0]['content'],
            'platformSpecificData' => [
                'threadItems' => $threadItems,
            ],
        ];
    }

    /**
     * @return list<string>
     */
    private function threadPartsFromPayload(array $payload, string $fullCaption): array
    {
        $parts = [];
        foreach (is_array($payload['thread_parts'] ?? null) ? $payload['thread_parts'] : [] as $part) {
            if (! is_string($part)) {
                continue;
            }
            $trimmed = trim($part);
            if ($trimmed !== '') {
                $parts[] = $trimmed;
            }
        }

        if ($parts !== []) {
            return $parts;
        }

        $split = array_values(array_filter(array_map(
            'trim',
            preg_split('/\R\s*---\s*\R/u', $fullCaption) ?: [],
        )));

        return $split;
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
     * @return array<string, mixed>
     */
    private function formatPayload(FunnelPromotionPost $post): array
    {
        $payload = $post->metadata['format_payload'] ?? null;

        return is_array($payload) ? $payload : [];
    }

    /**
     * @return array{content: string, platformSpecificData: array<string, mixed>}
     */
    private function adaptRedditContent(string $fullCaption, FunnelPromotionPost $post): array
    {
        $payload = $this->formatPayload($post);
        $titleLimit = (int) config('promotion.platform_content_limits.reddit_title', 300);
        $bodyLimit = (int) config('promotion.platform_content_limits.reddit_body', 4000);

        if (in_array($payload['generator'] ?? '', ['reddit_discussion', 'reddit_ama'], true)) {
            $title = trim((string) ($payload['title'] ?? $post->topic ?? ''));
            $body = trim((string) ($payload['body'] ?? $fullCaption));

            return [
                'content' => $this->truncateText($title !== '' ? $title : $this->shortTitleSource($post, $fullCaption), $titleLimit),
                'platformSpecificData' => [
                    'description' => $this->truncateText($body, $bodyLimit),
                ],
            ];
        }

        $shortTitle = $this->truncateText($this->shortTitleSource($post, $fullCaption), $titleLimit);

        return [
            'content' => $shortTitle,
            'platformSpecificData' => [
                'description' => $this->truncateText($fullCaption, $bodyLimit),
            ],
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
        $payload = $this->formatPayload($post);
        if (($payload['generator'] ?? null) === 'carousel') {
            $urls = [];
            foreach ($payload['slides'] ?? [] as $slide) {
                if (! is_array($slide)) {
                    continue;
                }
                $url = $slide['image_url'] ?? null;
                if (is_string($url) && $url !== '') {
                    $urls[] = $url;
                }
            }
            if ($urls !== []) {
                return $urls;
            }
        }

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

    /**
     * Publish X threads as chained replies (replyToTweetId) — one confirmed tweet at a time.
     * Batch threadItems can return optimistic threadPostIds before X finishes the chain.
     *
     * @param  list<array{content: string}>  $threadItems
     * @param  list<string>  $mediaUrls
     * @return array<string, mixed>
     */
    private function publishTwitterThreadChain(
        FunnelPromotionPost $post,
        SocialAccount $account,
        array $threadItems,
        array $mediaUrls,
        ?string $mediaType,
    ): array {
        $delayMs = max(0, (int) config('promotion.zernio.twitter_thread_delay_ms', 3000));
        $threadPostIds = [];
        $lastExternalId = null;
        $rootRow = null;
        $lastZernioPostId = null;
        $failures = [];
        $expected = count($threadItems);

        Log::info('[Promotion] publishing X thread via chain', [
            'post_id' => $post->id,
            'parts' => $expected,
            'delay_ms' => $delayMs,
        ]);

        foreach ($threadItems as $index => $item) {
            $content = is_array($item) ? trim((string) ($item['content'] ?? '')) : '';
            if ($content === '') {
                continue;
            }

            $target = [
                'platform' => 'twitter',
                'accountId' => (string) $account->zernio_account_id,
            ];

            if ($lastExternalId !== null) {
                $target['platformSpecificData'] = ['replyToTweetId' => $lastExternalId];
            }

            $result = $this->zernioClient->createPost(
                content: $content,
                platforms: [$target],
                mediaUrls: $index === 0 ? $mediaUrls : [],
                mediaType: $mediaType,
                linkUrl: null,
                publishNow: true,
            );

            if (! ($result['success'] ?? false)) {
                $failures[] = [
                    'platform' => 'twitter',
                    'error' => 'Thread part '.($index + 1).' failed: '.((string) ($result['error'] ?? 'Publish failed')),
                ];
                break;
            }

            if (is_string($result['zernio_post_id'] ?? null) && $result['zernio_post_id'] !== '') {
                $lastZernioPostId = $result['zernio_post_id'];
            }

            $row = collect($result['published'] ?? [])->firstWhere('platform', 'twitter');
            if (! is_array($row)) {
                $row = is_array($result['published'][0] ?? null) ? $result['published'][0] : null;
            }

            $externalId = is_array($row) && is_string($row['external_id'] ?? null) ? $row['external_id'] : null;
            if ($externalId === null || $externalId === '') {
                $failures[] = [
                    'platform' => 'twitter',
                    'error' => 'Thread part '.($index + 1).' did not return a tweet ID from X.',
                ];
                break;
            }

            $threadPostIds[] = $externalId;
            $lastExternalId = $externalId;

            if ($index === 0) {
                $rootRow = $row;
            }

            Log::info('[Promotion] X thread chain part published', [
                'post_id' => $post->id,
                'part' => $index + 1,
                'total' => $expected,
                'external_id' => $externalId,
            ]);

            if ($index < $expected - 1 && $delayMs > 0) {
                usleep($delayMs * 1000);
            }
        }

        $publishedCount = count($threadPostIds);
        $complete = $publishedCount >= $expected && $failures === [];

        Log::info('[Promotion] twitter thread chain complete', [
            'post_id' => $post->id,
            'expected' => $expected,
            'published' => $publishedCount,
            'thread_post_ids' => $threadPostIds,
            'complete' => $complete,
        ]);

        if ($publishedCount === 0) {
            return [
                'success' => false,
                'published' => [],
                'failures' => $failures !== [] ? $failures : [['platform' => 'twitter', 'error' => 'X thread publish failed']],
                'zernio_post_id' => $lastZernioPostId,
                'thread_post_ids' => [],
                'thread_parts_published' => 0,
                'thread_parts_total' => $expected,
            ];
        }

        if (! $complete && $failures === []) {
            $failures[] = [
                'platform' => 'twitter',
                'error' => "X thread incomplete: {$publishedCount}/{$expected} tweets published",
            ];
        }

        $published = [[
            'platform' => 'twitter',
            'external_id' => $threadPostIds[0],
            'url' => is_array($rootRow) && is_string($rootRow['url'] ?? null) ? $rootRow['url'] : null,
        ]];

        return [
            'success' => true,
            'partial' => ! $complete,
            'published' => $published,
            'failures' => $failures,
            'zernio_post_id' => $lastZernioPostId,
            'thread_post_ids' => $threadPostIds,
            'thread_parts_published' => $publishedCount,
            'thread_parts_total' => $expected,
        ];
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function verifyTwitterThreadResult(array $result, int $expectedParts, int $postId): array
    {
        $zernioPostId = (string) ($result['zernio_post_id'] ?? '');
        if ($zernioPostId === '') {
            return $result;
        }

        $verification = $this->zernioClient->waitForTwitterThreadCompletion($zernioPostId, $expectedParts);
        $publishedCount = (int) ($verification['count'] ?? 0);
        $threadPostIds = is_array($verification['thread_post_ids'] ?? null) ? $verification['thread_post_ids'] : [];

        Log::info('[Promotion] twitter thread verification', [
            'post_id' => $postId,
            'expected' => $expectedParts,
            'published' => $publishedCount,
            'thread_post_ids' => $threadPostIds,
            'complete' => ($verification['complete'] ?? false) === true,
        ]);

        $result['thread_parts_published'] = $publishedCount;
        $result['thread_parts_total'] = $expectedParts;
        $result['thread_post_ids'] = $threadPostIds;

        if (($verification['complete'] ?? false) === true) {
            return $result;
        }

        $error = $publishedCount > 0
            ? "X thread incomplete: {$publishedCount}/{$expectedParts} tweets confirmed"
            : 'X thread did not confirm any tweet IDs';

        return [
            'success' => $publishedCount > 0,
            'partial' => $publishedCount > 0 && $publishedCount < $expectedParts,
            'published' => $result['published'] ?? [],
            'failures' => [['platform' => 'twitter', 'error' => $error]],
            'zernio_post_id' => $zernioPostId,
            'thread_parts_published' => $publishedCount,
            'thread_parts_total' => $expectedParts,
            'thread_post_ids' => $threadPostIds,
        ];
    }
}
