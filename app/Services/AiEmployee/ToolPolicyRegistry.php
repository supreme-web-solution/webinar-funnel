<?php

namespace App\Services\AiEmployee;

use App\Ai\Tools\GatedTool;

class ToolPolicyRegistry
{
    public const READ = 'read';

    public const PREPARE = 'prepare';

    public const MUTATE = 'mutate';

    public const DESTRUCTIVE = 'destructive';

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        return [
            'get_status' => self::READ,
            'get_attention_queue' => self::READ,
            'list_campaigns' => self::READ,
            'get_campaign' => self::READ,
            'list_leads' => self::READ,
            'list_tracked_links' => self::READ,
            'list_funnels' => self::READ,
            'list_social_accounts' => self::READ,
            'list_promotion_posts' => self::READ,
            'search_marketplace' => self::READ,
            'extract_offer_url' => self::READ,
            'draft_campaign_plan' => self::PREPARE,
            'draft_content_plan' => self::PREPARE,
            'create_campaign' => self::MUTATE,
            'quick_start_campaign' => self::MUTATE,
            'publish_campaign' => self::MUTATE,
            'pause_campaign' => self::MUTATE,
            'create_tracked_link' => self::MUTATE,
            'generate_content_plan' => self::MUTATE,
            'execute_content_plan' => self::MUTATE,
            'create_promotion_post' => self::MUTATE,
            'publish_promotion_post' => self::MUTATE,
            'delete_campaign' => self::DESTRUCTIVE,
            'delete_tracked_link' => self::DESTRUCTIVE,
            'delete_content_plan' => self::DESTRUCTIVE,
        ];
    }

    public function isDestructive(string $toolName): bool
    {
        return $this->classFor($toolName) === self::DESTRUCTIVE;
    }

    /**
     * @return list<string>
     */
    public function mutateToolNames(): array
    {
        return collect($this->all())
            ->filter(fn (string $class): bool => $class === self::MUTATE)
            ->keys()
            ->values()
            ->all();
    }

    public function classFor(string $toolName): string
    {
        return $this->all()[$toolName] ?? self::MUTATE;
    }

    public function classForTool(GatedTool $tool): string
    {
        return match ($tool->permission()) {
            'read' => self::READ,
            'prepare' => self::PREPARE,
            'execute' => self::MUTATE,
            'destructive' => self::DESTRUCTIVE,
            default => self::MUTATE,
        };
    }
}
