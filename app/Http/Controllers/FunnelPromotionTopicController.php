<?php

namespace App\Http\Controllers;

use App\Http\Requests\FunnelPromotionTopicGenerateRequest;
use App\Models\Funnel;
use App\Models\FunnelPromotionTopicSuggestion;
use App\Services\Promotion\PromotionTopicSuggestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FunnelPromotionTopicController extends Controller
{
    public function index(Request $request, Funnel $funnel): JsonResponse
    {
        $this->authorizeFunnel($funnel);

        $topics = FunnelPromotionTopicSuggestion::query()
            ->where('funnel_id', $funnel->id)
            ->where('status', FunnelPromotionTopicSuggestion::STATUS_SUGGESTED)
            ->orderByDesc('score')
            ->orderByDesc('id')
            ->limit(25)
            ->get(['id', 'topic', 'angle', 'status', 'score']);

        return response()->json([
            'topics' => $topics,
        ]);
    }

    public function generate(
        FunnelPromotionTopicGenerateRequest $request,
        Funnel $funnel,
        PromotionTopicSuggestionService $service,
    ): RedirectResponse {
        $this->authorizeFunnel($funnel);

        $validated = $request->validated();
        $count = (int) ($validated['count'] ?? config('promotion.default_sequence_size', 12));
        $context = isset($validated['context']) ? (string) $validated['context'] : null;

        $topics = $service->generate($funnel->loadMissing(['settings', 'template', 'keywords']), $count, $context);
        $service->persist($funnel, $topics);

        return back()->with('success', count($topics).' promotion topic suggestions generated.');
    }

    private function authorizeFunnel(Funnel $funnel): void
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        abort_unless((int) $user->id === (int) $funnel->user_id, 403);
    }
}
