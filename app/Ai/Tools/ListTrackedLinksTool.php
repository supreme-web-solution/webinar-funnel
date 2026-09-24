<?php

namespace App\Ai\Tools;

use App\Models\TrackedLink;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;

class ListTrackedLinksTool extends GatedTool
{
    public function toolName(): string
    {
        return 'list_tracked_links';
    }

    public function permission(): string
    {
        return 'read';
    }

    public function description(): string
    {
        return 'List cloaked tracked links with click counts and public URLs.';
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
        $rows = TrackedLink::query()
            ->where('user_id', $this->user->id)
            ->latest()
            ->limit($limit)
            ->get();

        return $this->json([
            'links' => $rows->map(fn (TrackedLink $link): array => [
                'id' => $link->id,
                'code' => $link->code,
                'label' => $link->label,
                'destination_url' => $link->destination_url,
                'click_count' => $link->click_count,
                'public_url' => $link->publicUrl(),
                'is_active' => $link->is_active,
            ])->all(),
        ]);
    }
}
