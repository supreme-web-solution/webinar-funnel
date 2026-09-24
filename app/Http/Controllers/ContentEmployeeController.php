<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\ContentEmployeePlan;
use App\Services\Content\ContentEmployeePlanService;
use App\Services\Content\PlatformFormatCatalog;
use App\Services\Traffic\UserTrafficProfileService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ContentEmployeeController extends Controller
{
    public function __construct(
        protected ContentEmployeePlanService $planService,
        protected UserTrafficProfileService $profileService,
        protected PlatformFormatCatalog $formatCatalog,
    ) {}

    public function generatePlan(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'campaign_id' => ['nullable', 'integer', 'exists:campaigns,id'],
            'week_start' => ['nullable', 'date'],
            'auto_select_formats' => ['nullable', 'boolean'],
            'save_auto_preference' => ['nullable', 'boolean'],
            'plan_platforms' => ['nullable', 'array'],
            'plan_platforms.*' => ['string'],
            'planning_brief' => ['nullable', 'string', 'max:2000'],
        ]);

        $campaign = null;
        if (! empty($validated['campaign_id'])) {
            $campaign = Campaign::query()
                ->where('user_id', $request->user()->id)
                ->findOrFail((int) $validated['campaign_id']);
        }

        $weekStart = isset($validated['week_start'])
            ? Carbon::parse($validated['week_start'])->startOfWeek()
            : Carbon::now()->startOfWeek();

        if (($validated['save_auto_preference'] ?? false) === true && array_key_exists('auto_select_formats', $validated)) {
            $this->profileService->update($request->user(), [
                'auto_select_formats' => (bool) $validated['auto_select_formats'],
            ]);
        }

        if (array_key_exists('plan_platforms', $validated)) {
            $this->profileService->updatePlanPlatforms(
                $request->user(),
                array_values(array_filter($validated['plan_platforms'] ?? [], fn ($p) => is_string($p) && $p !== '')),
            );
        }

        $planningBrief = isset($validated['planning_brief'])
            ? trim((string) $validated['planning_brief'])
            : null;

        if ($campaign === null && ($planningBrief === null || $planningBrief === '')) {
            throw ValidationException::withMessages([
                'planning_brief' => 'Describe what you want to post about — your niche, audience, offer, or specific topic ideas.',
            ]);
        }

        if ($planningBrief !== null) {
            $this->profileService->update($request->user(), [
                'planning_brief' => $planningBrief,
            ]);
        }

        $autoSelect = array_key_exists('auto_select_formats', $validated)
            ? (bool) $validated['auto_select_formats']
            : null;

        $plan = $this->planService->generateWeeklyPlan(
            $request->user(),
            $campaign,
            $weekStart,
            $autoSelect,
            $planningBrief,
        );

        if ($request->wantsJson()) {
            return response()->json(['plan' => $this->planService->planPayload($plan)]);
        }

        return back()->with('success', 'Weekly content plan generated.');
    }

    public function approve(Request $request, ContentEmployeePlan $plan): RedirectResponse|JsonResponse
    {
        $this->authorizePlan($request, $plan);
        $plan = $this->planService->approve($plan);

        if ($request->wantsJson()) {
            return response()->json(['plan' => $this->planService->planPayload($plan)]);
        }

        return back()->with('success', 'Plan approved.');
    }

    public function execute(Request $request, ContentEmployeePlan $plan): RedirectResponse|JsonResponse
    {
        $this->authorizePlan($request, $plan);
        $plan = $this->planService->execute($plan, $request->user());

        if ($request->wantsJson()) {
            return response()->json(['plan' => $this->planService->planPayload($plan)]);
        }

        $queued = (int) data_get($plan->meta, 'execute_summary.queued', 0);

        return back()->with('success', "Plan executed — {$queued} posts queued for generation.");
    }

    public function destroy(Request $request, ContentEmployeePlan $plan): RedirectResponse
    {
        $this->authorizePlan($request, $plan);

        if (in_array($plan->status, [ContentEmployeePlan::STATUS_EXECUTING], true)) {
            return back()->withErrors(['plan' => 'Cannot delete a plan that is currently executing.']);
        }

        $plan->items()->delete();
        $plan->delete();

        return back()->with('success', 'Plan deleted.');
    }

    protected function authorizePlan(Request $request, ContentEmployeePlan $plan): void
    {
        abort_unless((int) $plan->user_id === (int) $request->user()->id, 403);
    }
}
