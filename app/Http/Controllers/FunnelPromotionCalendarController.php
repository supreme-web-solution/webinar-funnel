<?php

namespace App\Http\Controllers;

use App\Http\Requests\FunnelPromotionScheduleRequest;
use App\Models\Funnel;
use App\Models\FunnelPromotionPost;
use App\Models\FunnelPromotionScheduleEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FunnelPromotionCalendarController extends Controller
{
    public function index(Request $request, Funnel $funnel): Response
    {
        $this->authorizeFunnel($funnel);

        $month = max(1, min(12, (int) $request->query('month', now()->month)));
        $year  = max(2020, min(2035, (int) $request->query('year', now()->year)));

        $start = \Carbon\Carbon::createFromDate($year, $month, 1)->startOfDay();
        $end   = $start->copy()->endOfMonth()->endOfDay();

        $events = FunnelPromotionPost::query()
            ->where('funnel_id', $funnel->id)
            ->whereIn('status', [
                FunnelPromotionPost::STATUS_SCHEDULED,
                FunnelPromotionPost::STATUS_PUBLISHED,
                FunnelPromotionPost::STATUS_READY,
                FunnelPromotionPost::STATUS_DRAFT,
            ])
            ->where(function ($q) use ($start, $end): void {
                $q->whereBetween('scheduled_for', [$start, $end])
                    ->orWhereNull('scheduled_for');
            })
            ->orderBy('scheduled_for')
            ->get([
                'id',
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
                'email_subject',
                'last_error',
            ]);

        return Inertia::render('funnels/promotion/Calendar', [
            'funnel' => [
                'id' => $funnel->id,
                'name' => $funnel->name,
                'status' => $funnel->status,
            ],
            'events' => $events,
            'currentMonth' => $month,
            'currentYear' => $year,
            'routes' => [
                'posts' => route('funnels.promotion.posts.index', $funnel),
                'move' => route('funnels.promotion.calendar.move', [$funnel, '__POST__']),
            ],
        ]);
    }

    public function move(
        FunnelPromotionScheduleRequest $request,
        Funnel $funnel,
        FunnelPromotionPost $post
    ): RedirectResponse {
        $this->authorizePost($request, $funnel, $post);
        $validated = $request->validated();
        $from = $post->scheduled_for;

        $post->update([
            'scheduled_for' => $validated['scheduled_for'],
            'timezone' => $validated['timezone'] ?? (string) config('promotion.default_timezone', 'UTC'),
            'status' => FunnelPromotionPost::STATUS_SCHEDULED,
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        FunnelPromotionScheduleEvent::query()->create([
            'post_id' => $post->id,
            'actor_id' => $user->id,
            'from_time' => $from,
            'to_time' => $post->scheduled_for,
            'action' => FunnelPromotionScheduleEvent::ACTION_RESCHEDULED,
            'meta' => ['source' => 'calendar_drag_drop'],
        ]);

        return back()->with('success', 'Post rescheduled.');
    }

    private function authorizeFunnel(Funnel $funnel): void
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        abort_unless((int) $user->id === (int) $funnel->user_id, 403);
    }

    private function authorizePost(Request $request, Funnel $funnel, FunnelPromotionPost $post): void
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        abort_unless(
            (int) $user->id === (int) $post->user_id && (int) $post->funnel_id === (int) $funnel->id,
            403
        );
    }
}
