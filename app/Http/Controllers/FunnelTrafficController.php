<?php

namespace App\Http\Controllers;

use App\Jobs\FetchNewsMentions;
use App\Jobs\FetchRedditMentions;
use App\Jobs\FetchTwitterMentions;
use App\Jobs\FetchYouTubeMentions;
use App\Models\Funnel;
use App\Models\Keyword;
use App\Models\Mention;
use App\Services\Mentions\KeywordMentionCapEnforcer;
use App\Services\TrafficAi\TrafficReplyGenerator;
use App\Support\TrafficAiLogger;
use App\Support\TrafficAiPlatform;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FunnelTrafficController extends Controller
{
    public function storeKeyword(Request $request, Funnel $funnel): RedirectResponse
    {
        $this->authorizeFunnel($funnel);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'platforms' => ['sometimes', 'array'],
            'platforms.*' => ['in:reddit,youtube,twitter,news'],
        ]);

        $user = $request->user();
        $max = KeywordMentionCapEnforcer::maxKeywordsPerFunnel();

        if ($funnel->keywords()->count() >= $max) {
            return back()->withErrors(['name' => "This funnel can track up to {$max} keywords. Delete one to add another."]);
        }

        $keyword = Keyword::firstOrCreate(
            [
                'user_id' => $user->id,
                'funnel_id' => $funnel->id,
                'name' => trim($validated['name']),
            ],
            [
                'is_active' => true,
                'email_notifications' => false,
                'platforms' => $validated['platforms'] ?? ['reddit', 'youtube', 'twitter', 'news'],
            ]
        );

        if ($keyword->wasRecentlyCreated) {
            $this->dispatchAllPlatformJobs($keyword);
        }

        return back()->with('success', "Traffic keyword \"{$keyword->name}\" added.");
    }

    public function updateKeyword(Request $request, Funnel $funnel, Keyword $keyword): RedirectResponse
    {
        $this->authorizeKeyword($request, $funnel, $keyword);

        $validated = $request->validate([
            'is_active' => ['sometimes', 'boolean'],
            'email_notifications' => ['sometimes', 'boolean'],
            'platforms' => ['sometimes', 'array'],
            'platforms.*' => ['in:reddit,youtube,twitter,news'],
        ]);

        if (
            array_key_exists('is_active', $validated)
            && $validated['is_active']
            && app(KeywordMentionCapEnforcer::class)->hasReachedCap($keyword)
        ) {
            $cap = KeywordMentionCapEnforcer::maxMentionsPerKeyword();

            return back()->withErrors([
                'is_active' => "This keyword has reached the maximum of {$cap} saved mentions. Delete some mentions or remove the keyword and add it again.",
            ]);
        }

        $keyword->update($validated);

        return back()->with('success', 'Traffic keyword updated.');
    }

    public function destroyKeyword(Request $request, Funnel $funnel, Keyword $keyword): RedirectResponse
    {
        $this->authorizeKeyword($request, $funnel, $keyword);
        $name = $keyword->name;
        $keyword->delete();

        return back()->with('success', "Traffic keyword \"{$name}\" deleted.");
    }

    public function draftMentionReply(
        Request $request,
        Funnel $funnel,
        Mention $mention,
        TrafficReplyGenerator $generator,
    ): JsonResponse {
        $this->authorizeMention($request, $funnel, $mention);

        $mention->loadMissing('keyword.funnel.settings');
        $settings = $mention->keyword?->funnel?->settings;

        if (! $settings) {
            return response()->json([
                'message' => 'Funnel settings are missing for this mention.',
            ], 422);
        }

        $platform = TrafficAiPlatform::fromMentionSource($mention->source_type);

        if ($platform === null || $platform === 'news') {
            return response()->json([
                'message' => 'Manual reply drafts are not supported for news mentions.',
            ], 422);
        }

        $link = $settings->effectiveTrafficAffiliateLink();

        if ($link === null || $link === '') {
            return response()->json([
                'message' => 'Add an affiliate link or Traffic AI link override in funnel settings first.',
            ], 422);
        }

        $generated = $generator->generateWithMeta($mention, $settings, $link, $platform);

        if ($generated['text'] === '') {
            return response()->json([
                'message' => 'Could not generate a reply. Check your OpenAI key or try again.',
            ], 422);
        }

        TrafficAiLogger::info('manual reply drafted from funnel UI', [
            'mention_id' => $mention->id,
            'funnel_id' => $funnel->id,
            'platform' => $platform,
            'reply_length' => strlen($generated['text']),
            'source' => $generated['source'],
        ]);

        return response()->json([
            'reply' => $generated['text'],
            'source' => $generated['source'],
            'warning' => $generated['warning'],
            'permalink' => $mention->permalink,
            'platform' => $platform,
            'mention' => [
                'id' => $mention->id,
                'title' => $mention->title,
                'source_type' => $mention->source_type,
            ],
        ]);
    }

    public function fetchNow(Request $request, Funnel $funnel, Keyword $keyword): RedirectResponse
    {
        $this->authorizeKeyword($request, $funnel, $keyword);

        if (app(KeywordMentionCapEnforcer::class)->hasReachedCap($keyword)) {
            app(KeywordMentionCapEnforcer::class)->enforceCap($keyword);

            $cap = KeywordMentionCapEnforcer::maxMentionsPerKeyword();

            return back()->withErrors([
                'fetch' => "This keyword has reached the maximum of {$cap} saved mentions and was paused.",
            ]);
        }

        $this->dispatchAllPlatformJobs($keyword);

        return back()->with('success', "Fetch started for \"{$keyword->name}\".");
    }

    private function dispatchAllPlatformJobs(Keyword $keyword): void
    {
        $platforms = $keyword->platforms ?? ['reddit', 'youtube', 'twitter', 'news'];

        foreach ($platforms as $platform) {
            match ($platform) {
                'reddit' => FetchRedditMentions::dispatch($keyword),
                'youtube' => FetchYouTubeMentions::dispatch($keyword),
                'twitter' => FetchTwitterMentions::dispatch($keyword),
                'news' => FetchNewsMentions::dispatch($keyword),
                default => null,
            };
        }
    }

    private function authorizeFunnel(Funnel $funnel): void
    {
        abort_unless((int) auth()->id() === (int) $funnel->user_id, 403);
    }

    private function authorizeKeyword(Request $request, Funnel $funnel, Keyword $keyword): void
    {
        abort_unless(
            $keyword->user_id === $request->user()->id
                && (int) $keyword->funnel_id === (int) $funnel->id,
            403
        );
    }

    private function authorizeMention(Request $request, Funnel $funnel, Mention $mention): void
    {
        $this->authorizeFunnel($funnel);

        $mention->loadMissing('keyword');

        abort_unless(
            (int) $mention->user_id === (int) $request->user()->id
                && $mention->keyword
                && (int) $mention->keyword->funnel_id === (int) $funnel->id,
            403
        );
    }
}
