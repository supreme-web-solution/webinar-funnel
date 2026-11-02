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
use Throwable;

class DispatchLeadToEspJob implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;
    public int $timeout = 45;

    public function __construct(
        public int $leadEventId,
        public int $funnelIntegrationId
    ) {
        $this->onQueue('esp-dispatch');
    }

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

            $this->recordDispatch(
                $leadEvent->id,
                $integration->provider,
                (bool) ($result['ok'] ?? false) ? 'success' : 'failed',
                $payload,
                $result,
                (bool) ($result['ok'] ?? false) ? null : ($result['message'] ?? null),
            );
        } catch (\Throwable $exception) {
            $this->recordDispatch(
                $leadEvent->id,
                $integration->provider,
                'failed',
                $payload,
                null,
                $exception->getMessage(),
            );

            throw $exception;
        }
    }

    public function failed(Throwable $exception): void
    {
        $leadEvent = LeadEvent::query()->find($this->leadEventId);
        if (! $leadEvent) {
            return;
        }

        $provider = FunnelIntegration::query()
            ->with('integrationAccount:id,provider')
            ->find($this->funnelIntegrationId)?->integrationAccount?->provider ?? 'unknown';

        $this->recordDispatch(
            $leadEvent->id,
            $provider,
            'failed',
            [
                'funnel_integration_id' => $this->funnelIntegrationId,
                'lead_event_id' => $this->leadEventId,
            ],
            ['message' => 'Queue job exhausted all retries.'],
            $exception->getMessage(),
        );
    }

    /**
     * @param  array<string, mixed>  $requestPayload
     * @param  array<string, mixed>|null  $responsePayload
     */
    private function recordDispatch(
        int $leadEventId,
        string $provider,
        string $status,
        array $requestPayload,
        ?array $responsePayload,
        ?string $errorMessage,
    ): void {
        $attributes = [
            'status' => $status,
            'attempt' => $this->attempts(),
            'request_payload' => $requestPayload,
            'response_payload' => $responsePayload,
            'error_message' => $errorMessage,
        ];

        $queued = DispatchJobLog::query()
            ->where('lead_event_id', $leadEventId)
            ->where('provider', $provider)
            ->where('status', 'queued')
            ->latest('id')
            ->first();

        if ($queued) {
            $queued->update($attributes);

            return;
        }

        DispatchJobLog::query()->create([
            'lead_event_id' => $leadEventId,
            'provider' => $provider,
            ...$attributes,
        ]);
    }
}
