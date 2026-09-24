<?php

namespace App\Services\AiEmployee;

use App\Models\AiActionApproval;
use App\Models\Campaign;
use App\Models\CampaignLead;
use App\Models\Funnel;
use App\Models\FunnelPromotionPost;
use App\Models\Lead;
use App\Models\SocialAccount;
use App\Models\TrackedLink;
use App\Models\TrafficReplyAttempt;
use App\Models\User;

class CommandCenterSnapshotService
{
    /**
     * @return array<string, mixed>
     */
    public function status(User $user): array
    {
        $campaigns = Campaign::query()->where('user_id', $user->id);
        $funnelIds = Funnel::query()->where('user_id', $user->id)->pluck('id');

        return [
            'campaigns' => [
                'total' => (clone $campaigns)->count(),
                'published' => (clone $campaigns)->where('status', 'published')->count(),
                'draft' => (clone $campaigns)->where('status', 'draft')->count(),
            ],
            'funnels' => Funnel::query()->where('user_id', $user->id)->count(),
            'leads' => Lead::query()->whereIn('funnel_id', $funnelIds)->count()
                + CampaignLead::query()->whereIn(
                    'campaign_id',
                    Campaign::query()->where('user_id', $user->id)->select('id')
                )->count(),
            'tracked_links' => TrackedLink::query()->where('user_id', $user->id)->count(),
            'social_accounts' => SocialAccount::query()->where('user_id', $user->id)->count(),
            'promotion_posts' => FunnelPromotionPost::query()->where('user_id', $user->id)->count(),
        ];
    }

    /**
     * @return list<array{type: string, title: string, detail: string, href?: string}>
     */
    public function attention(User $user): array
    {
        $items = [];

        $pendingApprovals = AiActionApproval::query()
            ->where('user_id', $user->id)
            ->where('status', AiActionApproval::STATUS_PENDING)
            ->count();
        if ($pendingApprovals > 0) {
            $items[] = [
                'type' => 'approval',
                'title' => $pendingApprovals.' action'.($pendingApprovals === 1 ? '' : 's').' waiting to launch',
                'detail' => 'Say LAUNCH {id} or open Command Center to approve.',
                'href' => '/command-center',
            ];
        }

        $drafts = Campaign::query()
            ->where('user_id', $user->id)
            ->where('status', 'draft')
            ->latest()
            ->limit(5)
            ->get(['id', 'name', 'wizard_step']);
        foreach ($drafts as $campaign) {
            $items[] = [
                'type' => 'campaign',
                'title' => 'Draft campaign: '.$campaign->name,
                'detail' => 'Wizard step '.$campaign->wizard_step.' — publish when ready.',
                'href' => '/campaigns/'.$campaign->id.'/edit',
            ];
        }

        $failedPosts = FunnelPromotionPost::query()
            ->where('user_id', $user->id)
            ->where('status', FunnelPromotionPost::STATUS_FAILED)
            ->latest()
            ->limit(5)
            ->get(['id', 'topic', 'last_error', 'funnel_id']);
        foreach ($failedPosts as $post) {
            $items[] = [
                'type' => 'promotion',
                'title' => 'Failed post: '.($post->topic ?: '#'.$post->id),
                'detail' => (string) ($post->last_error ?: 'Generation or publish failed.'),
                'href' => '/funnels/'.$post->funnel_id.'/promotion/posts',
            ];
        }

        $failedReplies = TrafficReplyAttempt::query()
            ->where('user_id', $user->id)
            ->where('status', TrafficReplyAttempt::STATUS_FAILED)
            ->latest()
            ->limit(5)
            ->get(['id', 'last_error']);
        foreach ($failedReplies as $attempt) {
            $items[] = [
                'type' => 'traffic',
                'title' => 'Failed mention reply',
                'detail' => (string) ($attempt->last_error ?: 'Reply did not post.'),
                'href' => '/traffic',
            ];
        }

        return $items;
    }
}
