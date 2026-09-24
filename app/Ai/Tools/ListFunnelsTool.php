<?php

namespace App\Ai\Tools;

use App\Models\Funnel;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;

class ListFunnelsTool extends GatedTool
{
    public function toolName(): string
    {
        return 'list_funnels';
    }

    public function permission(): string
    {
        return 'read';
    }

    public function description(): string
    {
        return 'List webinar and opt-in funnels for the user.';
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
        $rows = Funnel::query()
            ->where('user_id', $this->user->id)
            ->latest()
            ->limit($limit)
            ->get(['id', 'name', 'slug', 'status', 'campaign_id']);

        return $this->json(['funnels' => $rows->toArray()]);
    }
}
