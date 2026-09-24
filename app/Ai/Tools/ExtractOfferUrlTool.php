<?php

namespace App\Ai\Tools;

use App\Services\Campaigns\OfferIntakeService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;

class ExtractOfferUrlTool extends GatedTool
{
    public function toolName(): string
    {
        return 'extract_offer_url';
    }

    public function permission(): string
    {
        return 'read';
    }

    public function description(): string
    {
        return 'Read a public sales or hoplink URL the user pasted and return structured offer fields (same pipeline as the campaign wizard). Use for “what is on this page?” — not for keyword marketplace discovery.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'url' => $schema->string()->required()->description('Full https URL to a sales page, hoplink, or offer'),
        ];
    }

    protected function run(Request $request): string
    {
        $result = app(OfferIntakeService::class)->extractFromUrl((string) $request['url']);

        return $this->json([
            'ok' => $result['ok'] ?? false,
            'offer' => $result['offer'] ?? null,
            'error' => $result['error'] ?? null,
            'fetch_method' => $result['fetch_method'] ?? null,
        ]);
    }
}
