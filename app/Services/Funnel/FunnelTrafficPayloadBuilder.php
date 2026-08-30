<?php

namespace App\Services\Funnel;

use App\Models\Funnel;
use App\Models\Keyword;
use App\Models\Mention;
use App\Models\SocialAccount;
use App\Services\Mentions\KeywordMentionCapEnforcer;
use Illuminate\Http\Request;

class FunnelTrafficPayloadBuilder
{
    public function mentionCountForFunnel(Funnel $funnel): int
    {
        return Mention::query()
            ->where('user_id', $funnel->user_id)
            ->whereHas('keyword', fn ($q) => $q->where('funnel_id', $funnel->id))
            ->count();
    }

    /**
     * @return array<string, mixed>
     */
    public function buildTrafficData(Request $request, Funnel $funnel): array
    {
        $mentionCap = KeywordMentionCapEnforcer::maxMentionsPerKeyword();
        $capEnforcer = app(KeywordMentionCapEnforcer::class);

        $mentionCountsByKeywordPlatform = Mention::query()
            ->where('user_id', $funnel->user_id)
            ->whereHas('keyword', fn ($q) => $q->where('funnel_id', $funnel->id))
            ->selectRaw('keyword_id, LOWER(source_type) as platform_key, count(*) as cnt')
            ->groupBy('keyword_id', 'platform_key')
            ->get()
            ->groupBy('keyword_id')
            ->map(fn ($rows) => $rows->pluck('cnt', 'platform_key')->all());

        $keywords = Keyword::query()
            ->where('user_id', $funnel->user_id)
            ->where('funnel_id', $funnel->id)
            ->withCount('mentions')
            ->orderByDesc('created_at')
            ->get()
            ->map(function (Keyword $keyword) use ($mentionCap, $capEnforcer, $mentionCountsByKeywordPlatform) {
                $mentionsCount = (int) $keyword->mentions_count;
                $capReached = $mentionsCount >= $mentionCap;

                if ($capReached) {
                    $capEnforcer->enforceCap($keyword);
                }

                return [
                    'id' => $keyword->id,
                    'name' => $keyword->name,
                    'is_active' => $capReached ? false : (bool) $keyword->is_active,
                    'email_notifications' => (bool) $keyword->email_notifications,
                    'platforms' => $keyword->platforms ?? [],
                    'mentions_count' => $mentionsCount,
                    'mention_cap_reached' => $capReached,
                    'mention_counts_by_platform' => $mentionCountsByKeywordPlatform->get($keyword->id, []),
                ];
            })
            ->values();

        $mentionsQuery = Mention::query()
            ->where('user_id', $funnel->user_id)
            ->whereHas('keyword', fn ($q) => $q->where('funnel_id', $funnel->id))
            ->with([
                'keyword:id,name,funnel_id',
                'trafficReplyAttempt:id,mention_id,status,skip_reason,last_error,posted_at,external_comment_id',
            ]);

        $trafficSearch = trim((string) $request->query('traffic_search', ''));
        $trafficPlatform = (string) $request->query('traffic_platform', '');
        $trafficKeywordId = $request->query('traffic_keyword_id');

        if ($trafficPlatform !== '') {
            $mentionsQuery->whereRaw('LOWER(source_type) = ?', [strtolower($trafficPlatform)]);
        }

        if ($trafficKeywordId) {
            $mentionsQuery->where('keyword_id', $trafficKeywordId);
        }

        if ($trafficSearch !== '') {
            $mentionsQuery->where(function ($q) use ($trafficSearch): void {
                $q->where('title', 'like', "%{$trafficSearch}%")
                    ->orWhere('content', 'like', "%{$trafficSearch}%")
                    ->orWhere('username', 'like', "%{$trafficSearch}%");
            });
        }

        $mentions = $mentionsQuery->orderByDesc('posted_at')->paginate(10)->withQueryString();

        $statsQuery = Mention::query()
            ->where('user_id', $funnel->user_id)
            ->whereHas('keyword', fn ($q) => $q->where('funnel_id', $funnel->id));

        if ($trafficKeywordId) {
            $statsQuery->where('keyword_id', $trafficKeywordId);
        }

        $platformCounts = (clone $statsQuery)
            ->selectRaw('LOWER(source_type) as platform_key, count(*) as cnt')
            ->groupBy('platform_key')
            ->pluck('cnt', 'platform_key');

        $funnel->loadMissing('template');
        $meta = is_array($funnel->meta) ? $funnel->meta : [];

        $funnel->loadMissing('campaign');
        if ($funnel->campaign) {
            $suggestedKeywords = app(\App\Services\Campaigns\CampaignKnowledgeContextService::class)
                ->trafficKeywordSuggestions($funnel->campaign);
        } else {
            $suggestedKeywords = $meta['suggested_keywords'] ?? $funnel->template?->suggested_keywords ?? [];
        }
        $trackedNames = $keywords
            ->pluck('name')
            ->map(fn (string $name): string => mb_strtolower(trim($name)))
            ->all();

        $availableSuggestedKeywords = collect(is_array($suggestedKeywords) ? $suggestedKeywords : [])
            ->map(fn ($keyword): string => trim((string) $keyword))
            ->filter(fn (string $keyword): bool => $keyword !== '')
            ->unique()
            ->reject(fn (string $keyword): bool => in_array(mb_strtolower($keyword), $trackedNames, true))
            ->values()
            ->all();

        return [
            'keywords' => $keywords,
            'suggested_keywords' => $availableSuggestedKeywords,
            'mentions' => $mentions,
            'stats' => [
                'total' => (clone $statsQuery)->count(),
                'this_week' => (clone $statsQuery)
                    ->where('created_at', '>=', now()->startOfWeek())
                    ->count(),
                'keywords_count' => $keywords->count(),
                'platforms' => $platformCounts,
            ],
            'filters' => [
                'search' => $trafficSearch,
                'platform' => $trafficPlatform,
                'keyword_id' => $trafficKeywordId,
            ],
            'social_accounts' => SocialAccount::query()
                ->where('user_id', $funnel->user_id)
                ->orderBy('platform')
                ->get(['id', 'platform', 'platform_username', 'posts_today', 'posts_today_reset_on']),
            'max_replies_per_day_per_account' => (int) config('traffic_ai.max_replies_per_day_per_account', 20),
            'limits' => [
                'max_keywords_per_funnel' => KeywordMentionCapEnforcer::maxKeywordsPerFunnel(),
                'max_mentions_per_keyword' => $mentionCap,
            ],
        ];
    }
}
