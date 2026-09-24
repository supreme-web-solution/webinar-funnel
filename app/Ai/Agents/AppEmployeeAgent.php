<?php

namespace App\Ai\Agents;

use App\Ai\Tools\CreateCampaignTool;
use App\Ai\Tools\CreatePromotionPostTool;
use App\Ai\Tools\CreateTrackedLinkTool;
use App\Ai\Tools\DeleteCampaignTool;
use App\Ai\Tools\DeleteContentPlanTool;
use App\Ai\Tools\DeleteTrackedLinkTool;
use App\Ai\Tools\DraftCampaignPlanTool;
use App\Ai\Tools\DraftContentPlanTool;
use App\Ai\Tools\ExecuteContentPlanTool;
use App\Ai\Tools\GatedTool;
use App\Ai\Tools\GenerateContentPlanTool;
use App\Ai\Tools\GetAttentionQueueTool;
use App\Ai\Tools\GetCampaignTool;
use App\Ai\Tools\GetStatusTool;
use App\Ai\Tools\ListCampaignsTool;
use App\Ai\Tools\ListFunnelsTool;
use App\Ai\Tools\ListLeadsTool;
use App\Ai\Tools\ListSocialAccountsTool;
use App\Ai\Tools\ListTrackedLinksTool;
use App\Ai\Tools\PauseCampaignTool;
use App\Ai\Tools\ListPromotionPostsTool;
use App\Ai\Tools\PublishCampaignTool;
use App\Ai\Tools\PublishPromotionPostTool;
use App\Ai\Tools\QuickStartCampaignTool;
use App\Ai\Tools\SearchMarketplaceTool;
use App\Ai\Tools\ExtractOfferUrlTool;
use App\Models\User;
use App\Services\AiEmployee\AiEmployeeSettingsService;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Stringable;

#[Provider(Lab::OpenRouter)]
#[MaxSteps(8)]
#[Timeout(120)]
class AppEmployeeAgent implements Agent, Conversational, HasTools
{
    use Promptable, RemembersConversations;

    public function __construct(public User $user) {}

    public function instructions(): Stringable|string
    {
        $name = (string) config('ai_employee.name', 'Alex');
        $appName = (string) config('app.name', 'this app');
        $settings = app(AiEmployeeSettingsService::class)->for($this->user);
        $autonomy = $settings->autonomy;

        return <<<TXT
You are {$name}, the {$appName} AI employee for {$this->user->name}.
You help with affiliate campaigns, funnels/webinars, leads, tracked links, social promotion, and content plans inside this app. Never invent campaign IDs or other entity IDs.

Autonomy is **{$autonomy}**:
- copilot: read + draft (prepare) only; mutating tools are blocked unless the user LAUNCHes a staged action
- assisted: mutating tools stage Review & Launch; user says LAUNCH {id} or approves in UI
- autopilot: allowlisted execute tools may run without staging

When to use tools (only when the message needs live data or an action):
- General chat, greetings, or explaining how {$appName} works → reply in text; no tool.
- Workspace overview or “how am I doing?” → get_status or get_attention_queue.
- Need a specific campaign ID or details → list_campaigns / get_campaign.
- Find offers by keyword/niche on ClickBank, JVZoo, WarriorPlus → search_marketplace.
- User pasted an offer/sales/hoplink URL to inspect → extract_offer_url; to build a full campaign from that URL → quick_start_campaign (or create_campaign for manual fields).
- Tracked links, publish/pause/delete campaigns, content plans → the matching named tool.
- Promotion / social: create_promotion_post (stage + LAUNCH once to create & generate). X thread = ONE call, twitter_thread true, short topic (not full tweet text). After LAUNCH succeeded, post id is in the tool result — do NOT ask to LAUNCH the same approval again.
- “Post/publish to Twitter” → publish_promotion_post with post_id when status is ready; use list_promotion_posts if unsure. LAUNCH is only for pending Review & Launch cards, not for publishing to social.
- Copilot + user wants a mutating action → draft_campaign_plan or draft_content_plan to stage, then they LAUNCH.

Do not use open-web search; this app has marketplace search and URL extract only.
Destructive deletes always stage approval; never assume they ran.
After tools, summarize in plain English for the user. Never paste raw JSON, tool payloads, or API dumps in chat.
Use short headings, numbered lists, and markdown links [label](url) for offer or editor links when helpful.
Do not claim email was sent or data was deleted unless a tool did it.
User shortcuts (handled outside you): help, status, attention, LAUNCH {id}, REJECT {id}, PAUSE {id}, RESUME.
Keep replies concise; WhatsApp under 1500 characters.
TXT;
    }

    protected function maxConversationMessages(): int
    {
        return (int) config('ai_employee.history_limit', 40);
    }

    public function tools(): iterable
    {
        return array_values($this->toolMap());
    }

    /**
     * @return array<string, GatedTool>
     */
    public function toolMap(): array
    {
        $user = $this->user;

        $tools = [
            new GetStatusTool($user),
            new GetAttentionQueueTool($user),
            new ListCampaignsTool($user),
            new GetCampaignTool($user),
            new ListFunnelsTool($user),
            new ListLeadsTool($user),
            new ListTrackedLinksTool($user),
            new ListSocialAccountsTool($user),
            new ListPromotionPostsTool($user),
            new SearchMarketplaceTool($user),
            new ExtractOfferUrlTool($user),
            new DraftCampaignPlanTool($user),
            new DraftContentPlanTool($user),
            new CreateCampaignTool($user),
            new QuickStartCampaignTool($user),
            new PublishCampaignTool($user),
            new PauseCampaignTool($user),
            new CreateTrackedLinkTool($user),
            new GenerateContentPlanTool($user),
            new ExecuteContentPlanTool($user),
            new CreatePromotionPostTool($user),
            new PublishPromotionPostTool($user),
            new DeleteCampaignTool($user),
            new DeleteTrackedLinkTool($user),
            new DeleteContentPlanTool($user),
        ];

        $map = [];
        foreach ($tools as $tool) {
            $name = $tool instanceof GatedTool
                ? $tool->toolName()
                : strtolower(class_basename($tool));
            $map[$name] = $tool;
        }

        return $map;
    }

    public function gatedTool(string $name): ?GatedTool
    {
        $tool = $this->toolMap()[$name] ?? null;

        return $tool instanceof GatedTool ? $tool : null;
    }
}
