<?php

namespace App\Jobs;

use App\Models\DispatchJobLog;
use App\Models\FunnelIntegration;
use App\Models\LeadEvent;
use App\Services\Esp\EspDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DispatchLeadToEspJob implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public int $leadEventId,
        public int $funnelIntegrationId
    ) {}

    public function handle(EspDispatcher $dispatcher): void
    {
        $leadEvent = LeadEvent::query()->with('lead')->find($this->leadEventId);
        $funnelIntegration = FunnelIntegration::query()
            ->with('integrationAccount')
            ->find($this->funnelIntegrationId);

        if (! $leadEvent || ! $funnelIntegration || ! $funnelIntegration->enabled) {
            return;
        }

        $integration = $funnelIntegration->integrationAccount;

        if (! $integration) {
            return;
        }

        $payload = [
            'name' => $leadEvent->lead->name,
            'email' => $leadEvent->lead->email,
            'funnel_id' => $leadEvent->lead->funnel_id,
            'lead_id' => $leadEvent->lead->id,
            'event_id' => $leadEvent->id,
        ];

        try {
            $result = $dispatcher->dispatch(
                $integration,
                $payload,
                $funnelIntegration->provider_list_config ?? []
            );

            DispatchJobLog::query()->create([
                'lead_event_id' => $leadEvent->id,
                'provider' => $integration->provider,
                'status' => ($result['ok'] ?? false) ? 'success' : 'failed',
                'attempt' => $this->attempts(),
                'request_payload' => $payload,
                'response_payload' => $result,
                'error_message' => ($result['ok'] ?? false) ? null : ($result['message'] ?? null),
            ]);
        } catch (\Throwable $exception) {
            DispatchJobLog::query()->create([
                'lead_event_id' => $leadEvent->id,
                'provider' => $integration->provider,
                'status' => 'failed',
                'attempt' => $this->attempts(),
                'request_payload' => $payload,
                'error_message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
