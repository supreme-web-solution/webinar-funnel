<?php

namespace App\Http\Controllers;

use App\Models\CampaignBonus;
use App\Services\Campaigns\CampaignBonusPresenterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BonusLibraryController extends Controller
{
    public function __construct(
        protected CampaignBonusPresenterService $presenter,
    ) {}

    public function index(): Response
    {
        $username = (string) auth()->user()?->username;

        $bonuses = CampaignBonus::query()
            ->where('user_id', auth()->id())
            ->where('status', 'ready')
            ->with('campaign:id,name,slug')
            ->latest()
            ->get()
            ->map(fn (CampaignBonus $b) => $this->presenter->libraryIndexItem($b, $username));

        return Inertia::render('bonuses/Index', [
            'bonuses' => $bonuses,
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $search = $request->string('q')->toString();
        $since = $request->string('since')->toString();

        return response()->json([
            'bonuses' => $this->presenter->libraryForUser(
                (int) auth()->id(),
                $search !== '' ? $search : null,
                50,
                $since !== '' ? $since : null,
            ),
        ]);
    }
}
