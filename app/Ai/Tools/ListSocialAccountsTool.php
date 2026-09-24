<?php

namespace App\Ai\Tools;

use App\Models\SocialAccount;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;

class ListSocialAccountsTool extends GatedTool
{
    public function toolName(): string
    {
        return 'list_social_accounts';
    }

    public function permission(): string
    {
        return 'read';
    }

    public function description(): string
    {
        return 'List connected social accounts used for publishing and traffic replies.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    protected function run(Request $request): string
    {
        $rows = SocialAccount::query()
            ->where('user_id', $this->user->id)
            ->get(['id', 'platform', 'platform_username', 'zernio_account_id']);

        return $this->json(['accounts' => $rows->toArray()]);
    }
}
