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
                'configured' => $this->whatsappConfigured(),
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

    protected function whatsappConfigured(): bool
    {
        if ((string) config('services.zernio.api_key', '') === '') {
            return false;
        }

        return (string) config('ai_employee.whatsapp.phone', '') !== ''
            || (string) config('ai_employee.whatsapp.account_id', '') !== ''
            || (string) config('ai_employee.whatsapp.webhook_secret', '') !== '';
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
                'channel' => $this->messageChannel($message),
                'created_at' => $message->created_at?->toIso8601String(),
            ];
        }

        return $this->collapseDuplicateUserMessages($out);
    }

    protected function messageChannel(ConversationMessage $message): string
    {
        $meta = $message->meta;
        if (is_string($meta)) {
            $decoded = json_decode($meta, true);
            $meta = is_array($decoded) ? $decoded : [];
        }

        return is_array($meta) && ($meta['channel'] ?? null) === 'whatsapp' ? 'whatsapp' : 'web';
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    protected function collapseDuplicateUserMessages(array $rows): array
    {
        $out = [];

        foreach ($rows as $row) {
            $previous = $out === [] ? null : $out[array_key_last($out)];
            if (
                is_array($previous)
                && ($previous['role'] ?? null) === 'user'
                && ($row['role'] ?? null) === 'user'
                && trim((string) ($previous['content'] ?? '')) === trim((string) ($row['content'] ?? ''))
            ) {
                $previousAt = strtotime((string) ($previous['created_at'] ?? '')) ?: 0;
                $rowAt = strtotime((string) ($row['created_at'] ?? '')) ?: 0;
                if ($previousAt === 0 || $rowAt === 0 || abs($rowAt - $previousAt) <= 180) {
                    if (($row['channel'] ?? null) === 'whatsapp' && ($previous['channel'] ?? null) !== 'whatsapp') {
                        $out[array_key_last($out)] = $row;
                    }

                    continue;
                }
            }

            $out[] = $row;
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
