<?php

namespace App\Services\AiEmployee;

use App\Models\User;
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
    public function for(User $user): array
    {
        $session = $this->sessions->for($user);
        $setting = $this->settings->for($user);

        return [
            'employee' => $this->branding->employeePayload(),
            'conversation_id' => $session->conversation_id,
            'processing' => $session->isProcessing(),
            'progress' => $session->progress,
            'messages' => $this->messages($session->conversation_id),
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
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function messages(?string $conversationId): array
    {
        if (! is_string($conversationId) || $conversationId === '') {
            return [];
        }

        return ConversationMessage::query()
            ->where('conversation_id', $conversationId)
            ->whereIn('role', ['user', 'assistant'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->map(fn (ConversationMessage $message): array => [
                'id' => $message->id,
                'role' => $message->role,
                'content' => $message->content,
                'created_at' => $message->created_at?->toIso8601String(),
            ])
            ->all();
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
