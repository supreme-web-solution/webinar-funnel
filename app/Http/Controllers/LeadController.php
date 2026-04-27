<?php

namespace App\Http\Controllers;

use App\Http\Requests\LeadCaptureRequest;
use App\Jobs\DispatchLeadToEspJob;
use App\Models\DispatchJobLog;
use App\Models\Funnel;
use App\Models\Lead;
use App\Models\LeadEvent;
use App\Services\Funnels\PublicFunnelResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class LeadController extends Controller
{
    public function index(): Response
    {
        $userId  = auth()->id();
        $search  = request()->string('search')->toString();
        $funnelId = request()->integer('funnel_id') ?: null;

        $baseQuery = Lead::query()
            ->whereHas('funnel', fn ($q) => $q->where('user_id', $userId));

        // Stats (no filter applied, always show totals)
        $totalLeads   = (clone $baseQuery)->count();
        $weekLeads    = (clone $baseQuery)->where('created_at', '>=', now()->startOfWeek())->count();
        $funnelCount  = (clone $baseQuery)->distinct('funnel_id')->count('funnel_id');

        // Filtered query for the table
        $tableQuery = (clone $baseQuery)->with('funnel:id,name,slug');

        if ($search !== '') {
            $tableQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($funnelId) {
            $tableQuery->where('funnel_id', $funnelId);
        }

        $leads = $tableQuery->latest()->paginate(25)->withQueryString();

        // Funnel options for filter dropdown
        $funnels = Funnel::query()
            ->where('user_id', $userId)
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        return Inertia::render('leads/Index', [
            'leads'       => $leads,
            'funnels'     => $funnels,
            'stats'       => [
                'total'       => $totalLeads,
                'this_week'   => $weekLeads,
                'funnel_count' => $funnelCount,
            ],
            'filters'     => [
                'search'    => $search,
                'funnel_id' => $funnelId,
            ],
        ]);
    }

    public function capture(
        LeadCaptureRequest $request,
        string $username,
        string $slug,
        PublicFunnelResolver $resolver
    ): RedirectResponse {
        $funnel = $resolver->resolve($username, $slug);
        abort_if(! $funnel, 404);

        $validated = $request->validated();
        $emailHash = hash('sha256', Str::lower(trim($validated['email'])));

        $lead = Lead::query()->firstOrCreate(
            [
                'funnel_id' => $funnel->id,
                'email_hash' => $emailHash,
            ],
            [
                'name' => $validated['name'],
                'email' => $validated['email'],
                'source' => 'optin',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => $validated['metadata'] ?? [],
            ]
        );

        $leadEvent = LeadEvent::query()->create([
            'lead_id' => $lead->id,
            'event_type' => 'captured',
            'status' => 'success',
            'payload' => [
                'funnel_id' => $funnel->id,
                'username' => $username,
                'slug' => $slug,
            ],
        ]);

        $enabledIntegrations = $funnel->integrations()
            ->where('enabled', true)
            ->with('integrationAccount:id,provider')
            ->get(['id', 'integration_account_id']);

        foreach ($enabledIntegrations as $funnelIntegration) {
            $funnelIntegrationId = (int) $funnelIntegration->id;
            $provider = $funnelIntegration->integrationAccount?->provider ?? 'unknown';

            DispatchJobLog::query()->create([
                'lead_event_id' => $leadEvent->id,
                'provider' => $provider,
                'status' => 'queued',
                'attempt' => 0,
                'request_payload' => [
                    'funnel_integration_id' => (int) $funnelIntegrationId,
                    'lead_id' => $lead->id,
                    'queued_at' => now()->toIso8601String(),
                ],
                'response_payload' => ['message' => 'Job queued for processing.'],
            ]);

            DispatchLeadToEspJob::dispatch($leadEvent->id, (int) $funnelIntegrationId)
                ->onQueue('esp-dispatch');
        }

        Log::info('Lead captured', [
            'funnel_id' => $funnel->id,
            'lead_id' => $lead->id,
            'integration_count' => $enabledIntegrations->count(),
        ]);

        $request->session()->put("funnel_lead.{$funnel->id}", [
            'lead_id' => $lead->id,
            'name' => $lead->name,
            'email' => $lead->email,
        ]);

        return redirect()->route('public.webinar', [
            'username' => $username,
            'slug' => $slug,
        ]);
    }
}
