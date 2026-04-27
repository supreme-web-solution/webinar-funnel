<?php

namespace App\Jobs;

use App\Mail\KeywordMentionAlert;
use App\Models\Keyword;
use App\Models\KeywordFetchState;
use App\Models\Mention;
use App\Services\YouTubeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class FetchYouTubeMentions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 360;
    public int $tries = 2;

    public function __construct(
        protected Keyword $keyword,
        protected ?Carbon $fromDate = null,
        protected ?Carbon $toDate = null,
    ) {}

    public function handle(YouTubeService $youTubeService): void
    {
        $this->keyword->refresh();

        if (! $this->keyword->is_active) {
            return;
        }

        if (! config('services.apify.enabled', true)) {
            return;
        }

        if ($this->isRecentlyFetched()) {
            return;
        }

        Log::info('FetchYouTubeMentions: Starting fetch', [
            'keyword_id' => $this->keyword->id,
            'keyword_name' => $this->keyword->name,
        ]);

        $videos = $youTubeService->searchVideos($this->keyword->name);

        if (empty($videos)) {
            KeywordFetchState::recordFetch($this->keyword->id, 'youtube');
            return;
        }

        $savedMentions = [];

        foreach ($videos as $video) {
            $videoId = $video['id']['videoId'] ?? null;
            $channelId = $video['id']['channelId'] ?? null;
            $postId = $videoId ?? $channelId;

            if (! $postId) {
                continue;
            }

            $snippet = $video['snippet'] ?? [];
            $stats = $video['statistics'] ?? [];

            $publishedAt = $snippet['publishedAt'] ?? null;
            $postedAt = $publishedAt ? Carbon::parse($publishedAt) : null;

            if ($this->outsideDateRange($postedAt)) {
                continue;
            }

            $exists = Mention::where('post_id', $postId)
                ->where('source_type', 'YouTube')
                ->where('keyword_id', $this->keyword->id)
                ->exists();

            if ($exists) {
                continue;
            }

            $url = $videoId
                ? "https://www.youtube.com/watch?v={$videoId}"
                : "https://www.youtube.com/channel/{$channelId}";

            try {
                $mention = Mention::create([
                    'keyword_id'     => $this->keyword->id,
                    'user_id'        => $this->keyword->user_id,
                    'post_id'        => $postId,
                    'title'          => Str::limit($snippet['title'] ?? 'Untitled', 500, ''),
                    'content'        => Str::limit($snippet['description'] ?? '', 2000, ''),
                    'source'         => $snippet['channelTitle'] ?? 'YouTube',
                    'source_type'    => 'YouTube',
                    'username'       => $snippet['channelTitle'] ?? null,
                    'like_count'     => (int) ($stats['likeCount'] ?? 0),
                    'favourite_count' => (int) ($stats['favoriteCount'] ?? 0),
                    'views'          => (int) ($stats['viewCount'] ?? 0),
                    'comments_count' => (int) ($stats['commentCount'] ?? 0),
                    'permalink'      => $url,
                    'posted_at'      => $postedAt,
                ]);

                $savedMentions[] = $mention;
            } catch (\Exception $e) {
                Log::error('FetchYouTubeMentions: Failed to save', [
                    'keyword_id' => $this->keyword->id,
                    'post_id' => $postId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        KeywordFetchState::recordFetch($this->keyword->id, 'youtube');

        Log::info('FetchYouTubeMentions: Done', [
            'keyword_id' => $this->keyword->id,
            'saved' => count($savedMentions),
        ]);

        $this->maybeSendEmail($savedMentions, 'youtube');
    }

    protected function isRecentlyFetched(): bool
    {
        $state = KeywordFetchState::where('keyword_id', $this->keyword->id)
            ->where('platform', 'youtube')
            ->whereNotNull('last_fetch_at')
            ->first();

        if (! $state) {
            return false;
        }

        $interval = config('limits.fetch.platform_intervals.youtube', 30);

        return $state->last_fetch_at->copy()->addMinutes($interval)->isFuture();
    }

    protected function outsideDateRange(?Carbon $date): bool
    {
        if (! $this->fromDate || ! $this->toDate || ! $date) {
            return false;
        }

        return $date->isBefore($this->fromDate) || $date->isAfter($this->toDate);
    }

    protected function maybeSendEmail(array $savedMentions, string $platform): void
    {
        if (! $this->keyword->email_notifications || empty($savedMentions)) {
            return;
        }

        $user = $this->keyword->user;

        if (! $user || ! $user->email) {
            return;
        }

        try {
            Mail::to($user->email)->send(
                new KeywordMentionAlert($this->keyword, collect($savedMentions), $platform)
            );
        } catch (\Exception $e) {
            Log::error('FetchYouTubeMentions: Email failed', ['error' => $e->getMessage()]);
        }
    }
}
