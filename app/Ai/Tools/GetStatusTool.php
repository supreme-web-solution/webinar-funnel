<?php

namespace App\Ai\Tools;

use App\Services\AiEmployee\CommandCenterSnapshotService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;

class GetStatusTool extends GatedTool
{
    public function toolName(): string
    {
        return 'get_status';
    }

    public function permission(): string
    {
        return 'read';
    }

    public function description(): string
    {
        return 'Snapshot of the workspace: campaigns, funnels, leads, tracked links, social accounts, and posts.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    protected function run(Request $request): string
    {
        return $this->json(app(CommandCenterSnapshotService::class)->status($this->user));
    }
}
