<?php

namespace App\Services\AiEmployee;

class ChatPresentationService
{
    public function formatLaunchResult(string $toolName, string $rawResult): string
    {
        $trim = trim($rawResult);
        if ($trim === '') {
            return 'Action completed.';
        }

        $data = json_decode($trim, true);
        if (! is_array($data)) {
            return $trim;
        }

        if (isset($data['error']) && is_string($data['error'])) {
            return '**Could not complete:** '.$data['error'];
        }

        return match ($toolName) {
            'quick_start_campaign', 'create_campaign' => $this->campaignCreated($data),
            'create_tracked_link' => $this->trackedLinkCreated($data),
            'publish_campaign', 'pause_campaign' => $this->campaignAction($data),
            'generate_content_plan', 'execute_content_plan' => $this->contentPlan($data),
            'create_promotion_post' => $this->promotionPost($data),
            'publish_promotion_post' => $this->promotionPublish($data),
            default => $this->genericSuccess($data),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function campaignCreated(array $data): string
    {
        $name = (string) ($data['name'] ?? 'New campaign');
        $id = $data['id'] ?? null;
        $edit = (string) ($data['edit_url'] ?? ($id ? '/campaigns/'.$id.'/edit' : '/campaigns'));
        $lines = [
            '**Campaign created:** '.$name.($id ? ' (#'.$id.')' : ''),
        ];

        if (($data['queued'] ?? false) === true) {
            $lines[] = 'AI is building pages, bonuses, and emails in the background.';
        }

        if ($id !== null) {
            $lines[] = '[Open campaign editor]('.$edit.')';
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function trackedLinkCreated(array $data): string
    {
        $label = (string) ($data['label'] ?? 'Tracked link');
        $short = (string) ($data['short_url'] ?? $data['url'] ?? '');

        $lines = ['**Tracked link created:** '.$label];
        if ($short !== '') {
            $lines[] = 'Share: '.$short;
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function campaignAction(array $data): string
    {
        if (isset($data['error'])) {
            return '**Could not update campaign:** '.(string) $data['error'];
        }

        $name = (string) ($data['name'] ?? 'Campaign');
        $status = (string) ($data['status'] ?? 'updated');

        return '**'.$name.'** is now **'.$status.'**.';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function contentPlan(array $data): string
    {
        if (isset($data['error'])) {
            return (string) $data['error'];
        }

        $planId = $data['plan_id'] ?? null;
        $href = (string) ($data['href'] ?? '/growth/content-employee');
        $count = $data['item_count'] ?? null;

        $lines = ['**Content plan ready**'.($planId ? ' (plan #'.$planId.')' : '').'.'];
        if ($count !== null) {
            $lines[] = $count.' items scheduled this week.';
        }
        $lines[] = '[View content employee]('.$href.')';

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    /**
     * @param  array<string, mixed>  $data
     */
    protected function promotionPublish(array $data): string
    {
        if (isset($data['error']) && is_string($data['error'])) {
            return '**Could not publish:** '.$data['error']
                .(isset($data['href']) ? "\n[Open Promotion Posts](".$data['href'].')' : '');
        }

        return '**Publishing queued** for post #'.($data['post_id'] ?? '?').'.'
            .("\n".(string) ($data['message'] ?? 'Check Promotion Posts for live status.'))
            .(isset($data['href']) ? "\n[Open Promotion Posts](".$data['href'].')' : '');
    }

    protected function promotionPost(array $data): string
    {
        $name = isset($data['funnel_name']) ? ' on **'.$data['funnel_name'].'**' : '';
        $topic = isset($data['topic']) ? ' “'.$data['topic'].'”' : '';
        $format = isset($data['format']) && $data['format'] === 'x_thread' ? ' (X thread)' : '';
        $gen = ($data['generating'] ?? false) === true
            ? "\nAI is writing the copy — ensure **promotion-generate** queue worker is running."
            : '';

        return '**Promotion post** #'.$data['id'].$topic.$format.$name.'.'
            .$gen
            .(isset($data['message']) && is_string($data['message']) ? "\n".$data['message'] : '')
            .(isset($data['href']) ? "\n[Open in Promotion Posts](".$data['href'].')' : '');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function genericSuccess(array $data): string
    {
        if (($data['ok'] ?? true) === false && isset($data['error'])) {
            return '**Error:** '.(string) $data['error'];
        }

        if (isset($data['message']) && is_string($data['message'])) {
            return (string) $data['message'];
        }

        $pairs = [];
        foreach (['name', 'id', 'status', 'href', 'edit_url', 'short_url'] as $key) {
            if (array_key_exists($key, $data) && $data[$key] !== null && $data[$key] !== '') {
                $pairs[] = '**'.str_replace('_', ' ', $key).'**: '.$data[$key];
            }
        }

        return $pairs !== [] ? implode("\n", $pairs) : 'Action completed successfully.';
    }
}
