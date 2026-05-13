<?php

namespace App\Http\Controllers;

use App\Jobs\FetchNewsMentions;
use App\Jobs\FetchRedditMentions;
use App\Jobs\FetchTwitterMentions;
use App\Jobs\FetchYouTubeMentions;
use App\Models\Funnel;
use App\Models\Keyword;
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
        $max = (int) config('limits.mentions.max_keywords_per_user', 20);

        if ($funnel->keywords()->count() >= $max) {
            return back()->withErrors(['name' => "This funnel can track up to {$max} keywords."]);
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

    public function fetchNow(Request $request, Funnel $funnel, Keyword $keyword): RedirectResponse
    {
        $this->authorizeKeyword($request, $funnel, $keyword);
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
}

