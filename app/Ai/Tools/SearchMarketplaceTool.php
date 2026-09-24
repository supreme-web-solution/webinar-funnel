<?php

namespace App\Ai\Tools;

use App\Services\Campaigns\MarketplaceOfferSearchService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;

class SearchMarketplaceTool extends GatedTool
{
    public function toolName(): string
    {
        return 'search_marketplace';
    }

    public function permission(): string
    {
        return 'read';
    }

    public function description(): string
    {
        return 'Discover affiliate offers by keyword on ClickBank, JVZoo, and WarriorPlus. Use when the user wants offer ideas or a niche search — not when they already gave a specific URL (use extract_offer_url instead).';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'keyword' => $schema->string()->required(),
            'marketplace' => $schema->string()->description('Optional: clickbank, jvzoo, warriorplus'),
        ];
    }

    protected function run(Request $request): string
    {
        $keyword = trim((string) $request['keyword']);
        $marketplace = isset($request['marketplace']) ? (string) $request['marketplace'] : null;
        $result = app(MarketplaceOfferSearchService::class)->search($keyword, $marketplace);
        $offers = array_slice($result['results'] ?? [], 0, 8);

        return $this->json([
            'ok' => $result['ok'] ?? false,
            'error' => $result['error'] ?? null,
            'top_pick' => $result['top_pick'] ?? null,
            'results' => $offers,
        ]);
    }
}
