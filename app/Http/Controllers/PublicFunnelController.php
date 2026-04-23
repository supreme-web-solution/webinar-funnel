<?php

namespace App\Http\Controllers;

use App\Services\Funnels\PublicFunnelResolver;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class PublicFunnelController extends Controller
{
    public function optin(
        string $username,
        string $slug,
        PublicFunnelResolver $resolver
    ): Response|HttpResponse {
        $funnel = $resolver->resolve($username, $slug);

        if (! $funnel) {
            abort(404);
        }

        $optinPage = $funnel->pages->firstWhere('page_type', 'optin');
        $schema    = $optinPage?->schema ?? [];

        return Inertia::render('public/Optin', [
            'funnel' => [
                'name'  => $funnel->name,
                'slug'  => $funnel->slug,
                'owner' => $username,
            ],
            'pageHtml' => $schema['html'] ?? null,
            'pageCss'  => $schema['css']  ?? null,
            // Legacy fallback keys kept for old funnel schemas
            'page' => $schema,
        ]);
    }

    public function webinar(
        string $username,
        string $slug,
        PublicFunnelResolver $resolver
    ): Response|HttpResponse {
        $funnel = $resolver->resolve($username, $slug);

        if (! $funnel) {
            abort(404);
        }

        $webinarPage = $funnel->pages->firstWhere('page_type', 'webinar');

        return Inertia::render('public/Webinar', [
            'funnel' => [
                'name' => $funnel->name,
                'slug' => $funnel->slug,
                'owner' => $username,
                'settings' => $funnel->settings,
            ],
            'page' => $webinarPage?->schema ?? [],
            'chatMessages' => $funnel->chatRoom?->messages()->orderBy('id')->limit(300)->get() ?? [],
            'chatEndpoints' => [
                'fetch' => route('public.chat.messages', compact('username', 'slug')),
                'send' => route('public.chat.send', compact('username', 'slug')),
            ],
        ]);
    }
}
