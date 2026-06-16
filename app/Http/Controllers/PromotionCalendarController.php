<?php

namespace App\Http\Controllers;

use App\Models\FunnelPromotionPost;
use App\Models\FunnelPromotionScheduleEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PromotionCalendarController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $month = max(1, min(12, (int) $request->query('month', now()->month)));
        $year  = max(2020, min(2035, (int) $request->query('year', now()->year)));

        $start = \Carbon\Carbon::createFromDate($year, $month, 1)->startOfDay();
        $end   = $start->copy()->endOfMonth()->endOfDay();

        // All scheduled/published/ready/draft posts across every funnel the user owns,
        // within the requested month (or unscheduled).
        $events = FunnelPromotionPost::query()
            ->where('user_id', $user->id)
            ->with('funnel:id,name')
            ->whereIn('status', [
                FunnelPromotionPost::STATUS_SCHEDULED,
                FunnelPromotionPost::STATUS_PUBLISHED,
                FunnelPromotionPost::STATUS_READY,
                FunnelPromotionPost::STATUS_DRAFT,
                FunnelPromotionPost::STATUS_GENERATING,
            ])
            ->where(function ($q) use ($start, $end): void {
                $q->whereBetween('scheduled_for', [$start, $end])
                    ->orWhereNull('scheduled_for');
            })
            ->orderBy('scheduled_for')
            ->get([
                'id',
                'funnel_id',
                'title',
                'topic',
                'content_type',
                'platforms',
                'status',
                'scheduled_for',
                'published_at',
                'cta_url',
                'cta_label',
                'text_body',
            ])
            ->map(function (FunnelPromotionPost $post) {
                return [
                    'id'            => $post->id,
                    'funnel_id'     => $post->funnel_id,
                    'funnel_name'   => $post->funnel?->name ?? 'Unknown funnel',
                    'title'         => $post->title,
                    'topic'         => $post->topic,
                    'content_type'  => $post->content_type,
                    'platforms'     => $post->platforms ?? [],
                    'status'        => $post->status,
                    'scheduled_for' => $post->scheduled_for?->toIso8601String(),
                    'published_at'  => $post->published_at?->toIso8601String(),
                    'cta_url'       => $post->cta_url,
                    'cta_label'     => $post->cta_label,
                    'text_body'     => $post->text_body ? \Illuminate\Support\Str::limit($post->text_body, 200) : null,
                ];
            });

        return Inertia::render('promotion/Calendar', [
            'events'       => $events,
            'currentMonth' => $month,
            'currentYear'  => $year,
            'routes'       => [
                'move' => route('promotion.calendar.move', '__POST__'),
            ],
        ]);
    }

    public function move(Request $request, FunnelPromotionPost $post): RedirectResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        abort_unless((int) $user->id === (int) $post->user_id, 403);

        $validated = $request->validate([
            'scheduled_for' => ['required', 'date', 'after:now'],
            'timezone'      => ['nullable', 'string', 'max:64'],
        ]);

        $from = $post->scheduled_for;

        $post->update([
            'scheduled_for' => $validated['scheduled_for'],
            'timezone'      => $validated['timezone'] ?? (string) config('promotion.default_timezone', 'UTC'),
            'status'        => FunnelPromotionPost::STATUS_SCHEDULED,
        ]);

        FunnelPromotionScheduleEvent::query()->create([
            'post_id'  => $post->id,
            'actor_id' => $user->id,
            'from_time'=> $from,
            'to_time'  => $post->scheduled_for,
            'action'   => FunnelPromotionScheduleEvent::ACTION_RESCHEDULED,
            'meta'     => ['source' => 'global_calendar'],
        ]);

        return back()->with('success', 'Post rescheduled.');
    }
}
