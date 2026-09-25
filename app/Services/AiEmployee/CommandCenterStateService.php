<?php

namespace App\Services\AiEmployee;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Ai\Models\ConversationMessage;

class CommandCenterStateService
{
    public function __construct(
        protected AiEmployeeSessionService $sessions,
        protected AiEmployeeSettingsService $settings,
        protected ActionApprovalService $approvals,
        protected AiActionLogService $logs,
        protected CommandCenterSnapshotService $snapshot,
        protected WhatsAppPairingService $pairing,
        protected AiEmployeeBrandingService $branding,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function for(User $user, bool $includeMessages = true): array
    {
        $session = $this->sessions->for($user);
        $setting = $this->settings->for($user);

        $payload = [
            'employee' => $this->branding->employeePayload(),
            'conversation_id' => $session->conversation_id,
            'processing' => $session->isProcessing(),
            'progress' => $session->progress,
            'approvals' => $this->approvals->pendingPayload($user),
            'activity' => $this->logs->recent($user),
            'attention' => $this->snapshot->attention($user),
            'status' => $this->snapshot->status($user),
            'settings' => $this->settings->payload($setting),
            'suggestions' => config('ai_employee.suggestions', []),
            'execute_tools' => $this->executeTools(),
            'whatsapp' => [
                'configured' => (string) config('ai_employee.whatsapp.account_id', '') !== ''
                    || (string) config('ai_employee.whatsapp.phone', '') !== '',
                'phone' => config('ai_employee.whatsapp.phone'),
                'pairing' => $this->pairing->current($user),
                'linked_phone' => $setting->whatsapp_phone,
            ],
        ];

        if ($includeMessages) {
            $page = $this->messagesRecent($session->conversation_id);
            $payload['messages'] = $page['data'];
            $payload['messages_meta'] = [
                'has_older' => $page['has_older'],
                'oldest_id' => $page['oldest_id'],
            ];
        }

        return $payload;
    }

    /**
     * Latest page (chronological order, oldest → newest).
     *
     * @return array{data: list<array<string, mixed>>, has_older: bool, oldest_id: string|null, newest_id: string|null}
     */
    public function messagesRecent(?string $conversationId, ?int $limit = null): array
    {
        if (! is_string($conversationId) || $conversationId === '') {
            return ['data' => [], 'has_older' => false, 'oldest_id' => null, 'newest_id' => null];
        }

        $limit = $limit ?? $this->pageSize();
        $query = $this->baseMessageQuery($conversationId);

        $rows = (clone $query)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit + 1)
            ->get();

        $hasOlder = $rows->count() > $limit;
        if ($hasOlder) {
            $rows = $rows->take($limit);
        }

        $chronological = $rows->reverse()->values();

        return [
            'data' => $this->mapMessages($chronological),
            'has_older' => $hasOlder,
            'oldest_id' => $chronological->first()?->id,
            'newest_id' => $chronological->last()?->id,
        ];
    }

    /**
     * Older messages before a cursor id (chronological).
     *
     * @return array{data: list<array<string, mixed>>, has_older: bool, oldest_id: string|null}
     */
    public function messagesBefore(?string $conversationId, string $beforeId, ?int $limit = null): array
    {
        if (! is_string($conversationId) || $conversationId === '') {
            return ['data' => [], 'has_older' => false, 'oldest_id' => null];
        }

        $anchor = ConversationMessage::query()->find($beforeId);
        if ($anchor === null) {
            return ['data' => [], 'has_older' => false, 'oldest_id' => null];
        }

        $limit = $limit ?? $this->pageSize();
        $query = $this->baseMessageQuery($conversationId);
        $this->applyBeforeCursor($query, $anchor);

        $rows = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit + 1)
            ->get();

        $hasOlder = $rows->count() > $limit;
        if ($hasOlder) {
            $rows = $rows->take($limit);
        }

        $chronological = $rows->reverse()->values();

        return [
            'data' => $this->mapMessages($chronological),
            'has_older' => $hasOlder,
            'oldest_id' => $chronological->first()?->id,
        ];
    }

    /**
     * New messages after a cursor id (chronological).
     *
     * @return list<array<string, mixed>>
     */
    public function messagesAfter(?string $conversationId, string $afterId, ?int $limit = null): array
    {
        if (! is_string($conversationId) || $conversationId === '') {
            return [];
        }

        $anchor = ConversationMessage::query()->find($afterId);
        if ($anchor === null) {
            return [];
        }

        $limit = $limit ?? $this->pageSize();
        $query = $this->baseMessageQuery($conversationId);
        $this->applyAfterCursor($query, $anchor);

        $rows = $query
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        return $this->mapMessages($rows);
    }

    /**
     * @return Builder<ConversationMessage>
     */
    protected function baseMessageQuery(string $conversationId): Builder
    {
        return ConversationMessage::query()
            ->where('conversation_id', $conversationId)
            ->whereIn('role', ['user', 'assistant']);
    }

    protected function applyBeforeCursor(Builder $query, ConversationMessage $anchor): void
    {
        $query->where(function (Builder $q) use ($anchor): void {
            $q->where('created_at', '<', $anchor->created_at)
                ->orWhere(function (Builder $q2) use ($anchor): void {
                    $q2->where('created_at', '=', $anchor->created_at)
                        ->where('id', '<', $anchor->id);
                });
        });
    }

    protected function applyAfterCursor(Builder $query, ConversationMessage $anchor): void
    {
        $query->where(function (Builder $q) use ($anchor): void {
            $q->where('created_at', '>', $anchor->created_at)
                ->orWhere(function (Builder $q2) use ($anchor): void {
                    $q2->where('created_at', '=', $anchor->created_at)
                        ->where('id', '>', $anchor->id);
                });
        });
    }

    protected function pageSize(): int
    {
        return max(10, min(50, (int) config('ai_employee.messages_page_size', 30)));
    }

    /**
     * @param  iterable<int, ConversationMessage>  $rows
     * @return list<array<string, mixed>>
     */
    protected function mapMessages(iterable $rows): array
    {
        $out = [];
        foreach ($rows as $message) {
            $out[] = [
                'id' => $message->id,
                'role' => $message->role,
                'content' => $message->content,
                'created_at' => $message->created_at?->toIso8601String(),
            ];
        }

        return $out;
    }

    /**
     * @return list<array{name: string, label: string}>
     */
    protected function executeTools(): array
    {
        $labels = [
            'create_campaign' => 'Create campaign',
            'quick_start_campaign' => 'Quick-start campaign',
            'publish_campaign' => 'Publish campaign',
            'pause_campaign' => 'Pause campaign',
            'create_tracked_link' => 'Create tracked link',
            'generate_content_plan' => 'Generate content plan',
            'execute_content_plan' => 'Execute content plan',
            'create_promotion_post' => 'Create promotion post',
            'publish_promotion_post' => 'Publish promotion post',
        ];

        return collect(app(ToolPolicyRegistry::class)->all())
            ->filter(fn (string $class): bool => $class === ToolPolicyRegistry::MUTATE)
            ->map(fn (string $class, string $name): array => [
                'name' => $name,
                'label' => $labels[$name] ?? str_replace('_', ' ', $name),
            ])
            ->values()
            ->all();
    }
}
