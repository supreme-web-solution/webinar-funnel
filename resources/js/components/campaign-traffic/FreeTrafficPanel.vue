<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import {
    PLATFORM_OPTIONS,
    type FreeTrafficRoutes,
    type TrafficData,
    type TrafficSettings,
    useFreeTrafficPanel,
} from '@/composables/useFreeTrafficPanel';

const props = withDefaults(defineProps<{
    traffic: TrafficData;
    settings: TrafficSettings | null;
    routes: FreeTrafficRoutes;
    title?: string;
    description?: string;
}>(), {
    title: 'Free traffic',
    description: 'Track mentions and conversations per keyword.',
});

const trafficRef = computed(() => props.traffic);
const settingsRef = computed(() => props.settings);
const routesRef = computed(() => props.routes);

const {
    trafficSearch,
    trafficPlatform,
    trafficKeywordModalOpen,
    trafficAiSectionOpen,
    savingSettings,
    trafficAiLinkError,
    trafficKeywordForm,
    settingsForm,
    trafficMaxKeywords,
    trafficMaxMentionsPerKeyword,
    trafficAtKeywordLimit,
    trafficSuggestedKeywords,
    trafficRemainingKeywordSlots,
    trafficKeywordCapTooltip,
    trafficKeywordFetchCapTooltip,
    trafficActiveKeyword,
    trafficStatsScopeLabel,
    trafficPlatformTabs,
    trafficMaxRepliesPerDay,
    trafficReplyPlatforms,
    trafficPlatformMeta,
    trafficKeywordIsFetching,
    trafficKeywordPlayPauseDisabled,
    trafficKeywordFilterActive,
    trafficAccountForPlatform,
    trafficRepliesPostedToday,
    openTrafficKeywordModal,
    closeTrafficKeywordModal,
    submitTrafficKeyword,
    selectTrafficSuggestedKeyword,
    addTrafficSuggestedKeyword,
    toggleTrafficPlatform,
    toggleTrafficKeywordFilter,
    toggleTrafficKeywordActive,
    canFetchTrafficKeyword,
    toggleTrafficKeywordNotifications,
    fetchTrafficKeywordNow,
    deleteTrafficKeyword,
    saveTrafficAiSettings,
    saveTrafficAiReplyEnabled,
    goToTrafficMentionsPage,
    fmtTrafficDate,
    trunc,
    trafficReplyStatusMeta,
    trafficReplyStatusHint,
    trafficMentionAutoReplied,
    trafficMentionCanDraftReply,
    trafficReplyDraftModalOpen,
    trafficReplyDraftLoading,
    trafficReplyDraftText,
    trafficReplyDraftWarning,
    trafficReplyDraftSource,
    trafficReplyDraftMention,
    openTrafficReplyDraftModal,
    copyTrafficReplyDraft,
} = useFreeTrafficPanel(trafficRef, settingsRef, routesRef);
</script>

<template>
    <div class="space-y-4">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h3 class="text-sm font-semibold text-foreground">{{ title }}</h3>
                <p class="text-xs text-muted-foreground mt-0.5">
                    {{ description }}
                    (up to {{ trafficMaxKeywords }} keywords, {{ trafficMaxMentionsPerKeyword.toLocaleString() }} mentions each).
                </p>
            </div>
            <TooltipProvider :delay-duration="120">
                <Tooltip>
                    <TooltipTrigger as-child>
                        <span class="inline-flex">
                            <Button
                                type="button"
                                size="sm"
                                class="h-8 text-xs gap-1.5 bg-primary text-primary-foreground hover:opacity-90"
                                :disabled="trafficAtKeywordLimit"
                                @click="openTrafficKeywordModal()"
                            >
                                <Icon icon="heroicons:plus" class="size-3.5" />
                                Add Keyword
                            </Button>
                        </span>
                    </TooltipTrigger>
                    <TooltipContent v-if="trafficAtKeywordLimit" side="bottom" class="max-w-xs text-xs">
                        This funnel already has the maximum of {{ trafficMaxKeywords }} keywords. Delete one to add another.
                    </TooltipContent>
                </Tooltip>
            </TooltipProvider>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <Card class="border shadow-sm"><CardContent class="p-4"><p class="text-xs text-muted-foreground">Total Mentions <span class="text-muted-foreground/70">({{ trafficStatsScopeLabel }})</span></p><p class="text-2xl font-bold mt-1">{{ traffic.stats.total.toLocaleString() }}</p></CardContent></Card>
            <Card class="border shadow-sm"><CardContent class="p-4"><p class="text-xs text-muted-foreground">This Week <span class="text-muted-foreground/70">({{ trafficStatsScopeLabel }})</span></p><p class="text-2xl font-bold mt-1 text-[#40E0D0]">{{ traffic.stats.this_week.toLocaleString() }}</p></CardContent></Card>
            <Card class="border shadow-sm"><CardContent class="p-4"><p class="text-xs text-muted-foreground">Keywords</p><p class="text-2xl font-bold mt-1 text-[#FFAD00]">{{ traffic.stats.keywords_count }}</p></CardContent></Card>
            <Card class="border shadow-sm"><CardContent class="p-4"><p class="text-xs text-muted-foreground">Platforms</p><p class="text-2xl font-bold mt-1 text-[#a78bfa]">{{ Object.keys(traffic.stats.platforms ?? {}).length }}</p></CardContent></Card>
        </div>

        <Card class="border shadow-sm border-dashed border-primary/25 overflow-hidden">
            <Collapsible v-model:open="trafficAiSectionOpen">
                <CardHeader class="space-y-0 pb-3 pt-4 px-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                        <CollapsibleTrigger
                            type="button"
                            class="flex flex-1 min-w-0 items-start gap-2 rounded-lg border border-transparent px-1 py-0.5 text-left outline-none ring-offset-background transition-colors hover:border-border hover:bg-muted/40 focus-visible:ring-2 focus-visible:ring-ring"
                        >
                            <Icon
                                icon="heroicons:chevron-right"
                                class="size-5 shrink-0 text-muted-foreground transition-transform duration-200"
                                :class="{ 'rotate-90': trafficAiSectionOpen }"
                            />
                            <div class="min-w-0 space-y-0.5">
                                <CardTitle class="text-sm font-semibold leading-tight">AI traffic auto-reply</CardTitle>
                                <p class="text-[0.65rem] text-muted-foreground leading-snug">
                                    <span v-if="!trafficAiSectionOpen">Collapsed — expand to enable auto-replies and set your link / tone.</span>
                                    <span v-else>Uses your connected Social posting accounts (one per platform).</span>
                                </p>
                            </div>
                        </CollapsibleTrigger>
                        <div class="flex shrink-0 flex-wrap items-center gap-2 sm:justify-end">
                            <Button size="sm" variant="outline" class="h-9 text-xs gap-1.5" as-child>
                                <Link href="/settings/social-traffic">
                                    <Icon icon="heroicons:link-20-solid" class="size-3.5" />
                                    Connect accounts
                                </Link>
                            </Button>
                        </div>
                    </div>
                </CardHeader>
                <CollapsibleContent>
                    <CardContent class="space-y-3 border-t border-border/60 px-4 pb-4 pt-3">
                        <div class="flex flex-wrap gap-2">
                            <Button size="sm" class="h-9 text-xs gap-1.5 bg-primary text-primary-foreground hover:opacity-90" as-child>
                                <Link href="/settings/social-traffic">
                                    <Icon icon="simple-icons:reddit" class="size-3.5" />
                                    Go to Social posting settings
                                </Link>
                            </Button>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <Label class="text-xs">Enable auto-replies for this funnel</Label>
                            <Switch
                                :model-value="Boolean(settingsForm.traffic_ai_reply_enabled)"
                                :disabled="savingSettings || settingsForm.processing"
                                @update:model-value="saveTrafficAiReplyEnabled(Boolean($event))"
                            />
                        </div>
                        <div class="space-y-1">
                            <Label class="text-xs">Link override (optional)</Label>
                            <Input
                                v-model="settingsForm.traffic_ai_link_override"
                                type="text"
                                class="h-9 text-xs"
                                placeholder="https://… (else: affiliate → offer → webinar CTA)"
                            />
                            <p v-if="trafficAiLinkError" class="text-[0.65rem] text-destructive">
                                {{ trafficAiLinkError }}
                            </p>
                        </div>
                        <div class="space-y-1">
                            <Label class="text-xs">More context</Label>
                            <p class="text-[0.65rem] text-muted-foreground leading-snug">
                                Optional background for AI replies — used by auto-reply and Draft reply on mentions.
                            </p>
                            <Textarea
                                v-model="settingsForm.traffic_ai_extra_context"
                                class="min-h-[72px] text-xs resize-y"
                                placeholder="Your product, audience, tone, what to avoid…"
                            />
                        </div>
                        <div class="space-y-2">
                            <Label class="text-xs">Posting accounts (from Settings → Social posting)</Label>
                            <div class="grid gap-2 sm:grid-cols-3">
                                <div
                                    v-for="p in trafficReplyPlatforms"
                                    :key="p.key"
                                    class="rounded-lg border border-border/80 bg-muted/20 px-3 py-2.5"
                                    :class="trafficAccountForPlatform(p.key) ? 'border-blue-500/40' : ''"
                                >
                                    <div class="flex items-center gap-2">
                                        <Icon :icon="p.icon" class="size-4 shrink-0" :style="{ color: p.color }" />
                                        <span class="text-xs font-medium">{{ p.label }}</span>
                                    </div>
                                    <p v-if="trafficAccountForPlatform(p.key)" class="mt-1.5 text-[0.65rem] text-blue-600 dark:text-blue-400">
                                        Connected · {{ trafficAccountForPlatform(p.key)?.platform_username || 'account linked' }}
                                    </p>
                                    <p v-if="trafficAccountForPlatform(p.key)" class="text-[0.65rem] text-muted-foreground">
                                        {{ trafficRepliesPostedToday(p.key) }} / {{ trafficMaxRepliesPerDay }} replies sent today
                                    </p>
                                    <p v-else class="mt-1.5 text-[0.65rem] text-muted-foreground">
                                        Not connected —
                                        <Link href="/settings/social-traffic" class="underline hover:text-foreground">connect</Link>
                                    </p>
                                </div>
                            </div>
                        </div>
                        <Button
                            size="sm"
                            class="h-9 text-xs bg-primary text-primary-foreground hover:opacity-90"
                            :disabled="savingSettings || settingsForm.processing"
                            @click="saveTrafficAiSettings()"
                        >
                            Save auto-reply settings
                        </Button>
                    </CardContent>
                </CollapsibleContent>
            </Collapsible>
        </Card>

        <Dialog v-model:open="trafficKeywordModalOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Track a new traffic keyword</DialogTitle>
                    <DialogDescription>
                        Search Reddit, YouTube, X, and news for this term and attach mentions to this funnel.
                    </DialogDescription>
                </DialogHeader>
                <form id="traffic-keyword-form" class="flex flex-col gap-4" @submit.prevent="submitTrafficKeyword()">
                    <div class="space-y-2">
                        <Label for="traffic-keyword-name" class="text-xs">Keyword</Label>
                        <Input
                            id="traffic-keyword-name"
                            v-model="trafficKeywordForm.name"
                            placeholder="e.g. your brand name…"
                            class="h-9 text-sm"
                            autocomplete="off"
                        />
                        <p v-if="trafficKeywordForm.errors.name" class="text-xs text-destructive">
                            {{ trafficKeywordForm.errors.name }}
                        </p>
                        <div
                            v-if="trafficSuggestedKeywords.length > 0"
                            class="space-y-2 rounded-lg border border-dashed border-blue-200/80 bg-blue-50/50 p-3 dark:border-blue-900/50 dark:bg-blue-950/20"
                        >
                            <div>
                                <p class="text-xs font-semibold text-foreground">Suggested keywords</p>
                                <p class="text-[0.65rem] text-muted-foreground leading-relaxed mt-0.5">
                                    High-intent phrases for this offer. Click a phrase to fill the field, or
                                    <span class="font-medium text-foreground">+</span> to add and start fetching.
                                    <template v-if="trafficRemainingKeywordSlots > 0">
                                        {{ trafficRemainingKeywordSlots }} slot{{ trafficRemainingKeywordSlots === 1 ? '' : 's' }} left.
                                    </template>
                                </p>
                            </div>
                            <div class="flex flex-wrap gap-1.5">
                                <div
                                    v-for="suggestion in trafficSuggestedKeywords"
                                    :key="suggestion"
                                    class="inline-flex max-w-full items-stretch overflow-hidden rounded-full border text-xs shadow-sm"
                                    :class="
                                        trafficKeywordForm.name.trim().toLowerCase() === suggestion.trim().toLowerCase()
                                            ? 'border-primary/50 bg-primary/10'
                                            : 'border-border bg-background/90'
                                    "
                                >
                                    <button
                                        type="button"
                                        class="inline-flex min-w-0 items-center px-2.5 py-1 font-medium text-foreground transition-colors hover:bg-muted/60"
                                        :title="`Use “${suggestion}”`"
                                        @click="selectTrafficSuggestedKeyword(suggestion)"
                                    >
                                        <span class="truncate">{{ suggestion }}</span>
                                    </button>
                                    <button
                                        type="button"
                                        class="inline-flex shrink-0 items-center border-l border-border/80 px-2 text-primary transition-colors hover:bg-primary/10 disabled:opacity-40"
                                        :disabled="trafficKeywordForm.processing || trafficAtKeywordLimit"
                                        :title="`Add “${suggestion}” and fetch`"
                                        @click="addTrafficSuggestedKeyword(suggestion)"
                                    >
                                        <Icon icon="heroicons:plus" class="size-3.5" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="space-y-2">
                        <Label class="text-xs">Platforms</Label>
                        <div class="flex flex-wrap gap-1.5">
                            <button
                                v-for="p in PLATFORM_OPTIONS"
                                :key="p"
                                type="button"
                                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full border text-xs font-medium transition-colors"
                                :class="trafficKeywordForm.platforms.includes(p) ? 'bg-primary/15 border-primary/40 text-primary' : 'bg-muted/30 border-border text-muted-foreground'"
                                @click="toggleTrafficPlatform(p)"
                            >
                                <Icon :icon="trafficPlatformMeta(p).icon" class="size-3" />
                                {{ p }}
                            </button>
                        </div>
                    </div>
                </form>
                <DialogFooter class="gap-2 sm:gap-0">
                    <Button type="button" variant="outline" size="sm" class="h-9" @click="closeTrafficKeywordModal()">
                        Cancel
                    </Button>
                    <Button
                        type="submit"
                        form="traffic-keyword-form"
                        size="sm"
                        class="h-9 bg-primary text-primary-foreground hover:opacity-90"
                        :disabled="trafficKeywordForm.processing || !trafficKeywordForm.name.trim()"
                    >
                        Add & Fetch
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="trafficReplyDraftModalOpen">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Draft reply</DialogTitle>
                    <DialogDescription>
                        Copy this reply, open the post, and paste it as a comment. This does not auto-post.
                    </DialogDescription>
                </DialogHeader>
                <div v-if="trafficReplyDraftMention" class="space-y-3">
                    <p
                        v-if="trafficReplyDraftWarning"
                        class="rounded-md border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-xs text-amber-900 dark:text-amber-200"
                    >
                        {{ trafficReplyDraftWarning }}
                    </p>
                    <p v-else-if="trafficReplyDraftSource === 'openai'" class="text-[0.65rem] text-muted-foreground">
                        Generated with AI using your More context and this post.
                    </p>
                    <p class="text-xs text-muted-foreground line-clamp-2">
                        {{ trunc(trafficReplyDraftMention.title ?? trafficReplyDraftMention.content ?? '', 160) }}
                    </p>
                    <Textarea
                        v-model="trafficReplyDraftText"
                        class="min-h-[140px] text-sm"
                        :disabled="trafficReplyDraftLoading"
                        placeholder="Generating reply…"
                    />
                    <a
                        v-if="trafficReplyDraftMention.permalink"
                        :href="trafficReplyDraftMention.permalink"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline"
                    >
                        <Icon icon="heroicons:arrow-top-right-on-square" class="size-3.5" />
                        Open post to comment
                    </a>
                </div>
                <DialogFooter class="gap-2 sm:gap-0">
                    <Button type="button" variant="outline" @click="trafficReplyDraftModalOpen = false">Close</Button>
                    <Button
                        type="button"
                        :disabled="trafficReplyDraftLoading || !trafficReplyDraftText"
                        @click="copyTrafficReplyDraft()"
                    >
                        Copy reply
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <div class="grid grid-cols-1 lg:grid-cols-[280px_1fr] gap-4 items-start">
            <Card class="border shadow-sm">
                <CardHeader class="pb-2 pt-4 px-4">
                    <CardTitle class="text-sm font-semibold">
                        Tracked Keywords ({{ traffic.keywords.length }} / {{ trafficMaxKeywords }})
                    </CardTitle>
                </CardHeader>
                <CardContent class="p-0">
                    <div v-if="traffic.keywords.length === 0" class="py-8 text-center text-xs text-muted-foreground">No keywords yet.</div>
                    <ul v-else class="divide-y divide-border">
                        <li
                            v-for="kw in traffic.keywords"
                            :key="kw.id"
                            role="button"
                            tabindex="0"
                            class="px-4 py-3 cursor-pointer transition-colors hover:bg-muted/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/40"
                            :class="trafficKeywordFilterActive(kw) ? 'bg-primary/10 border-l-2 border-l-primary' : 'border-l-2 border-l-transparent'"
                            :aria-pressed="trafficKeywordFilterActive(kw)"
                            @click="toggleTrafficKeywordFilter(kw)"
                            @keydown.enter.prevent="toggleTrafficKeywordFilter(kw)"
                            @keydown.space.prevent="toggleTrafficKeywordFilter(kw)"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0 flex-1">
                                    <p
                                        class="text-sm font-medium truncate max-w-full"
                                        :class="{
                                            'text-primary': trafficKeywordFilterActive(kw),
                                            'line-through opacity-60': !kw.is_active && !kw.mention_cap_reached,
                                            'text-amber-800 dark:text-amber-300': kw.mention_cap_reached && !trafficKeywordFilterActive(kw),
                                        }"
                                    >
                                        {{ kw.name }}
                                    </p>
                                    <p class="text-xs text-muted-foreground mt-0.5">
                                        {{ kw.mentions_count.toLocaleString() }} / {{ trafficMaxMentionsPerKeyword.toLocaleString() }} mentions
                                        <span v-if="kw.mention_cap_reached" class="text-amber-600 dark:text-amber-400"> · auto-paused (limit reached)</span>
                                        <span v-else-if="!kw.is_active" class="text-muted-foreground"> · paused</span>
                                        <span v-if="trafficKeywordFilterActive(kw)" class="text-primary"> · filtering mentions</span>
                                    </p>
                                </div>
                                <div class="flex items-center gap-1 shrink-0" @click.stop>
                                    <TooltipProvider :delay-duration="120">
                                        <Tooltip>
                                            <TooltipTrigger as-child>
                                                <span class="inline-flex">
                                                    <button
                                                        type="button"
                                                        class="flex size-7 items-center justify-center rounded-md text-muted-foreground hover:bg-muted/50 disabled:cursor-not-allowed disabled:opacity-40"
                                                        :disabled="!canFetchTrafficKeyword(kw)"
                                                        @click="fetchTrafficKeywordNow(kw)"
                                                    >
                                                        <Icon icon="heroicons:arrow-path" class="size-3.5" />
                                                    </button>
                                                </span>
                                            </TooltipTrigger>
                                            <TooltipContent v-if="kw.mention_cap_reached" side="top" class="max-w-xs text-xs">
                                                {{ trafficKeywordFetchCapTooltip }}
                                            </TooltipContent>
                                            <TooltipContent v-else side="top" class="text-xs">
                                                Fetch now from all platforms
                                            </TooltipContent>
                                        </Tooltip>
                                        <Tooltip>
                                            <TooltipTrigger as-child>
                                                <span class="inline-flex">
                                                    <button
                                                        type="button"
                                                        class="flex size-7 items-center justify-center rounded-md disabled:cursor-not-allowed disabled:opacity-40"
                                                        :class="trafficKeywordIsFetching(kw) ? 'text-[#40E0D0]' : 'text-muted-foreground'"
                                                        :disabled="trafficKeywordPlayPauseDisabled(kw)"
                                                        @click="toggleTrafficKeywordActive(kw)"
                                                    >
                                                        <Icon
                                                            :icon="trafficKeywordIsFetching(kw) ? 'heroicons:pause' : 'heroicons:play'"
                                                            class="size-3.5"
                                                        />
                                                    </button>
                                                </span>
                                            </TooltipTrigger>
                                            <TooltipContent v-if="kw.mention_cap_reached" side="top" class="max-w-xs text-xs">
                                                {{ trafficKeywordCapTooltip }}
                                            </TooltipContent>
                                            <TooltipContent v-else-if="kw.is_active" side="top" class="text-xs">
                                                Pause scheduled fetching for this keyword
                                            </TooltipContent>
                                            <TooltipContent v-else side="top" class="text-xs">
                                                Resume scheduled fetching for this keyword
                                            </TooltipContent>
                                        </Tooltip>
                                    </TooltipProvider>
                                    <button class="flex size-7 items-center justify-center rounded-md" :class="kw.email_notifications ? 'text-[#FFAD00]' : 'text-muted-foreground'" @click="toggleTrafficKeywordNotifications(kw)">
                                        <Icon :icon="kw.email_notifications ? 'heroicons:bell' : 'heroicons:bell-slash'" class="size-3.5" />
                                    </button>
                                    <button class="flex size-7 items-center justify-center rounded-md text-muted-foreground hover:text-destructive" @click="deleteTrafficKeyword(kw)">
                                        <Icon icon="heroicons:trash" class="size-3.5" />
                                    </button>
                                </div>
                            </div>
                        </li>
                    </ul>
                </CardContent>
            </Card>

            <div class="space-y-3">
                <Card class="border shadow-sm">
                    <CardContent class="p-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex flex-col gap-1.5 min-w-0">
                            <p v-if="trafficActiveKeyword" class="text-[0.65rem] text-muted-foreground">
                                Platform counts {{ trafficStatsScopeLabel }}
                            </p>
                            <div class="flex items-center gap-1 flex-wrap">
                                <button
                                    v-for="tab in trafficPlatformTabs"
                                    :key="tab.key"
                                    class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-md text-xs font-medium transition-colors"
                                    :class="trafficPlatform === tab.key ? 'bg-primary/15 text-primary border border-primary/30' : 'text-muted-foreground hover:bg-muted/50 border border-transparent'"
                                    @click="trafficPlatform = tab.key"
                                >
                                    <Icon v-if="tab.key" :icon="trafficPlatformMeta(tab.key).icon" class="size-3" />
                                    <Icon v-else icon="heroicons:squares-2x2" class="size-3" />
                                    {{ tab.label }}
                                    <span class="text-[0.6rem] text-muted-foreground">{{ tab.count }}</span>
                                </button>
                            </div>
                        </div>
                        <div class="relative shrink-0">
                            <Icon icon="heroicons:magnifying-glass" class="absolute left-2.5 top-1/2 -translate-y-1/2 size-3.5 text-muted-foreground pointer-events-none" />
                            <Input v-model="trafficSearch" placeholder="Search mentions…" class="h-8 pl-8 text-xs w-full sm:w-52" />
                        </div>
                    </CardContent>
                </Card>

                <Card v-if="traffic.mentions.total === 0" class="border shadow-sm">
                    <CardContent class="py-10 text-center text-sm text-muted-foreground">No traffic mentions found for this funnel.</CardContent>
                </Card>

                <div v-else class="flex flex-col gap-3">
                    <Card v-for="mention in traffic.mentions.data" :key="mention.id" class="border shadow-sm">
                        <CardContent class="p-4">
                            <div class="flex items-start gap-3">
                                <div class="flex size-9 shrink-0 items-center justify-center rounded-lg mt-0.5" :style="{ background: trafficPlatformMeta(mention.source_type).bg }">
                                    <Icon :icon="trafficPlatformMeta(mention.source_type).icon" class="size-4.5" :style="{ color: trafficPlatformMeta(mention.source_type).color }" />
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between gap-2 mb-1">
                                        <div class="flex items-center gap-2 min-w-0 flex-wrap">
                                            <Badge class="text-[0.6rem] px-1.5 py-0 border font-semibold">{{ mention.source_type }}</Badge>
                                            <Badge
                                                v-if="trafficReplyStatusMeta(mention.traffic_reply_attempt)"
                                                class="inline-flex items-center gap-0.5 text-[0.6rem] px-1.5 py-0 border font-semibold"
                                                :class="trafficReplyStatusMeta(mention.traffic_reply_attempt)!.class"
                                                :title="trafficReplyStatusHint(mention.traffic_reply_attempt) ?? undefined"
                                            >
                                                <Icon
                                                    v-if="trafficMentionAutoReplied(mention.traffic_reply_attempt)"
                                                    icon="heroicons:chat-bubble-left-ellipsis-solid"
                                                    class="size-3"
                                                />
                                                {{ trafficReplyStatusMeta(mention.traffic_reply_attempt)!.label }}
                                            </Badge>
                                            <span v-if="mention.keyword" class="text-[0.65rem] text-muted-foreground truncate">#{{ mention.keyword.name }}</span>
                                        </div>
                                        <div class="flex items-center gap-1.5 shrink-0 flex-wrap justify-end">
                                            <Button
                                                v-if="trafficMentionCanDraftReply(mention)"
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                class="h-7 px-2 text-[0.65rem]"
                                                :disabled="trafficReplyDraftLoading && trafficReplyDraftMention?.id === mention.id"
                                                @click.stop="openTrafficReplyDraftModal(mention)"
                                            >
                                                <Icon
                                                    icon="heroicons:sparkles"
                                                    class="size-3.5 mr-1"
                                                    :class="{ 'animate-pulse': trafficReplyDraftLoading && trafficReplyDraftMention?.id === mention.id }"
                                                />
                                                Draft reply
                                            </Button>
                                            <a
                                                v-if="mention.permalink"
                                                :href="mention.permalink"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="inline-flex items-center gap-1 text-[0.65rem] font-medium text-primary hover:underline"
                                            >
                                                <Icon icon="heroicons:arrow-top-right-on-square" class="size-3.5" />
                                                Open
                                            </a>
                                            <span class="text-[0.65rem] text-muted-foreground">{{ fmtTrafficDate(mention.posted_at) }}</span>
                                        </div>
                                    </div>
                                    <a
                                        v-if="mention.title && mention.permalink"
                                        :href="mention.permalink"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="text-sm font-semibold leading-snug mb-1 text-primary hover:underline block"
                                    >
                                        {{ trunc(mention.title, 140) }}
                                    </a>
                                    <p v-else-if="mention.title" class="text-sm font-semibold leading-snug mb-1">{{ trunc(mention.title, 140) }}</p>
                                    <p v-if="mention.content && mention.content !== mention.title" class="text-xs text-muted-foreground leading-relaxed">{{ trunc(mention.content, 220) }}</p>
                                    <p
                                        v-if="trafficMentionAutoReplied(mention.traffic_reply_attempt)"
                                        class="mt-1.5 text-[0.65rem] text-blue-700 dark:text-blue-400"
                                    >
                                        Your app posted an auto-reply
                                        <template v-if="mention.traffic_reply_attempt?.posted_at">
                                            · {{ fmtTrafficDate(mention.traffic_reply_attempt.posted_at) }}
                                        </template>
                                    </p>
                                    <p
                                        v-else-if="trafficReplyStatusHint(mention.traffic_reply_attempt)"
                                        class="mt-1.5 text-[0.65rem] text-muted-foreground"
                                    >
                                        {{ trafficReplyStatusHint(mention.traffic_reply_attempt) }}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <div
                        v-if="traffic.mentions.last_page > 1"
                        class="flex flex-col gap-2 rounded-lg border bg-card px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <p class="text-xs text-muted-foreground">
                            <template v-if="traffic.mentions.from && traffic.mentions.to">
                                {{ traffic.mentions.from }}–{{ traffic.mentions.to }} of
                            </template>
                            {{ traffic.mentions.total.toLocaleString() }} mentions
                            <span class="text-muted-foreground/80">(page {{ traffic.mentions.current_page }} / {{ traffic.mentions.last_page }})</span>
                        </p>
                        <div class="flex flex-wrap items-center gap-1">
                            <button
                                v-for="link in traffic.mentions.links"
                                :key="`${link.label}-${link.url ?? 'disabled'}`"
                                type="button"
                                :disabled="!link.url"
                                class="inline-flex h-7 min-w-7 items-center justify-center rounded-md border px-1.5 text-xs transition-colors disabled:cursor-not-allowed disabled:opacity-40"
                                :class="link.active
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'border-border bg-background text-foreground hover:bg-muted'"
                                @click="goToTrafficMentionsPage(link.url)"
                                v-html="link.label"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
