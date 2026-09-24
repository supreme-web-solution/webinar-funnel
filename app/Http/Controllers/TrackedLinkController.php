<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\TrackedLink;
use App\Models\TrackedLinkClick;
use App\Services\Campaigns\TrackedLinkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TrackedLinkController extends Controller
{
    public function index(): Response
    {
        $userId = auth()->id();
        $linkIds = TrackedLink::query()
            ->where('user_id', $userId)
            ->whereNull('campaign_id')
            ->pluck('id');

        $clicksLast7Days = TrackedLinkClick::query()
            ->whereIn('tracked_link_id', $linkIds)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        $links = TrackedLink::query()
            ->where('user_id', $userId)
            ->whereNull('campaign_id')
            ->latest()
            ->get()
            ->map(fn (TrackedLink $l) => [
                'id' => $l->id,
                'code' => $l->code,
                'label' => $l->label,
                'destination_url' => $l->destination_url,
                'click_count' => $l->click_count,
                'public_url' => $l->publicUrl(),
                'is_active' => $l->is_active,
                'geo_rules' => $l->geo_rules,
                'device_rules' => $l->device_rules,
                'created_at' => $l->created_at,
            ]);

        return Inertia::render('tracked-links/Index', [
            'links' => $links,
            'clicks_last_7_days' => $clicksLast7Days,
        ]);
    }

    public function store(Request $request, TrackedLinkService $service): RedirectResponse
    {
        $validated = $request->validate([
            'destination_url' => ['required', 'url', 'max:2048'],
            'label' => ['nullable', 'string', 'max:160'],
            'campaign_id' => ['nullable', 'integer', 'exists:campaigns,id'],
            'geo_rules' => ['nullable', 'array'],
            'device_rules' => ['nullable', 'array'],
        ]);

        if (! empty($validated['campaign_id'])) {
            abort_unless(
                Campaign::query()
                    ->where('id', $validated['campaign_id'])
                    ->where('user_id', auth()->id())
                    ->exists(),
                403
            );
        }

        $service->createForUser(
            (int) auth()->id(),
            $validated['destination_url'],
            $validated['label'] ?? null,
            $validated['geo_rules'] ?? null,
            $validated['device_rules'] ?? null,
            $validated['campaign_id'] ?? null,
        );

        return back()->with('success', 'Tracked link created.');
    }

    public function update(Request $request, TrackedLink $trackedLink): RedirectResponse
    {
        abort_unless((int) $trackedLink->user_id === (int) auth()->id(), 403);

        $validated = $request->validate([
            'destination_url' => ['sometimes', 'url', 'max:2048'],
            'label' => ['nullable', 'string', 'max:160'],
            'geo_rules' => ['nullable', 'array'],
            'device_rules' => ['nullable', 'array'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $trackedLink->update($validated);

        return back();
    }

    public function show(TrackedLink $trackedLink): JsonResponse
    {
        abort_unless((int) $trackedLink->user_id === (int) auth()->id(), 403);

        $recentClicks = $trackedLink->clicks()
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn ($click) => [
                'country' => $click->country,
                'device' => $click->device,
                'referrer' => $click->referrer,
                'created_at' => $click->created_at?->toIso8601String(),
            ]);

        $byCountry = $trackedLink->clicks()
            ->selectRaw('country, count(*) as total')
            ->whereNotNull('country')
            ->groupBy('country')
            ->orderByDesc('total')
            ->limit(10)
            ->pluck('total', 'country');

        $byDevice = $trackedLink->clicks()
            ->selectRaw('device, count(*) as total')
            ->groupBy('device')
            ->orderByDesc('total')
            ->pluck('total', 'device');

        $clicksByDay = [];
        for ($i = 6; $i >= 0; $i--) {
            $start = now()->subDays($i)->startOfDay();
            $end = $start->copy()->endOfDay();

            $clicksByDay[] = [
                'label' => $start->format('D'),
                'date' => $start->toDateString(),
                'clicks' => $trackedLink->clicks()
                    ->whereBetween('created_at', [$start, $end])
                    ->count(),
            ];
        }

        $clicksLast7Days = array_sum(array_column($clicksByDay, 'clicks'));

        $topReferrers = $trackedLink->clicks()
            ->selectRaw('referrer, count(*) as total')
            ->whereNotNull('referrer')
            ->where('referrer', '!=', '')
            ->groupBy('referrer')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->map(fn ($row) => [
                'referrer' => $row->referrer,
                'total' => (int) $row->total,
            ]);

        return response()->json([
            'link' => [
                'id' => $trackedLink->id,
                'label' => $trackedLink->label,
                'code' => $trackedLink->code,
                'click_count' => $trackedLink->click_count,
                'public_url' => $trackedLink->publicUrl(),
                'is_active' => $trackedLink->is_active,
                'geo_rules' => $trackedLink->geo_rules,
                'device_rules' => $trackedLink->device_rules,
            ],
            'summary' => [
                'clicks_last_7_days' => $clicksLast7Days,
                'unique_countries' => $trackedLink->clicks()
                    ->whereNotNull('country')
                    ->distinct('country')
                    ->count('country'),
            ],
            'clicks_by_day' => $clicksByDay,
            'recent_clicks' => $recentClicks,
            'by_country' => $byCountry,
            'by_device' => $byDevice,
            'top_referrers' => $topReferrers,
        ]);
    }

    public function destroy(TrackedLink $trackedLink): RedirectResponse
    {
        abort_unless((int) $trackedLink->user_id === (int) auth()->id(), 403);
        abort_unless($trackedLink->campaign_id === null, 403, 'Campaign links are managed from the campaign Publish step.');

        $trackedLink->delete();

        return back()->with('success', 'Tracked link deleted.');
    }

    public function redirect(Request $request, string $code, TrackedLinkService $service): RedirectResponse
    {
        $link = TrackedLink::query()->where('code', $code)->where('is_active', true)->firstOrFail();
        $url = $service->resolveRedirect($link, $request);

        return redirect()->away($url);
    }
}
