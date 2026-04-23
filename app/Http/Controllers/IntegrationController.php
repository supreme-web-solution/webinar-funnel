<?php

namespace App\Http\Controllers;

use App\Http\Requests\IntegrationAccountStoreRequest;
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

        return Inertia::render('integrations/Index', [
            'accounts' => $accounts,
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
