<?php

namespace App\Http\Controllers;

use App\Services\AiEmployee\AgentOrchestrator;
use App\Services\AiEmployee\AiEmployeeSessionService;
use App\Services\AiEmployee\AiEmployeeSettingsService;
use App\Services\AiEmployee\CommandCenterStateService;
use App\Services\AiEmployee\WhatsAppPairingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CommandCenterController extends Controller
{
    public function __construct(
        protected CommandCenterStateService $state,
        protected AgentOrchestrator $orchestrator,
        protected AiEmployeeSettingsService $settings,
        protected AiEmployeeSessionService $sessions,
        protected WhatsAppPairingService $pairing,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('command-center/Index', $this->state->for($request->user()));
    }

    public function state(Request $request): JsonResponse
    {
        return response()->json($this->state->for($request->user()));
    }

    public function chat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:8000'],
        ]);

        $result = $this->orchestrator->handle($request->user(), $validated['message'], 'web');

        return response()->json([
            ...$result,
            'state' => $this->state->for($request->user()),
        ]);
    }

    public function launch(Request $request, int $approval): JsonResponse
    {
        $message = $this->orchestrator->launch($request->user(), $approval);

        return response()->json([
            'message' => $message,
            'state' => $this->state->for($request->user()),
        ]);
    }

    public function reject(Request $request, int $approval): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $message = $this->orchestrator->reject($request->user(), $approval, $validated['reason'] ?? null);

        return response()->json([
            'message' => $message,
            'state' => $this->state->for($request->user()),
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'killed' => ['sometimes', 'boolean'],
            'autonomy' => ['sometimes', 'in:copilot,assisted,autopilot'],
            'execute_allowlist' => ['sometimes', 'array'],
            'execute_allowlist.*' => ['string', 'max:80'],
        ]);

        $this->settings->update($request->user(), $validated);

        return response()->json(['state' => $this->state->for($request->user())]);
    }

    public function pairing(Request $request): JsonResponse
    {
        $pairing = $this->pairing->start($request->user());

        return response()->json([
            'pairing' => $pairing,
            'state' => $this->state->for($request->user()),
        ]);
    }

    public function clear(Request $request): JsonResponse
    {
        $this->sessions->resetConversation($request->user());

        return response()->json(['state' => $this->state->for($request->user())]);
    }
}
