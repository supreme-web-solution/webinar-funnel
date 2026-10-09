<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class ProfitMultiplierController extends Controller
{
    public function __invoke(): Response
    {
        $links = collect(config('addons.profit_multiplier.links', []))
            ->map(fn (array $link): array => [
                'label' => (string) ($link['label'] ?? ''),
                'url' => is_string($link['url'] ?? null) && trim($link['url']) !== '' ? trim($link['url']) : null,
            ])
            ->values()
            ->all();

        return Inertia::render('profit-multiplier/Index', [
            'links' => $links,
        ]);
    }
}
