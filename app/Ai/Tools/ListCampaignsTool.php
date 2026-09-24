<?php

namespace App\Ai\Tools;

use App\Models\Campaign;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;

class ListCampaignsTool extends GatedTool
{
    public function toolName(): string
    {
        return 'list_campaigns';
    }

    public function permission(): string
    {
        return 'read';
    }

    public function description(): string
    {
        return 'List affiliate campaigns with id, name, type, status, and edit path.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'limit' => $schema->integer()->min(1)->max(50),
        ];
    }

    protected function run(Request $request): string
    {
        $limit = min(50, max(1, (int) ($request['limit'] ?? 20)));
        $rows = Campaign::query()
            ->where('user_id', $this->user->id)
            ->latest()
            ->limit($limit)
            ->get(['id', 'name', 'type', 'status', 'slug', 'offer_url', 'wizard_step']);

        return $this->json(['campaigns' => $rows->toArray()]);
    }
}
