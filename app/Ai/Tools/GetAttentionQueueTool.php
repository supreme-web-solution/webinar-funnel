<?php

namespace App\Ai\Tools;

use App\Services\AiEmployee\CommandCenterSnapshotService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;

class GetAttentionQueueTool extends GatedTool
{
    public function toolName(): string
    {
        return 'get_attention_queue';
    }

    public function permission(): string
    {
        return 'read';
    }

    public function description(): string
    {
        return 'Items that need a human: pending launches, draft campaigns, failed posts, failed mention replies.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    protected function run(Request $request): string
    {
        $items = app(CommandCenterSnapshotService::class)->attention($this->user);

        return $this->json(['count' => count($items), 'items' => $items]);
    }
}
