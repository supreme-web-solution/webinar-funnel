<?php

namespace App\Http\Controllers;

use App\Http\Requests\IntegrationAccountStoreRequest;
use App\Models\DispatchJobLog;
use App\Models\IntegrationAccount;
use App\Services\Esp\EspDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class IntegrationController extends Controller
{
    public function index(): Response
    {
        $accounts = IntegrationAccount::query()
            ->where('user_id', auth()->id())
            ->latest()
            ->get(['id', 'provider', 'name', 'status', 'last_connected_at', 'created_at']);

        $dispatchLogs = DispatchJobLog::query()
            ->whereHas('leadEvent.lead.funnel', fn ($q) => $q->where('user_id', auth()->id()))
            ->with(['leadEvent.lead.funnel:id,name'])
            ->latest()
            ->limit(25)
            ->get([
                'id',
                'provider',
                'status',
                'attempt',
                'error_message',
                'lead_event_id',
                'created_at',
            ]);

        $queueHealth = [
            'queued' => DispatchJobLog::query()
                ->whereHas('leadEvent.lead.funnel', fn ($q) => $q->where('user_id', auth()->id()))
                ->where('status', 'queued')
                ->count(),
            'failed_last_24h' => DispatchJobLog::query()
                ->whereHas('leadEvent.lead.funnel', fn ($q) => $q->where('user_id', auth()->id()))
                ->where('status', 'failed')
                ->where('created_at', '>=', now()->subDay())
                ->count(),
        ];

        return Inertia::render('integrations/Index', [
            'accounts' => $accounts,
            'dispatchLogs' => $dispatchLogs->map(fn ($log) => [
                'id' => $log->id,
                'provider' => $log->provider,
                'status' => $log->status,
                'attempt' => $log->attempt,
                'error_message' => $log->error_message,
                'funnel_name' => $log->leadEvent?->lead?->funnel?->name,
                'created_at' => $log->created_at?->toIso8601String(),
            ])->values(),
            'queueHealth' => $queueHealth,
        ]);
    }

    public function store(IntegrationAccountStoreRequest $request): RedirectResponse
    {
        IntegrationAccount::query()->create([
            'user_id'             => $request->user()->id,
            'provider'            => $request->validated('provider'),
            'name'                => $request->validated('name'),
            'credentials'         => $request->validated('credentials'),
            'config'              => $request->validated('config') ?? [],
            'status'              => 'active',
            'last_connected_at'   => now(),
        ]);

        return back()->with('success', 'Integration saved.');
    }

    public function destroy(IntegrationAccount $integration): RedirectResponse
    {
        abort_if($integration->user_id !== auth()->id(), 403);

        $integration->delete();

        return back()->with('success', 'Integration removed.');
    }

    public function test(IntegrationAccount $integration, EspDispatcher $dispatcher): JsonResponse
    {
        abort_if($integration->user_id !== auth()->id(), 403);

        try {
            $result = $dispatcher->testConnection($integration);

            if ($result['ok']) {
                $integration->update(['status' => 'active', 'last_connected_at' => now()]);
            } else {
                $integration->update(['status' => 'error']);
            }

            return response()->json([
                'ok'      => $result['ok'],
                'message' => $result['message'],
            ]);
        } catch (\Throwable $e) {
            $integration->update(['status' => 'error']);

            return response()->json([
                'ok'      => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
