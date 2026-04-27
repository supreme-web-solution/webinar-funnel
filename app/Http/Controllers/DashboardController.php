<?php

namespace App\Http\Controllers;

use App\Models\Funnel;
use App\Models\Lead;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $userId = auth()->id();

        $funnelCount = Funnel::query()
            ->where('user_id', $userId)
            ->count();

        $publishedCount = Funnel::query()
            ->where('user_id', $userId)
            ->where('status', 'published')
            ->count();
        $draftCount = Funnel::query()
            ->where('user_id', $userId)
            ->where('status', 'draft')
            ->count();

        $leadCount = Lead::query()
            ->whereHas('funnel', fn ($q) => $q->where('user_id', $userId))
            ->count();

        // Leads captured in the last 7 days
        $recentLeads = Lead::query()
            ->whereHas('funnel', fn ($q) => $q->where('user_id', $userId))
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        // Leads captured in the 7 days prior (for % change)
        $previousWeekLeads = Lead::query()
            ->whereHas('funnel', fn ($q) => $q->where('user_id', $userId))
            ->whereBetween('created_at', [now()->subDays(14), now()->subDays(7)])
            ->count();

        // Top funnels by lead count (up to 5)
        $topFunnels = Funnel::query()
            ->where('user_id', $userId)
            ->withCount('leads')
            ->orderByDesc('leads_count')
            ->limit(12)
            ->get(['id', 'name', 'slug', 'status']);
        $topFunnels = $topFunnels
            ->filter(fn ($funnel) => (int) $funnel->leads_count > 0)
            ->take(5)
            ->values();

        $recentFunnels = Funnel::query()
            ->where('user_id', $userId)
            ->with('template:id,name')
            ->latest()
            ->limit(8)
            ->get(['id', 'template_id', 'name', 'slug', 'status', 'created_at']);

        return Inertia::render('dashboard/Index', [
            'metrics' => [
                'funnelCount'       => $funnelCount,
                'publishedCount'    => $publishedCount,
                'draftCount'        => $draftCount,
                'leadCount'         => $leadCount,
                'recentLeads'       => $recentLeads,
                'previousWeekLeads' => $previousWeekLeads,
            ],
            'topFunnels'    => $topFunnels,
            'recentFunnels' => $recentFunnels,
        ]);
    }
}
