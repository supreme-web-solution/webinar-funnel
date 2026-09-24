<?php

namespace App\Services\Campaigns;

use App\Mail\CampaignSwipeMail;
use App\Models\Campaign;
use App\Models\CampaignEmailSend;
use App\Models\CampaignLead;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CampaignEmailSequenceService
{
    /**
     * @return array{delay_days: int, send_time: string}
     */
    public function defaultScheduleForIndex(int $index, ?int $planDay = null): array
    {
        $presets = [
            ['delay_days' => 0, 'send_time' => '09:00'],
            ['delay_days' => 1, 'send_time' => '09:00'],
            ['delay_days' => 2, 'send_time' => '10:00'],
            ['delay_days' => 4, 'send_time' => '09:00'],
            ['delay_days' => 6, 'send_time' => '09:00'],
            ['delay_days' => 8, 'send_time' => '10:00'],
            ['delay_days' => 10, 'send_time' => '09:00'],
        ];

        $preset = $presets[$index] ?? ['delay_days' => max(0, $index * 2), 'send_time' => '09:00'];

        if ($planDay !== null) {
            $preset['delay_days'] = max(0, $planDay);
        }

        return $preset;
    }

    /**
     * Seed default send schedules for every campaign email (does not enable auto-send).
     *
     * @param  list<array<string, mixed>>|null  $planItems
     */
    public function seedDefaultSchedules(Campaign $campaign, ?array $planItems = null): void
    {
        $emails = $campaign->emails()->orderBy('sort_order')->orderBy('id')->get();
        if ($emails->isEmpty()) {
            return;
        }

        $schedules = [];
        foreach ($emails as $index => $email) {
            $planDay = null;
            if (is_array($planItems[$index] ?? null)) {
                $rawDay = $planItems[$index]['day'] ?? null;
                $planDay = is_numeric($rawDay) ? (int) $rawDay : null;
            }

            $default = $this->defaultScheduleForIndex($index, $planDay);
            $schedules[] = [
                'campaign_email_id' => $email->id,
                'delay_days' => $default['delay_days'],
                'send_time' => $default['send_time'],
            ];
        }

        $meta = $campaign->meta ?? [];
        $existing = is_array($meta['email_sequence'] ?? null) ? $meta['email_sequence'] : [];

        $meta['email_sequence'] = [
            'enabled' => (bool) ($existing['enabled'] ?? false),
            'schedules' => $schedules,
            'updated_at' => now()->toIso8601String(),
        ];

        $campaign->update(['meta' => $meta]);
    }

    /**
     * @param  array{enabled?: bool, schedules?: list<array{campaign_email_id: int, delay_days?: int, send_time?: string}>}  $config
     */
    public function saveConfig(Campaign $campaign, array $config): void
    {
        $meta = $campaign->meta ?? [];
        $meta['email_sequence'] = [
            'enabled' => (bool) ($config['enabled'] ?? false),
            'schedules' => collect($config['schedules'] ?? [])
                ->map(fn ($row) => [
                    'campaign_email_id' => (int) ($row['campaign_email_id'] ?? 0),
                    'delay_days' => max(0, (int) ($row['delay_days'] ?? 0)),
                    'send_time' => $this->normalizeSendTime($row['send_time'] ?? '09:00'),
                ])
                ->filter(fn ($row) => $row['campaign_email_id'] > 0)
                ->values()
                ->all(),
            'updated_at' => now()->toIso8601String(),
        ];

        $campaign->update(['meta' => $meta]);
    }

    /**
     * @return array{enabled: bool, schedules: list<array<string, mixed>>}
     */
    public function configForCampaign(Campaign $campaign): array
    {
        $stored = is_array($campaign->meta['email_sequence'] ?? null)
            ? $campaign->meta['email_sequence']
            : [];

        $storedById = collect(is_array($stored['schedules'] ?? null) ? $stored['schedules'] : [])
            ->keyBy(fn ($row) => (int) ($row['campaign_email_id'] ?? 0));

        $emails = $campaign->emails()->orderBy('sort_order')->orderBy('id')->get();
        $schedules = [];

        foreach ($emails as $index => $email) {
            $row = $storedById->get($email->id);
            if (is_array($row)) {
                $schedules[] = [
                    'campaign_email_id' => $email->id,
                    'delay_days' => max(0, (int) ($row['delay_days'] ?? 0)),
                    'send_time' => $this->normalizeSendTime((string) ($row['send_time'] ?? '09:00')),
                ];

                continue;
            }

            $default = $this->defaultScheduleForIndex($index);
            $schedules[] = [
                'campaign_email_id' => $email->id,
                'delay_days' => $default['delay_days'],
                'send_time' => $default['send_time'],
            ];
        }

        return [
            'enabled' => (bool) ($stored['enabled'] ?? false),
            'schedules' => $schedules,
        ];
    }

    public function scheduleForLead(Campaign $campaign, CampaignLead $lead): void
    {
        $config = $this->configForCampaign($campaign);
        if (! $config['enabled'] || $config['schedules'] === []) {
            return;
        }

        $emailIds = $campaign->emails()->pluck('id')->map(fn ($id) => (int) $id)->all();

        foreach ($config['schedules'] as $schedule) {
            $emailId = (int) ($schedule['campaign_email_id'] ?? 0);
            if ($emailId <= 0 || ! in_array($emailId, $emailIds, true)) {
                continue;
            }

            $anchor = $lead->created_at ?? now();
            $scheduledAt = $this->scheduledAt(
                (int) ($schedule['delay_days'] ?? 0),
                (string) ($schedule['send_time'] ?? '09:00'),
                $anchor,
            );

            CampaignEmailSend::query()->firstOrCreate(
                [
                    'campaign_email_id' => $emailId,
                    'campaign_lead_id' => $lead->id,
                ],
                [
                    'campaign_id' => $campaign->id,
                    'status' => CampaignEmailSend::STATUS_PENDING,
                    'scheduled_at' => $scheduledAt,
                ]
            );
        }
    }

    public function processDueSends(): int
    {
        $due = CampaignEmailSend::query()
            ->where('status', CampaignEmailSend::STATUS_PENDING)
            ->where('scheduled_at', '<=', now())
            ->with(['campaignEmail', 'campaignLead', 'campaign.user'])
            ->limit(50)
            ->get();

        $sent = 0;

        foreach ($due as $send) {
            if ($this->dispatchSend($send)) {
                $sent++;
            }
        }

        return $sent;
    }

    public function dispatchSend(CampaignEmailSend $send): bool
    {
        $email = $send->campaignEmail;
        $lead = $send->campaignLead;
        $campaign = $send->campaign;

        if (! $email || ! $lead || ! $campaign) {
            $send->update([
                'status' => CampaignEmailSend::STATUS_SKIPPED,
                'error_message' => 'Missing email, lead, or campaign.',
            ]);

            return false;
        }

        if (! filter_var($lead->email, FILTER_VALIDATE_EMAIL)) {
            $send->update([
                'status' => CampaignEmailSend::STATUS_FAILED,
                'error_message' => 'Invalid lead email.',
            ]);

            return false;
        }

        try {
            Mail::to($lead->email)->send(new CampaignSwipeMail(
                subjectLine: $email->subject,
                body: (string) ($email->body ?? ''),
                campaignName: $campaign->name,
                recipientName: (string) ($lead->name ?: 'there'),
            ));

            $send->update([
                'status' => CampaignEmailSend::STATUS_SENT,
                'sent_at' => now(),
                'error_message' => null,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::warning('[CampaignEmailSequence] Send failed', [
                'send_id' => $send->id,
                'error' => $e->getMessage(),
            ]);

            $send->update([
                'status' => CampaignEmailSend::STATUS_FAILED,
                'error_message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * @return array{pending: int, sent: int, failed: int}
     */
    public function statsForCampaign(Campaign $campaign): array
    {
        $rows = CampaignEmailSend::query()
            ->where('campaign_id', $campaign->id)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'pending' => (int) ($rows[CampaignEmailSend::STATUS_PENDING] ?? 0),
            'sent' => (int) ($rows[CampaignEmailSend::STATUS_SENT] ?? 0),
            'failed' => (int) ($rows[CampaignEmailSend::STATUS_FAILED] ?? 0),
        ];
    }

    protected function scheduledAt(int $delayDays, string $sendTime, ?Carbon $from = null): Carbon
    {
        $from = ($from ?? now())->copy();
        $time = $this->normalizeSendTime($sendTime);
        [$hour, $minute] = array_map('intval', explode(':', $time));

        $target = $from->copy()->addDays($delayDays)->setTime($hour, $minute, 0);

        // e.g. Day 0 @ 09:00 but lead opted in at 14:00 — don't leave a past timestamp; send soon after opt-in.
        if ($target->lte($from)) {
            return $from->copy()->addMinute();
        }

        return $target;
    }

    protected function normalizeSendTime(string $time): string
    {
        if (preg_match('/^(\d{1,2}):(\d{2})$/', trim($time), $m)) {
            $hour = max(0, min(23, (int) $m[1]));
            $minute = max(0, min(59, (int) $m[2]));

            return sprintf('%02d:%02d', $hour, $minute);
        }

        return '09:00';
    }
}
