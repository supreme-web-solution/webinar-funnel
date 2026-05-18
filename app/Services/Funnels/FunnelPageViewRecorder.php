<?php

namespace App\Services\Funnels;

use App\Models\Funnel;
use App\Models\FunnelPageView;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class FunnelPageViewRecorder
{
    public function record(Funnel $funnel, string $pageType, Request $request): void
    {
        if (! in_array($pageType, ['optin', 'webinar'], true)) {
            return;
        }

        $sessionKey = $request->session()->getId();
        if ($sessionKey === '') {
            $sessionKey = hash('sha256', ($request->ip() ?? 'unknown').'|'.($request->userAgent() ?? ''));
        }

        FunnelPageView::query()->create([
            'funnel_id' => $funnel->id,
            'page_type' => $pageType,
            'session_key' => Str::limit($sessionKey, 80, ''),
            'viewed_at' => now(),
        ]);
    }
}
