<?php

namespace App\Http\Controllers;

use App\Jobs\FetchNewsMentions;
use App\Jobs\FetchRedditMentions;
use App\Jobs\FetchTwitterMentions;
use App\Jobs\FetchYouTubeMentions;
use App\Models\Keyword;
use App\Models\Mention;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MentionController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $keywords = Keyword::where('user_id', $user->id)
            ->withCount('mentions')
            ->orderBy('created_at', 'desc')
            ->get();

        $mentionsQuery = Mention::where('user_id', $user->id)
            ->with('keyword:id,name');

        if ($request->filled('platform')) {
            $mentionsQuery->where('source_type', $request->platform);
        }

        if ($request->filled('keyword_id')) {
            $mentionsQuery->where('keyword_id', $request->keyword_id);
        }

        if ($request->filled('search')) {
            $term = $request->search;
            $mentionsQuery->where(function ($q) use ($term) {
                $q->where('title', 'like', "%{$term}%")
                    ->orWhere('content', 'like', "%{$term}%")
                    ->orWhere('username', 'like', "%{$term}%");
            });
        }

        $mentions = $mentionsQuery
            ->orderBy('posted_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        // Platform breakdown counts
        $platformCounts = Mention::where('user_id', $user->id)
            ->selectRaw('source_type, count(*) as cnt')
            ->groupBy('source_type')
            ->pluck('cnt', 'source_type');

        $stats = [
            'total'          => Mention::where('user_id', $user->id)->count(),
            'this_week'      => Mention::where('user_id', $user->id)
                ->where('created_at', '>=', now()->startOfWeek())
                ->count(),
            'keywords_count' => $keywords->count(),
            'platforms'      => $platformCounts,
        ];

        return Inertia::render('mentions/Index', [
            'keywords' => $keywords,
            'mentions' => $mentions,
            'stats'    => $stats,
            'filters'  => $request->only(['search', 'platform', 'keyword_id']),
        ]);
    }

    public function storeKeyword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'      => ['required', 'string', 'max:100'],
            'platforms' => ['sometimes', 'array'],
            'platforms.*' => ['in:reddit,youtube,twitter,news'],
        ]);

        $user = $request->user();

        // Check per-user keyword limit
        $max = (int) config('limits.mentions.max_keywords_per_user', 20);
        if ($user->keywords()->count() >= $max) {
            return back()->withErrors(['name' => "You can track up to {$max} keywords."]);
        }

        $keyword = Keyword::firstOrCreate(
            ['user_id' => $user->id, 'name' => trim($validated['name'])],
            [
                'is_active'           => true,
                'email_notifications' => false,
                'platforms'           => $validated['platforms'] ?? ['reddit', 'youtube', 'twitter', 'news'],
            ]
        );

        if ($keyword->wasRecentlyCreated) {
            $this->dispatchAllPlatformJobs($keyword);
        }

        return back()->with('success', "Keyword \"{$keyword->name}\" added and fetch started.");
    }

    public function updateKeyword(Request $request, Keyword $keyword): RedirectResponse
    {
        abort_unless($keyword->user_id === $request->user()->id, 403);

        $validated = $request->validate([
            'is_active'           => ['sometimes', 'boolean'],
            'email_notifications' => ['sometimes', 'boolean'],
            'platforms'           => ['sometimes', 'array'],
            'platforms.*'         => ['in:reddit,youtube,twitter,news'],
        ]);

        $keyword->update($validated);

        return back()->with('success', 'Keyword settings updated.');
    }

    public function destroyKeyword(Request $request, Keyword $keyword): RedirectResponse
    {
        abort_unless($keyword->user_id === $request->user()->id, 403);

        $keyword->delete();

        return back()->with('success', "Keyword \"{$keyword->name}\" and its mentions deleted.");
    }

    public function fetchNow(Request $request, Keyword $keyword): RedirectResponse
    {
        abort_unless($keyword->user_id === $request->user()->id, 403);

        $this->dispatchAllPlatformJobs($keyword);

        return back()->with('success', "Fetch started for \"{$keyword->name}\" across all platforms.");
    }

    protected function dispatchAllPlatformJobs(Keyword $keyword): void
    {
        $platforms = $keyword->platforms ?? ['reddit', 'youtube', 'twitter', 'news'];

        foreach ($platforms as $platform) {
            match ($platform) {
                'reddit'  => FetchRedditMentions::dispatch($keyword),
                'youtube' => FetchYouTubeMentions::dispatch($keyword),
                'twitter' => FetchTwitterMentions::dispatch($keyword),
                'news'    => FetchNewsMentions::dispatch($keyword),
                default   => null,
            };
        }
    }
}
